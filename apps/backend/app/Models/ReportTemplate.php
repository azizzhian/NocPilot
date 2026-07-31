<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    public const TYPE_DAILY = 'daily';

    public const TYPE_NOC = 'noc';

    public const TYPE_MONITORING = 'monitoring';

    protected $fillable = ['type', 'body'];
}
