<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pop extends Model
{
    protected $fillable = ['name', 'code', 'area', 'address', 'status', 'capacity', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $pop) {
            if ($pop->capacity === null) {
                $pop->capacity = 0;
            }
        });
    }

    public function odcs(): HasMany
    {
        return $this->hasMany(Odc::class);
    }

    public function olts(): HasMany
    {
        return $this->hasMany(Olt::class);
    }
}
