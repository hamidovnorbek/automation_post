<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform_name',
        'provider_id',
        'provider_name',
        'access_token',
        'refresh_token',
        'expires_at',
        'account_username',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors & Mutators for secure token handling
    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $minutes = 60): bool
    {
        return $this->expires_at && $this->expires_at->diffInMinutes(now()) <= $minutes;
    }

    public function getPlatformLabel(): string
    {
        return match($this->platform_name) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'telegram' => 'Telegram',
            'youtube' => 'YouTube',
            default => ucfirst($this->platform_name),
        };
    }

    public function getStatusColor(): string
    {
        if (!$this->is_active) return 'danger';
        if ($this->isExpired()) return 'danger';
        if ($this->isExpiringSoon()) return 'warning';
        return 'success';
    }

    public function getStatusLabel(): string
    {
        if (!$this->is_active) return 'Inactive';
        if ($this->isExpired()) return 'Expired';
        if ($this->isExpiringSoon()) return 'Expiring Soon';
        return 'Connected';
    }
}
