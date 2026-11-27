<?php

namespace App\Notifications;

use App\Models\Quote;

class QuoteAwarded extends MtcNotification
{
    public function __construct(public Quote $quote) {}

    public function type(): string
    {
        return 'quote_results';
    }

    public function title(): string
    {
        return 'Tvoje odpověď je '.mb_strtolower($this->quote->label()).'!';
    }

    public function body(): string
    {
        return '„'.$this->quote->display_text.'“ vyhrála hlasování komunity. Případné komerční využití by vyžadovalo tvůj samostatný souhlas.';
    }

    public function url(): string
    {
        return route('quotes.index');
    }
}
