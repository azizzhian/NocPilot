<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use Illuminate\Database\Eloquent\Model;

class DailyNocUpdate extends Model
{
    use HasDailyAttribution;

    protected $fillable = [
        'report_date', 'description', 'status', 'sort_order',
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
