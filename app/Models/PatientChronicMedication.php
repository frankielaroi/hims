<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientChronicMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'drug_id',
        'drug_name',
        'strength',
        'dose',
        'route',
        'frequency',
        'is_active',
        'recorded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
