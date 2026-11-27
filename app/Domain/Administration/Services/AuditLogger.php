<?php

namespace App\Domain\Administration\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const SECRET_KEYS = ['password', 'api_key', 'secret', 'token', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    /** @param array<string, mixed> $changes */
    public function log(string $action, ?Model $subject = null, array $changes = [], ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();
        $ip = $request?->ip();

        return AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ? $this->redact($changes) : null,
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed> */
    private function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && collect(self::SECRET_KEYS)->contains(fn ($s) => str_contains(strtolower($k), $s))) {
                $data[$k] = '[skryto]';
            } elseif (is_array($v)) {
                $data[$k] = $this->redact($v);
            }
        }

        return $data;
    }
}
