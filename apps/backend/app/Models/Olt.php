<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    protected $fillable = ['pop_id', 'name', 'ip', 'status', 'capacity', 'pon_ports', 'notes'];

    protected static function booted(): void
    {
        static::saving(function (self $olt) {
            if ($olt->capacity === null) {
                $olt->capacity = 128;
            }
            if ($olt->pon_ports === null) {
                $olt->pon_ports = 8;
            }
        });
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }
}
