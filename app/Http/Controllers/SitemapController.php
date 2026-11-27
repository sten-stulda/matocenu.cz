<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Question;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** Only public pages: visible questions, categories, legal texts. No answers' internal AI variants, no admin. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('mtc:sitemap', 3600, function () {
            $urls = [[route('home'), now()], [route('questions.index'), null], [route('categories.index'), null], [route('rankings.index'), null]];
            if (feature('quotes')) {
                $urls[] = [route('quotes.index'), null];
            }
            foreach (Category::where('is_active', true)->get() as $c) {
                $urls[] = [route('categories.show', $c), null];
            }
            Question::query()->visible()->select(['id', 'slug', 'updated_at', 'last_activity_at'])->orderByDesc('id')->limit(40000)
                ->each(function ($q) use (&$urls) {
                    $urls[] = [route('questions.show', $q), $q->last_activity_at ?? $q->updated_at];
                });
            foreach (LegalDocument::TYPES as [, $slug]) {
                $urls[] = [route('legal.show', $slug), null];
            }
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as [$loc, $mod]) {
                $out .= '<url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod->toAtomString().'</lastmod>' : '').'</url>';
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
