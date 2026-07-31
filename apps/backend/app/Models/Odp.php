<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odp extends Model
{
    protected $fillable = ['odc_id', 'name', 'code', 'status', 'capacity', 'used_ports', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $odp) {
            if ($odp->capacity === null) {
                $odp->capacity = 16;
            }
            if ($odp->used_ports === null) {
                $odp->used_ports = 0;
            }
        });
    }

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }
}
