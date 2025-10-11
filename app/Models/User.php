<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    // Relationships
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    // Helper methods for social accounts
    public function getSocialAccount(string $platform): ?SocialAccount
    {
        return $this->socialAccounts()
            ->where('platform_name', $platform)
            ->where('is_active', true)
            ->first();
    }

    public function hasConnectedPlatform(string $platform): bool
    {
        return $this->getSocialAccount($platform) !== null;
    }

    public function getConnectedPlatforms(): array
    {
        return $this->socialAccounts()
            ->where('is_active', true)
            ->pluck('platform_name')
            ->toArray();
    }

    public function getActiveConnections()
    {
        return $this->socialAccounts()
            ->where('is_active', true)
            ->get();
    }
}
