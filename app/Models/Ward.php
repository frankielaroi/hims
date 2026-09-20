<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    protected $fillable = [
        'name',
        'category',
        'floor',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }
}
