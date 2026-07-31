<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'customer_id', 'customer_name', 'phone', 'package',
    'area', 'odp', 'address', 'status', 'scheduled_at', 'completed_at',
    'assigned_to', 'notes', 'created_by',
])]
class Activation extends Model
{
    protected function casts(): array
    {
        return [
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
        $last = static::where('reference', 'like', "AKT-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('AKT-%d-%04d', $year, $seq);
    }
}
