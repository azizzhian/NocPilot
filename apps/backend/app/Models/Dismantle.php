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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /** @return list<string> */
    public static function openStatuses(): array
    {
        return ['Pending', 'On-Progress'];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, static::openStatuses(), true);
    }

    public static function findOpenByCustomerCode(string $code, ?int $exceptId = null): ?self
    {
        $normalized = strtolower(trim($code));
        if ($normalized === '') {
            return null;
        }

        $query = static::query()
            ->whereIn('status', static::openStatuses())
            ->whereRaw('LOWER(TRIM(customer_code)) = ?', [$normalized]);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->first();
    }
}
