<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'master_id',
        'title',
        'slug',
        'description',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function sheetModels(): BelongsToMany
    {
        return $this->belongsToMany(SheetModel::class)
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function characterSheets(): HasMany
    {
        return $this->hasMany(CharacterSheet::class);
    }

    public function maps(): HasMany
    {
        return $this->hasMany(CampaignMap::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CampaignSession::class);
    }
}
