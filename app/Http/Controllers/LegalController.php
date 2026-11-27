<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function show(string $slug): View
    {
        $type = LegalDocument::typeForSlug($slug);
        abort_if($type === null, 404);
        $doc = LegalDocument::current($type);
        abort_if($doc === null, 404);

        return view('pages.legal', ['doc' => $doc, 'all' => LegalDocument::TYPES]);
    }
}
