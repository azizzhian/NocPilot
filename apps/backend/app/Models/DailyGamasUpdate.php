<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyGamasUpdate extends Model
{
    protected $fillable = [
        'daily_gamas_id',
        'update_text',
        'started_at',
        'ended_at',
        'duration_minutes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<DailyGamas, $this> */
    public function gamas(): BelongsTo
    {
        return $this->belongsTo(DailyGamas::class, 'daily_gamas_id');
    }
}
