<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
    'url',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->event) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'login_failed' => 'Failed sign-in',
            'login_inactive' => 'Inactive account sign-in attempt',
            'login_lockout' => 'Sign-in locked',
            'user_created' => 'User created',
            'user_updated' => 'User updated',
            'password_reset' => 'Password reset',
            'role_permissions_updated' => 'Role permissions updated',
            default => str_replace('_', ' ', (string) $this->event),
        };
    }
}
