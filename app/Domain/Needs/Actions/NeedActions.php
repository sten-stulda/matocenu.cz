<?php

namespace App\Domain\Needs\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Needs\Enums\NeedType;
use App\Domain\Needs\Exceptions\NeedException;
use App\Models\Need;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NeedActions
{
    public function __construct(private Moderation $moderation, private AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:140'],
            'description' => ['required', 'string', 'min:15', 'max:1500'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'type' => ['required', Rule::enum(NeedType::class)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'urgency' => ['required', 'in:low,medium,high'],
            'is_anonymous' => ['boolean'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Need
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            throw new NeedException('Potřebu může zveřejnit jen uživatel s ověřeným e-mailem.');
        }
        $title = Text::clean((string) $data['title']);
        $description = Text::clean((string) $data['description'], multiline: true);

        return DB::transaction(function () use ($user, $data, $title, $description) {
            [$status, $check] = $this->moderation->initialStatus($title.' '.$description);
            $need = new Need([
                'category_id' => $data['category_id'], 'location_id' => $data['location_id'] ?: null, 'type' => $data['type'],
                'title' => $title, 'description' => $description, 'urgency' => $data['urgency'], 'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            ]);
            $need->user_id = $user->id;
            $need->slug = $this->slug($title);
            $need->moderation_status = $status;
            $need->published_at = now();
            $need->last_activity_at = now();
            $need->save();
            DB::table('need_status_history')->insert(['need_id' => $need->id, 'from_status' => null, 'to_status' => 'open', 'user_id' => $user->id, 'created_at' => now()]);
            if (! $check['ok']) {
                $this->moderation->recordAutoFlag($need, $check);
            }

            return $need;
        });
    }

    public function changeStatus(User $user, Need $need, string $to, ?string $note = null): void
    {
        if (! array_key_exists($to, Need::STATUSES)) {
            throw new NeedException('Neplatný stav.');
        }
        if ($need->user_id !== $user->id && ! $user->can('needs.manage')) {
            throw new NeedException('Stav potřeby mění jen její autor.');
        }
        DB::transaction(function () use ($user, $need, $to, $note) {
            $from = $need->status;
            $need->status = $to;
            if ($to === 'solved' && $note) {
                $need->forceFill(['solved_note' => mb_substr(Text::clean($note), 0, 500)]);
            }
            $need->save();
            DB::table('need_status_history')->insert(['need_id' => $need->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $user->id, 'note' => $note, 'created_at' => now()]);
            if ($user->id !== $need->user_id) {
                $this->audit->log('need.status', $need, ['from' => $from, 'to' => $to]);
            }
        });
    }

    private function slug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 90, ''), '-', 'cs') ?: 'potreba';
        $slug = $base;
        $i = 2;
        while (Need::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
