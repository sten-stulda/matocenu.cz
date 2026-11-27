<?php

namespace App\Domain\Reactions\Events;

use App\Models\ReactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ReactionAdded
{
    use Dispatchable;

    public function __construct(public User $user, public Model $target, public ReactionType $type) {}
}
