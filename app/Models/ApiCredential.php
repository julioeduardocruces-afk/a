<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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

    public function getDecryptedCredentials(): array
    {
        try {
            return json_decode(Crypt::decryptString($this->encrypted_json), true);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            throw new \RuntimeException(
                "No se pudo descifrar la credencial '{$this->name}' (ID:{$this->id}). "
                . "Esto ocurre si el APP_KEY cambió después de guardar las credenciales. "
                . "Ve a Admin > Credenciales y vuelve a guardar la API key."
            );
        }
    }

    public function setCredentials(array $data): void
    {
        $this->encrypted_json = Crypt::encryptString(json_encode($data));
    }

    public function recordUsage(): void
    {
        // Single query instead of two: increment() + update() each hit the DB.
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
