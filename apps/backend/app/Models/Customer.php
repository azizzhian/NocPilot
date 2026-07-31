<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_code',
    'name',
    'pppoe',
    'phone',
    'email',
    'package',
    'status',
    'area',
    'address',
    'odc_id',
    'odp',
    'olt',
    'onu',
    'pon_port',
    'rx_power',
    'tx_power',
    'latitude',
    'longitude',
    'activated_at',
    'imported_at',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'rx_power' => 'decimal:2',
            'tx_power' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'activated_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class);
    }

    public function displayName(): string
    {
        if ($this->odc?->name) {
            return "{$this->name} ({$this->odc->name})";
        }

        if ($this->address) {
            return "{$this->name} ({$this->address})";
        }

        return $this->name;
    }
}
