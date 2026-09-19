<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $fillable = ['name', 'odc_id', 'status', 'notes'];

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class);
    }
}
