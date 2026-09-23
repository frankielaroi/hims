<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'status',
        'requires_password_change',
        'login_attempts',
        'contact_info',
        'department_id',
        'licence_body',
        'licence_number',
        'licence_expires_on',
    ];

    /**
     * The regulators whose licence numbers HEFRA inspects.
     *
     * @var array<string, string>
     */
    public const LICENCE_BODIES = [
        'mdc' => 'Medical and Dental Council',
        'nmc' => 'Nursing and Midwifery Council',
        'ahpc' => 'Allied Health Professions Council',
        'pharmacy_council' => 'Pharmacy Council',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'requires_password_change' => 'boolean',
            'licence_expires_on' => 'date',
        ];
    }

    /**
     * Whether this member of staff holds a licence that has run out.
     *
     * A missing expiry is not treated as expired: plenty of records will have
     * no date until the register is filled in, and locking those people out
     * would be worse than the gap it closes.
     */
    public function licenceHasExpired(): bool
    {
        return $this->licence_expires_on !== null && $this->licence_expires_on->isPast();
    }

    public function licenceBodyName(): ?string
    {
        return $this->licence_body === null
            ? null
            : (self::LICENCE_BODIES[$this->licence_body] ?? $this->licence_body);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('slug', $permission);
        })->exists();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
