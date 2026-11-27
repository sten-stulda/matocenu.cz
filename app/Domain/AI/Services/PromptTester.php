<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Exceptions\AiUnavailable;
use App\Models\AiModel;
use App\Models\AiPromptTestResult;
use App\Models\AiPromptVersion;
use App\Models\User;
use App\Support\Text;

/**
 * Runs a prompt version against its Czech test cases. Uses the real configured model (costs
 * money – started explicitly by an admin) or the offline FakeProvider in tests/CI.
 */
class PromptTester
{
    public function __construct(private AiGateway $gateway, private OutputValidator $validator) {}

    /** @return list<AiPromptTestResult> */
    public function run(User $user, AiPromptVersion $version, ?AiModel $model = null): array
    {
        $results = [];
        foreach ($version->prompt->testCases()->where('is_active', true)->get() as $case) {
            $vars = $case->variables + ['repair_note' => '', 'variants' => 3, 'max_characters' => 100, 'style_examples' => '', 'sensitivity_level' => 'běžná'];
            $checks = [];
            $output = null;
            $cost = '0';
            try {
                $r = $this->gateway->complete('prompt_test', $version, $vars, $user, null, null, $model);
                $cost = $r['cost_czk'];
                $output = $r['response']->text;
                $data = $this->validator->parse($output);
                $texts = isset($data['variants']) ? (array) $data['variants'] : (isset($data['question']) ? [$data['question']] : []);
                $checks[] = ['Platná struktura JSON', $texts !== []];
                $max = (int) ($case->expectations['max_length'] ?? ($vars['max_characters'] ?? 100));
                $original = (string) ($vars['original_answer'] ?? $vars['question'] ?? '');
                foreach ($texts as $t) {
                    $problem = is_string($t) ? $this->validator->check($original, Text::clean($t), $max) : 'neplatný typ';
                    $checks[] = ['„'.mb_substr((string) (is_string($t) ? $t : ''), 0, 60).'“', $problem === null, $problem];
                    foreach ((array) ($case->expectations['must_not_contain'] ?? []) as $bad) {
                        if (is_string($t) && str_contains(Text::fold($t), Text::fold((string) $bad))) {
                            $checks[] = ['Nesmí obsahovat „'.$bad.'“', false];
                        }
                    }
                }
            } catch (AiUnavailable $e) {
                $checks[] = ['Volání AI', false, $e->getMessage()];
            }
            $results[] = AiPromptTestResult::create([
                'ai_prompt_test_case_id' => $case->id, 'ai_prompt_version_id' => $version->id, 'ai_model_id' => $model?->id,
                'run_by' => $user->id, 'output' => $output, 'passed' => collect($checks)->every(fn ($c) => $c[1]), 'checks' => $checks, 'cost_czk' => $cost,
            ]);
        }

        return $results;
    }
}
