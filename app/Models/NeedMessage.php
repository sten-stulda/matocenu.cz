<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeedMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['need_offer_id', 'sender_id', 'recipient_id', 'body', 'recipients_count'];
}
