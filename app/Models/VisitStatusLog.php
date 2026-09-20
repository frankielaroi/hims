<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'visit_id',
        'status',
        'changed_by',
    ];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
