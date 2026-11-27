<?php

namespace App\Domain\Sharing\Services;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;

/**
 * 1200×630 PNG cards for social sharing (Open Graph). Rendered with GD + bundled OFL fonts
 * (resources/fonts/ttf). Cached by content hash; respects anonymity and never shows private originals.
 */
class ShareCard
{
    private const W = 1200;

    private const H = 630;

    public function forAnswer(Answer $a): string
    {
        $publicAuthor = $a->publicAuthor();
        $author = $publicAuthor !== null ? $publicAuthor->name : 'Anonym';
        $label = $a->is_stylized ? 'Stylizováno AI'.($a->style ? ' · '.$a->style->name : '') : 'Bez AI úprav';
        $text = '„'.$a->display_text.'“';

        return $this->render('a'.$a->id, $text, $a->question->text, $author.' · '.$label, 50);
    }

    public function forQuestion(Question $q): string
    {
        return $this->render('q'.$q->id.'v'.$q->version, $q->text, null, 'Odpověz po svém – stačí sto znaků.', 62);
    }

    private function render(string $id, string $main, ?string $sub, string $footer, int $size): string
    {
        $hash = substr(sha1($id.'|'.$main.'|'.$sub.'|'.$footer.'|v1'), 0, 16);
        $path = "cards/{$id}-{$hash}.png";
        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }

        $img = imagecreatetruecolor(self::W, self::H);
        $forest = imagecolorallocate($img, 0x24, 0x4A, 0x35);
        $cream = imagecolorallocate($img, 0xF7, 0xF3, 0xE9);
        $mustard = imagecolorallocate($img, 0xE5, 0xB5, 0x4B);
        $muted = imagecolorallocate($img, 0xCF, 0xE0, 0xD4);
        imagefilledrectangle($img, 0, 0, self::W, self::H, $forest);
        imagefilledrectangle($img, 0, self::H - 12, self::W, self::H, $mustard);

        $display = resource_path('fonts/ttf/Bricolage-ExtraBold.ttf');
        $body = resource_path('fonts/ttf/Inter-SemiBold.ttf');

        // Logo
        imagettftext($img, 30, 0, 70, 95, $cream, $display, 'MáTo');
        $box = imagettfbbox(30, 0, $display, 'MáTo');
        imagettftext($img, 30, 0, 70 + $box[2] - $box[0], 95, $mustard, $display, 'Cenu');
        $box2 = imagettfbbox(30, 0, $display, 'MáToCenu');
        imagettftext($img, 18, 0, 70 + $box2[2] - $box2[0], 95, $cream, $display, '.cz');

        $y = 170;
        if ($sub) {
            foreach (array_slice($this->wrap($sub, $body, 24, 1060), 0, 2) as $line) {
                imagettftext($img, 24, 0, 70, $y, $muted, $body, $line);
                $y += 38;
            }
            $y += 30;
        }
        // Shrink long texts until they fit in the available height.
        $lines = [];
        for ($s = $size; $s >= 28; $s -= 4) {
            $lines = $this->wrap($main, $display, $s, 1060);
            if (count($lines) * $s * 1.25 <= (self::H - 110 - $y)) {
                break;
            }
        }
        foreach ($lines as $line) {
            $y += (int) ($s * 1.25);
            imagettftext($img, $s, 0, 70, $y, $cream, $display, $line);
        }
        imagettftext($img, 22, 0, 70, self::H - 50, $mustard, $body, $footer);

        ob_start();
        imagepng($img, null, 6);
        $disk->put($path, (string) ob_get_clean());
        imagedestroy($img);

        return $disk->path($path);
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, int $size, int $max): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $try);
            if ($max < $box[2] - $box[0] && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }
}
