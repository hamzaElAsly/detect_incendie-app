<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    private const REDACT = ['password', 'password_encrypted', 'username_encrypted', 'stream_url', 'secret', 'secret_hash', 'client_secret', 'token'];

    public function record(string $action, Model|string $entity, array $old = [], array $new = [], ?Factory $factory = null, ?User $user = null, ?Request $request = null): AuditLog
    {
        return AuditLog::create(['factory_id' => $factory?->id, 'user_id' => $user?->id, 'action' => $action, 'entity_type' => is_string($entity) ? $entity : $entity::class, 'entity_id' => is_string($entity) ? null : (string) $entity->getKey(), 'old_values' => $this->redact($old), 'new_values' => $this->redact($new), 'ip_address' => $request?->ip(), 'user_agent' => $request?->userAgent(), 'created_at' => now()]);
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

return $data;
    }
}
