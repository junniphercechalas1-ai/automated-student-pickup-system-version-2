<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessageReply extends Model
{
    protected $fillable = [
        'support_message_id',
        'sender_type',
        'sender_id',
        'body',
    ];

    public function supportMessage()
    {
        return $this->belongsTo(SupportMessage::class);
    }
}