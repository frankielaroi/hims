<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RadiologyAttachment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const KINDS = ['image', 'report'];

    protected $fillable = [
        'radiology_order_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'kind',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function radiologyOrder(): BelongsTo
    {
        return $this->belongsTo(RadiologyOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Remove the file from storage as well as the record. Attachments are
     * patient-identifiable, so an orphaned file is a disclosure risk.
     */
    public function deleteWithFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
        $this->delete();
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
