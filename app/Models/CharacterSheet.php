<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSheet extends Model
{
    protected $fillable = [
        'campaign_id',
        'sheet_model_id',
        'sheet_type',
        'user_id',
        'character_name',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function sheetModel(): BelongsTo
    {
        return $this->belongsTo(SheetModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
