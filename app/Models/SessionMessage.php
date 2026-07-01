<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMessage extends Model
{
    protected $fillable = [
        'campaign_session_id',
        'user_id',
        'body',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CampaignSession::class, 'campaign_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
