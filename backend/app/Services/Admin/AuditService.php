<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Sensitive keys that must NEVER be written to audit log metadata.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'mfa_secret',
        'otp',
        'pin',
        'token',
        'qr_token',
        'authorization',
        'cookie',
    ];

    /**
     * Record an audit log entry.
     *
     * @param string $action Action performed (e.g. 'admin.login', 'event.published')
     * @param Model|null $target Target model entity affected
     * @param array<string, mixed>|null $metadata Contextual data (will be sanitized)
     * @param Admin|null $actor The administrator performing the action
     * @param Request|null $request Current HTTP request for IP and User-Agent
     */
    public function log(
        string $action,
        ?Model $target = null,
        ?array $metadata = null,
        ?Admin $actor = null,
        ?Request $request = null
    ): AuditLog {
        $actor = $actor ?? (auth()->user() instanceof Admin ? auth()->user() : null);
        $request = $request ?? request();

        $sanitizedMetadata = $metadata ? $this->sanitizeMetadata($metadata) : null;

        return AuditLog::create([
            'id' => (string) Str::uuid(),
            'admin_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 500) : null,
            'metadata' => $sanitizedMetadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively scrub sensitive keys from metadata.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            foreach (self::REDACTED_KEYS as $redacted) {
                if (str_contains($lowerKey, $redacted)) {
                    $value = '[REDACTED]';
                    break;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}

