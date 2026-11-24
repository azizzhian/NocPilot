<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTicketUpdate extends Model
{
    protected $fillable = [
        'report_ticket_id',
        'created_by',
        'type',
        'check_result',
        'repair_action',
        'material',
        'photo',
        'work_started_at',
        'work_ended_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_started_at' => 'datetime',
            'work_ended_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ReportTicket::class, 'report_ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
