<?php

namespace App\Models;

use App\Models\Concerns\HasDailyAttribution;
use App\Support\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyComplaint extends Model
{
    use HasDailyAttribution;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_GAMAS = 'gamas';

    public const GAMAS_KINDS = ['odp', 'upstream', 'olt', 'other'];

    /** Jendela deteksi komplain berulang (jam) sejak komplain sebelumnya. */
    public const REPEAT_WINDOW_HOURS = 24;

    /** Timer auto-clear status Menunggu Konfirmasi (menit). */
    public const WAITING_CONFIRMATION_MINUTES = 30;

    /** Umur minimum komplain (detik) sebelum boleh di-Clear secara manual. */
    public const MIN_CLEAR_AGE_SECONDS = 300;

    /** Kategori performa penanganan (durasi sejak input sampai clear). */
    public const PERFORMANCE_CATEGORIES = ['Cepat', 'Lambat', 'Cukup Lambat', 'Sangat Lambat'];

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
        'parent_complaint_id',
        'complaint_group_id',
        'ticket_id',
        'escalated_at',
        'handled_at',
        'handled_by',
        'auto_cleared',
        'handling_minutes',
        'performance_category',
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
            'handled_at' => 'datetime',
            'escalated_at' => 'datetime',
            'auto_cleared' => 'boolean',
            'handling_minutes' => 'integer',
            'shift' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parentComplaint(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_complaint_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ReportTicket::class, 'ticket_id');
    }

    public function activeTicket(): ?ReportTicket
    {
        return $this->tickets()
            ->whereIn('status', \App\Support\TicketStatus::activeStatuses())
            ->latest('id')
            ->first();
    }

    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReportTicket::class, 'complaint_id');
    }

    public function hasActiveTicket(): bool
    {
        return $this->tickets()
            ->whereIn('status', \App\Support\TicketStatus::activeStatuses())
            ->exists();
    }

    public function complaintNumber(): string
    {
        $date = $this->report_date?->format('Ymd') ?: $this->created_at?->format('Ymd') ?: '00000000';

        return 'KM-'.$date.'-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && (int) ($this->created_by ?? 0) === $userId;
    }

    public function isWaitingConfirmation(): bool
    {
        return $this->status === ReportStatus::WAITING_CONFIRMATION;
    }

    /** Sisa waktu auto-clear (menit) untuk status Menunggu Konfirmasi, 0 jika sudah lewat. */
    public function waitingMinutesLeft(): int
    {
        if (! $this->isWaitingConfirmation() || ! $this->handled_at) {
            return 0;
        }

        $due = $this->handled_at->copy()->addMinutes(self::WAITING_CONFIRMATION_MINUTES);

        return (int) max(0, intdiv($due->getTimestamp() - now()->getTimestamp(), 60));
    }

    /**
     * Sinkronkan field penanganan (handled_at/handled_by, durasi, kategori) mengikuti transisi status.
     * Dipanggil berdampingan dengan syncClearAttribution().
     */
    public function syncHandling(string $newStatus, ?int $userId, ?string $previousStatus = null): void
    {
        $leavingClear = $newStatus !== ReportStatus::CLEAR
            && ($previousStatus === ReportStatus::CLEAR || $this->handling_minutes !== null);

        if ($newStatus === ReportStatus::CLEAR) {
            $end = $this->cleared_at ?? now();
            $start = $this->created_at ?? now();
            $minutes = (int) max(0, $start->diffInMinutes($end));
            $this->handling_minutes = $minutes;
            $this->performance_category = self::performanceCategoryFor($minutes);
            if ($previousStatus !== ReportStatus::CLEAR) {
                $this->auto_cleared = false;
            }

            return;
        }

        if ($leavingClear) {
            // Komplain dibuka kembali → metrik performa di-reset.
            $this->handling_minutes = null;
            $this->performance_category = null;
            $this->auto_cleared = false;
        }

        if ($newStatus === ReportStatus::WAITING_CONFIRMATION) {
            if ($previousStatus !== ReportStatus::WAITING_CONFIRMATION) {
                $this->handled_at = now();
                $this->handled_by = $userId ?? $this->handled_by;
            }

            return;
        }

        if ($previousStatus === ReportStatus::WAITING_CONFIRMATION && $newStatus === ReportStatus::ON_PROGRESS) {
            // NOC membatalkan kembali ke On-Progress → reset timer penanganan.
            $this->handled_at = null;
            $this->handled_by = null;
            $this->auto_cleared = false;
        }
    }

    public static function performanceCategoryFor(int $minutes): string
    {
        return match (true) {
            $minutes <= 30 => 'Cepat',
            $minutes <= 40 => 'Lambat',
            $minutes <= 60 => 'Cukup Lambat',
            default => 'Sangat Lambat',
        };
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
