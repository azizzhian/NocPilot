<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odc extends Model
{
    protected $fillable = ['pop_id', 'name', 'code', 'status', 'capacity', 'location', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $odc) {
            if ($odc->capacity === null) {
                $odc->capacity = 0;
            }
        });
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }
}
