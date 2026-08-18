<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppUpdate extends Model
{
    protected $fillable = [
        'from_commit',
        'to_commit',
        'branch',
        'changes',
        'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'deployed_at' => 'datetime',
        ];
    }
}
