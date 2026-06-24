<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_church_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'current_church_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'current_church_id' => 'integer',
        ];
    }

    /**
     * The active church the user is currently working in.
     */
    public function currentChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'current_church_id');
    }

    /**
     * All churches this user belongs to.
     */
    public function churches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class)
            ->using(ChurchUser::class)
            ->withPivot('role', 'modules')
            ->withTimestamps();
    }

    /**
     * Get the pivot member record for the active church.
     */
    public function currentChurchMember(): ?ChurchUser
    {
        if (! $this->current_church_id) {
            return null;
        }

        /** @var ChurchUser|null */
        return $this->churches()
            ->where('churches.id', $this->current_church_id)
            ->first()?->pivot;
    }

    /**
     * Check if user has a role in the active church.
     */
    public function hasChurchRole(string|array $roles): bool
    {
        $member = $this->currentChurchMember();
        if (! $member) {
            return false;
        }

        if (is_array($roles)) {
            return in_array($member->role, $roles);
        }

        return $member->role === $roles;
    }

    /**
     * Check if user has access to a specific module in the active church.
     */
    public function hasModuleAccess(string $module): bool
    {
        $member = $this->currentChurchMember();
        if (! $member) {
            return false;
        }

        // Admins and Managers have access to all modules automatically
        if (in_array($member->role, ['Admin', 'Manager'])) {
            return true;
        }

        // Users are restricted to modules explicitly granted
        $allowedModules = $member->modules ?: [];
        return in_array($module, $allowedModules);
    }
}
