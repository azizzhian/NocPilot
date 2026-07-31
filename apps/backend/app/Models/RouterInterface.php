<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterInterface extends Model
{
    protected $fillable = [
        'router_id',
        'interface_name',
        'label',
        'is_monitored',
        'is_running',
        'rx_bps',
        'tx_bps',
        'traffic_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_monitored' => 'boolean',
            'is_running' => 'boolean',
            'rx_bps' => 'integer',
            'tx_bps' => 'integer',
            'traffic_polled_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function displayName(): string
    {
        return $this->label ?: $this->interface_name;
    }
}
