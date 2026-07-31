<?php

namespace App\Models;

use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'ticket_number', 'subject', 'description', 'customer_id',
    'customer_name', 'customer_phone', 'priority', 'status',
    'assigned_to', 'area', 'latitude', 'longitude',
    'sla_deadline', 'assigned_at', 'solved_at', 'closed_at',
    'internal_note', 'created_by',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sla_deadline' => 'datetime',
            'assigned_at' => 'datetime',
            'solved_at' => 'datetime',
            'closed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->latest();
    }

    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = static::withTrashed()
            ->where('ticket_number', 'like', "TK-{$year}-%")
            ->orderByDesc('id')
            ->value('ticket_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('TK-%d-%04d', $year, $seq);
    }

    public function slaRemainingMinutes(): ?int
    {
        if (! $this->sla_deadline || in_array($this->status, ['solved', 'closed'])) {
            return null;
        }

        return (int) now()->diffInMinutes($this->sla_deadline, false);
    }
}
