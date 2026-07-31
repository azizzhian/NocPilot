<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use Illuminate\Database\Eloquent\Model;

class DailyDismantle extends Model
{
    use HasDailyAttribution;

    protected $fillable = [
        'report_date', 'customer_name', 'customer_code', 'site_name', 'start_ticket', 'close_ticket', 'status',
        'created_by', 'cleared_by', 'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'start_ticket' => 'date',
            'close_ticket' => 'date',
            'cleared_at' => 'datetime',
        ];
    }
}
