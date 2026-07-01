<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetModel extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'schema',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class)
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function characterSheets(): HasMany
    {
        return $this->hasMany(CharacterSheet::class);
    }
}
