<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiCredential extends Model
{
    protected $fillable = [
        'provider',
        'name',
        'encrypted_json',
        'is_active',
        'usage_count',
        'last_used_at',
    ];

    protected $hidden = ['encrypted_json'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get credentials stored as plain JSON in encrypted_json column.
     * Column name kept for backward compatibility.
     */
    public function getDecryptedCredentials(): array
    {
        $raw = $this->encrypted_json;

        if (empty($raw)) {
            return [];
        }

        // Try plain JSON first (new format)
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Fallback: try decrypting legacy encrypted data
        try {
            $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($raw);
            $data = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Auto-migrate: save as plain JSON so it won't break again
                $this->timestamps = false;
                $this->update(['encrypted_json' => $decrypted]);
                $this->timestamps = true;
                return $data;
            }
        } catch (\Exception $e) {
            // Can't decrypt either — data is corrupted
        }

        throw new \RuntimeException(
            "No se pudo leer la credencial '{$this->name}' (ID:{$this->id}). "
            . "Los datos están corruptos. Ve a Admin > Credenciales y vuelve a guardarla."
        );
    }

    /**
     * Store credentials as plain JSON.
     */
    public function setCredentials(array $data): void
    {
        $this->encrypted_json = json_encode($data);
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count', 1, ['last_used_at' => now()]);
    }

    /**
     * Get the next active credential for a provider using round-robin rotation.
     */
    public static function getNextForProvider(string $provider): ?self
    {
        return static::where('provider', $provider)
            ->where('is_active', true)
            ->orderBy('usage_count', 'asc')
            ->orderBy('last_used_at', 'asc')
            ->first();
    }
}
