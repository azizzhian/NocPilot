<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyComplaint extends Model
{
    use HasDailyAttribution;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_GAMAS = 'gamas';

    public const GAMAS_KINDS = ['odp', 'upstream', 'olt', 'other'];

    protected $fillable = [
        'report_date',
        'complaint_type',
        'customer_id',
        'customer_code',
        'gamas_kind',
        'location_label',
        'impact',
        'odc_name',
        'customer_name',
        'phone_normalized',
        'start_problem',
        'end_problem',
        'problem',
        'action',
        'status',
        'shift',
        'created_by',
        'cleared_by',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'start_problem' => 'date',
            'end_problem' => 'date',
            'cleared_at' => 'datetime',
            'shift' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isGamas(): bool
    {
        return $this->complaint_type === self::TYPE_GAMAS;
    }

    public function displayLabel(): string
    {
        if (! $this->isGamas()) {
            return (string) $this->customer_name;
        }

        $kindLabels = [
            'odp' => 'ODP/Jalur',
            'upstream' => 'Upstream',
            'olt' => 'OLT/Site',
            'other' => 'Gamas',
        ];
        $kind = $kindLabels[$this->gamas_kind] ?? 'Gamas';
        $location = trim((string) ($this->location_label ?: $this->customer_name));
        $impact = trim((string) ($this->impact ?: ''));

        $label = "{$kind}: {$location}";
        if ($impact !== '') {
            $label .= " ({$impact})";
        }

        return $label;
    }
}
