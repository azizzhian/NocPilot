<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTicket extends Model
{
    protected $fillable = [
        'source',
        'complaint_id',
        'customer_id',
        'location',
        'odc_name',
        'customer_code',
        'customer_name',
        'customer_phone',
        'job_type',
        'priority',
        'visit_schedule',
        'source_complaint_number',
        'source_snapshot',
        'problem',
        'action',
        'status',
        'opened_at',
        'closed_at',
        'notes',
        'escalation_reason',
        'escalated_at',
        'assigned_to',
        'assigned_at',
        'work_started_at',
        'work_completed_at',
        'created_by',
        'cleared_by',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'cleared_at' => 'datetime',
            'visit_schedule' => 'datetime',
            'escalated_at' => 'datetime',
            'assigned_at' => 'datetime',
            'work_started_at' => 'datetime',
            'work_completed_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(DailyComplaint::class, 'complaint_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ReportTicketUpdate::class)->latest();
    }

    public function isFromComplaint(): bool
    {
        return $this->source === 'complaint' || filled($this->complaint_id);
    }
}
