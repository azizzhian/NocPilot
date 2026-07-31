<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onu extends Model
{
    protected $fillable = [
        'odp_id', 'olt_id', 'customer_id', 'serial', 'name',
        'status', 'rx_power', 'tx_power', 'pon_port', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rx_power' => 'float',
            'tx_power' => 'float',
        ];
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
