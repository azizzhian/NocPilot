<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'customer_id', 'customer_name', 'location', 'customer_code',
    'phone', 'pppoe', 'package', 'area', 'reason', 'status',
    'opened_at', 'closed_at', 'scheduled_at', 'completed_at',
    'assigned_to', 'notes', 'created_by',
])]
class Dismantle extends Model
{
    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public static function generateReference(): string
    {
        $year = now()->year;
        $last = static::where('reference', 'like', "DSM-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('DSM-%d-%04d', $year, $seq);
    }
}
