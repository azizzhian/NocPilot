<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportSnapshot extends Model
{
    protected $fillable = [
        'report_date', 'generated_by', 'responsible_name', 'activity_name',
        'daily_report_text', 'noc_update_text', 'monitoring_report_text',
    ];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
