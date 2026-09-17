<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RotateRsaKeys extends Command
{
    protected $signature   = 'auth:rotate-keys';
    protected $description = 'Genera pares de claves RSA para el slot de tiempo actual y el siguiente (cada 2h)';

    public function handle(): int
    {
        $slot = (int) floor(time() / 7200);

        if (!is_dir(storage_path('keys'))) {
            mkdir(storage_path('keys'), 0700, true);
        }

        // Se genera el slot actual Y el siguiente. Así, aunque el scheduler
        // pierda un tick o arranque tarde, cuando currentSlot() avance las
        // claves del nuevo slot YA están en disco y /auth/public-key nunca
        // devuelve 500 en el borde de cada período de 2h. getPublicKey() solo
        // mira el slot actual, no hay fallback: la pre-generación es la red.
        foreach ([$slot, $slot + 1] as $s) {
            if (file_exists(storage_path("keys/private_{$s}.pem"))) {
                $this->info("Slot {$s}: claves ya existen.");
                continue;
            }

            if (!$this->generarPar($s)) {
                return self::FAILURE;
            }

            $this->info("Claves RSA generadas para slot {$s}.");
        }

        $this->cleanOldKeys($slot);

        return self::SUCCESS;
    }

    private function generarPar(int $slot): bool
    {
        $keyPair = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$keyPair) {
            $this->error('Error al generar claves RSA: ' . openssl_error_string());
            return false;
        }

        openssl_pkey_export($keyPair, $privateKey);
        $details = openssl_pkey_get_details($keyPair);

        // 0644 en vez de 0600: quien genera la clave (root en k8s job/scheduler,
        // root en "docker compose exec" sin -u) casi nunca es el mismo usuario
        // que sirve HTTP (www-data en el php-fpm de producción, "sail" en el
        // supervisord de Sail) — con permisos solo-dueño, decryptPassword()
        // tira "Permission denied" al leer el archivo. La clave rota cada 2h
        // y se borra a los 3 slots, así que no hay nada que ganar restringiendo
        // la lectura dentro del propio contenedor.
        file_put_contents(storage_path("keys/private_{$slot}.pem"), $privateKey);
        chmod(storage_path("keys/private_{$slot}.pem"), 0644);

        file_put_contents(storage_path("keys/public_{$slot}.pem"), $details['key']);
        chmod(storage_path("keys/public_{$slot}.pem"), 0644);

        return true;
    }

    private function cleanOldKeys(int $currentSlot): void
    {
        // slot-1 sigue vivo porque decryptPassword() acepta [slot, slot-1];
        // slot+1 es el pre-generado de handle(). Todo lo demás se borra.
        $keep = [$currentSlot - 1, $currentSlot, $currentSlot + 1];

        foreach (glob(storage_path('keys/private_*.pem')) ?: [] as $file) {
            if (preg_match('/private_(\d+)\.pem$/', $file, $m) && !\in_array((int) $m[1], $keep, true)) {
                unlink($file);
                $pub = str_replace('private_', 'public_', $file);
                if (file_exists($pub)) {
                    unlink($pub);
                }
                $this->line("Limpiado slot antiguo: {$m[1]}");
            }
        }
    }
}
