<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use Illuminate\Database\Eloquent\Model;

class DailyCctvSetup extends Model
{
    use HasDailyAttribution;

    protected $fillable = [
        'report_date', 'customer_name', 'router', 'status',
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
