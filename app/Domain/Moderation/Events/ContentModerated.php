<?php

namespace App\Domain\Moderation\Events;

use App\Models\ModerationAction;
use Illuminate\Foundation\Events\Dispatchable;

class ContentModerated
{
    use Dispatchable;

    public function __construct(public ModerationAction $action) {}
}
