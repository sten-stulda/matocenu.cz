<?php

namespace App\Console\Commands;

use App\Domain\Administration\Services\Settings;
use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('matocenu:prune')]
#[Description('Smaže data po uplynutí retenčních lhůt (audit, AI logy, …).')]
class PruneData extends Command
{
    public function handle(Settings $settings): int
    {
        $n = AuditLog::where('created_at', '<', now()->subDays($settings->int('privacy.retention_days_audit')))->delete();
        $this->line("Audit: smazáno $n záznamů.");
        foreach (config('matocenu.pruners', []) as $class) {
            $this->line(app($class)());
        }
        Cache::forever('mtc:task:prune', now()->toIso8601String());

        return self::SUCCESS;
    }
}
