<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    protected $fillable = [
        'consultation_id',
        'content',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }
}
