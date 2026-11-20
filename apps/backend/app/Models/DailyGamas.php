<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use App\Support\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyGamas extends Model
{
    use HasDailyAttribution;

    public const KINDS = ['odp', 'upstream', 'olt', 'other'];

    /** @var array<string, string> */
    public const KIND_LABELS = [
        'odp' => 'ODP/Jalur',
        'upstream' => 'Upstream',
        'olt' => 'OLT/Site',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'report_date',
        'gamas_kind',
        'location_label',
        'impact',
        'odc_name',
        'problem',
        'action',
        'start_problem',
        'end_problem',
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

    public static function kindLabel(?string $kind): string
    {
        return self::KIND_LABELS[$kind] ?? 'Gamas';
    }

    public function displayLabel(): string
    {
        $kind = self::kindLabel($this->gamas_kind);
        $location = trim((string) ($this->location_label ?: 'Tanpa lokasi'));
        $impact = trim((string) ($this->impact ?: ''));

        $label = "{$kind}: {$location}";
        if ($impact !== '') {
            $label .= " ({$impact})";
        }

        return $label;
    }

    public function isOpen(): bool
    {
        return ReportStatus::isOpen($this->status);
    }

    /** @return HasMany<DailyGamasUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(DailyGamasUpdate::class, 'daily_gamas_id')->latest();
    }
}
