<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * draft → review → approved → production (→ archived). Only one production version per prompt
 * (DB partial unique index). Rollback re-promotes the previous production version.
 */
class PromptLifecycle
{
    private const ALLOWED = [
        'draft' => ['review', 'archived'],
        'review' => ['approved', 'draft', 'archived'],
        'approved' => ['production', 'archived'],
        'production' => [],
        'archived' => ['draft'],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function createDraft(User $user, AiPrompt $prompt, string $system, string $userTemplate, ?string $note = null): AiPromptVersion
    {
        $this->authorize($user, 'prompts.manage');
        foreach ((array) $prompt->variables as $var) {
            if (! str_contains($system.$userTemplate, '{{'.$var.'}}') && in_array($var, ['original_answer', 'question'], true)) {
                throw new InvalidArgumentException("Šablona musí obsahovat proměnnou {{{$var}}}.");
            }
        }

        return DB::transaction(function () use ($user, $prompt, $system, $userTemplate, $note) {
            $v = AiPromptVersion::create([
                'ai_prompt_id' => $prompt->id, 'version' => (int) $prompt->versions()->max('version') + 1,
                'system_template' => $system, 'user_template' => $userTemplate, 'status' => 'draft', 'change_note' => $note, 'created_by' => $user->id,
            ]);
            $this->history($v, null, 'draft', $user, $note);
            $this->audit->log('prompt.draft_created', $v, ['prompt' => $prompt->key, 'version' => $v->version]);

            return $v;
        });
    }

    public function transition(User $user, AiPromptVersion $v, string $to, ?string $note = null): AiPromptVersion
    {
        if (! in_array($to, self::ALLOWED[$v->status] ?? [], true)) {
            throw new InvalidArgumentException("Přechod {$v->status} → {$to} není povolen.");
        }
        $this->authorize($user, in_array($to, ['approved', 'production'], true) ? 'prompts.approve' : 'prompts.manage');
        if ($to === 'approved' && $v->created_by === $user->id && ! $user->hasRole('superadmin')) {
            throw new AuthorizationException('Vlastní verzi promptu musí schválit někdo jiný.');
        }

        return DB::transaction(function () use ($user, $v, $to, $note) {
            $from = $v->status;
            if ($to === 'production') {
                $current = AiPromptVersion::where('ai_prompt_id', $v->ai_prompt_id)->where('status', 'production')->lockForUpdate()->first();
                if ($current) {
                    $current->update(['status' => 'approved']);
                    $this->history($current, 'production', 'approved', $user, 'Nahrazeno verzí '.$v->version);
                }
                $v->production_at = now();
            }
            if ($to === 'approved') {
                $v->approved_by = $user->id;
                $v->approved_at = now();
            }
            $v->status = $to;
            $v->save();
            $this->history($v, $from, $to, $user, $note);
            $this->audit->log('prompt.'.$to, $v, ['from' => $from, 'to' => $to]);

            return $v;
        });
    }

    /** Re-promote the most recent previously-production version. */
    public function rollback(User $user, AiPrompt $prompt): AiPromptVersion
    {
        $this->authorize($user, 'prompts.approve');
        $current = $prompt->production();
        $previous = AiPromptVersion::where('ai_prompt_id', $prompt->id)->where('status', 'approved')->whereNotNull('production_at')
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))->orderByDesc('production_at')->first();
        if (! $previous) {
            throw new AiUnavailable('Není žádná předchozí produkční verze, na kterou by šlo vrátit.', 'no_prompt');
        }

        return $this->transition($user, $previous, 'production', 'Rollback');
    }

    private function history(AiPromptVersion $v, ?string $from, string $to, User $user, ?string $note): void
    {
        DB::table('ai_prompt_status_history')->insert(['ai_prompt_version_id' => $v->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $user->id, 'note' => $note, 'created_at' => now()]);
    }

    private function authorize(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException('Nemáš oprávnění.');
        }
    }
}
