<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\RecordConsents;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsentController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.consents', [
            'terms' => LegalDocument::current('terms'),
            'privacy' => LegalDocument::current('privacy'),
        ]);
    }

    public function store(Request $request, RecordConsents $consents): RedirectResponse
    {
        $request->validate(['terms' => 'accepted', 'privacy' => 'accepted'], [
            'terms.accepted' => 'Bez souhlasu s podmínkami nelze pokračovat.',
            'privacy.accepted' => 'Potvrď prosím zásady ochrany osobních údajů.',
        ]);
        $consents->registration($request->user(), $request->boolean('marketing'), 'web');

        return redirect()->intended(route('home'))->with('status', 'Díky! Můžeš pokračovat.');
    }
}
