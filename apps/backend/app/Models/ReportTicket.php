<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTicket extends Model
{
    protected $fillable = [
        'location',
        'customer_code',
        'customer_name',
        'problem',
        'action',
        'status',
        'opened_at',
        'closed_at',
        'notes',
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
}
