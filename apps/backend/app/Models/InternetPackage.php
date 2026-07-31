<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternetPackage extends Model
{
    protected $fillable = ['name', 'speed_mbps', 'price', 'status', 'description'];
}
