<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Exceptions\AiUnavailable;
use App\Models\AiPrompt;
use App\Models\AiPromptVersion;

/** Only the production version of a prompt is used for regular users. */
class PromptRegistry
{
    public function production(string $key): AiPromptVersion
    {
        $prompt = AiPrompt::where('key', $key)->first();
        $version = $prompt?->production();
        if (! $version) {
            throw new AiUnavailable("Prompt „{$key}“ nemá produkční verzi.", 'no_prompt');
        }

        return $version;
    }

    /**
     * Render {{variable}} placeholders. User-provided values are data, not instructions:
     * angle brackets are neutralised so they cannot close the <tags> that wrap them.
     *
     * @param  array<string, scalar|null>  $vars
     */
    public function render(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($vars) {
            $v = (string) ($vars[$m[1]] ?? '');

            return str_replace(['<', '>'], ['‹', '›'], $v);
        }, $template) ?? $template;
    }
}
