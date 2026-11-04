<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'token_hash',
        'share_token',
        'remember_token',
        'secret',
        'api_key',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $request = request();
        $authenticated = $request->user();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey() ?? ($authenticated instanceof User ? $authenticated->getKey() : null),
            'event' => $event,
            'auditable_type' => $auditable === null ? null : $auditable::class,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->emptyAsNull($this->redact($oldValues)),
            'new_values' => $this->emptyAsNull($this->redact($newValues)),
            'ip_address' => $request->ip(),
            'user_agent' => $this->limited($request->userAgent()),
            'url' => $this->limited($this->redactUrl($request->fullUrl()), 2048),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = Str::lower($key);

        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token');
    }

    /**
     * Share links carry a bearer token in the path. The audit row keeps the
     * route and drops the secret.
     */
    private function redactUrl(string $url): string
    {
        $redacted = preg_replace('~(/q/)[^/?#\s]+~', '$1[redacted]', $url) ?? $url;

        return preg_replace('/([?&](?:token|share_token)=)[^&#\s]+/i', '$1[redacted]', $redacted) ?? $redacted;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>|null
     */
    private function emptyAsNull(array $values): ?array
    {
        return $values === [] ? null : $values;
    }

    private function limited(?string $value, int $limit = 512): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
