<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Rules\UniqueNickname;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateProfile
{
    /** @param array{name?: string, location_id?: int|string|null, avatar?: UploadedFile|null} $input */
    public function __invoke(User $user, array $input): User
    {
        $input['name'] = Str::squish((string) ($input['name'] ?? $user->name));
        $input['location_id'] = ($input['location_id'] ?? null) ?: null;

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN][\pL\pN ._\-]*$/u', new UniqueNickname($user->id)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->whereIn('type', ['region', 'district', 'municipality'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000'],
        ];
        Validator::make($input, $rules, ['name.regex' => 'Přezdívka může obsahovat písmena, číslice, mezery, tečky, podtržítka a pomlčky.'])->validate();

        return DB::transaction(function () use ($user, $input) {
            $user->name = $input['name'];
            $user->location_id = $input['location_id'] ? (int) $input['location_id'] : null;
            if (($input['avatar'] ?? null) instanceof UploadedFile) {
                $this->storeAvatar($user, $input['avatar']);
            }
            $user->save();

            return $user;
        });
    }

    public function removeAvatar(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    /** Re-encode to a 256×256 WebP: strips EXIF/GPS metadata and neutralises polyglot files. */
    private function storeAvatar(User $user, UploadedFile $file): void
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw ValidationException::withMessages(['avatar' => 'Obrázek se nepodařilo načíst.']);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(256, 256);
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
        ob_start();
        imagewebp($dst, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        $path = 'avatars/'.$user->id.'-'.Str::random(12).'.webp';
        Storage::disk('public')->put($path, $bytes);
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->avatar_path = $path;
    }
}
