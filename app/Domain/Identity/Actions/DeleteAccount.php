<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Services\PersonalDataExporter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Account deletion = irreversible anonymisation. The row stays (foreign keys of public content),
 * but nothing personal remains. Content is either deleted or kept as anonymous.
 */
class DeleteAccount
{
    public function __invoke(User $user, bool $deleteContent): void
    {
        DB::transaction(function () use ($user, $deleteContent) {
            foreach (app(PersonalDataExporter::class)->contributors() as $c) {
                $c->erase($user, $deleteContent);
            }
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->tokens()->delete();
            $user->consents()->delete();
            $user->settings()->delete();
            $user->blockedUsers()->detach();
            DB::table('user_blocks')->where('blocked_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();
            $user->syncRoles([]);

            $user->forceFill([
                'name' => 'Smazaný účet '.$user->id,
                'email' => 'deleted-'.$user->id.'@deleted.invalid',
                'password' => null,
                'google_id' => null,
                'avatar_path' => null,
                'location_id' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'status' => UserStatus::Deleted,
                'deleted_at' => now(),
            ])->save();

            app(AuditLogger::class)->log('account.deleted', $user, ['delete_content' => $deleteContent], $user->id);
        });
    }
}
