<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $fillable = [
        'parent_user_id',
        'parent_name',
        'parent_email',
        'subject',
        'message',
        'status',
    ];

    public function replies()
    {
        return $this->hasMany(SupportMessageReply::class)->oldest();
    }
}