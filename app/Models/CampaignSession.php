<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignSession extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_map_id',
        'name',
        'status',
        'state',
        'notes',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(CampaignMap::class, 'campaign_map_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SessionMessage::class);
    }
}
