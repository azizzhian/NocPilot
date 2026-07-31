<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use Illuminate\Database\Eloquent\Model;

class DailyActivation extends Model
{
    use HasDailyAttribution;

    protected $fillable = [
        'report_date', 'customer_name', 'package_name', 'olt_name', 'odp_name', 'port_onu', 'status', 'notes',
        'created_by', 'cleared_by', 'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'cleared_at' => 'datetime',
        ];
    }
}
