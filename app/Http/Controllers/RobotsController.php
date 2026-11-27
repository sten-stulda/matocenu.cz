<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $allow = setting('seo.indexing') && app()->isProduction();
        $lines = ['User-agent: *'];
        if ($allow) {
            array_push($lines, 'Disallow: /admin', 'Disallow: /ucet', 'Disallow: /api', 'Disallow: /livewire', 'Disallow: /hledat');
            if (Route::has('sitemap')) {
                $lines[] = 'Sitemap: '.route('sitemap');
            }
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
