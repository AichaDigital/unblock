<?php

namespace App\Console\Commands\Develop;

use App\Models\Host;
use Illuminate\Console\Command;

use function Laravel\Prompts\{error, info, select, table, warning};

class TestHostConnectionCommand extends Command
{
    protected $signature = 'develop:test-host-connection {--host-id= : ID específico del host a probar}';

    protected $description = 'Diagnóstico SSH real usando el sistema de la aplicación';

    public function handle(): int
    {
        info('=== DIAGNÓSTICO SSH REAL ===');

        $host = $this->selectHost();
        if (! $host) {
            error('Host no encontrado');

            return 1;
        }

        $this->showHostInfo($host);
        $keyFile = $this->diagnoseKey($host);
        $this->testConnection($host, $keyFile);
        $this->showDebugInfo($keyFile);

        return 0;
    }

    private function selectHost(): ?Host
    {
        if ($hostId = $this->option('host-id')) {
            return Host::find($hostId);
        }

        $hosts = Host::whereNull('deleted_at')->orderBy('fqdn')->get();

        if ($hosts->isEmpty()) {
            error('No hay hosts disponibles');

            return null;
        }

        $options = $hosts->mapWithKeys(fn ($host) => [
            $host->id => "{$host->fqdn}:{$host->port_ssh} ({$host->panel})",
        ])->toArray();

        $selectedId = select('Selecciona el host:', $options);

        return $hosts->find($selectedId);
    }

    private function showHostInfo(Host $host): void
    {
        table(['Campo', 'Valor'], [
            ['FQDN', $host->fqdn],
            ['Puerto', $host->port_ssh ?? 22],
            ['Panel', $host->panel],
            ['Hash Length', strlen($host->hash).' chars'],
        ]);
    }

    private function diagnoseKey(Host $host): string
    {
        info('=== DIAGNÓSTICO DE CLAVE SSH ===');

        $privateKey = $host->hash; // Getter que devuelve la clave privada

        // Verificar formato de clave
        if (! str_starts_with($privateKey, '-----BEGIN')) {
            error('❌ Clave SSH no tiene formato PEM válido');

            return '';
        }

        info('✅ Formato PEM detectado');

        // Detectar tipo de clave
        if (str_contains($privateKey, 'BEGIN OPENSSH PRIVATE KEY')) {
            info('🔑 Tipo: OpenSSH (ED25519/ECDSA)');
            $keyType = 'openssh';
        } elseif (str_contains($privateKey, 'BEGIN RSA PRIVATE KEY')) {
            info('🔑 Tipo: RSA tradicional');
            $keyType = 'rsa';
        } elseif (str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
            info('🔑 Tipo: PKCS#8');
            $keyType = 'pkcs8';
        } else {
            warning('⚠️  Tipo de clave no reconocido');
            $keyType = 'unknown';
        }

        // Crear archivo temporal usando Laravel Storage
        $keyFileName = 'ssh_debug_'.uniqid().'.key';
        $keyFile = storage_path('app/temp/'.$keyFileName);

        // Asegurar que el directorio temp existe
        if (! is_dir(dirname($keyFile))) {
            mkdir(dirname($keyFile), 0755, true);
        }

        // Normalizar line endings y asegurar newline final
        $normalizedKey = str_replace(["\r\n", "\r"], "\n", $privateKey);
        if (! str_ends_with($normalizedKey, "\n")) {
            $normalizedKey .= "\n";
        }

        if (file_put_contents($keyFile, $normalizedKey) === false) {
            error('❌ No se pudo escribir clave en archivo temporal');

            return '';
        }

        chmod($keyFile, 0600);

        // Verificar que el archivo existe y tiene contenido
        if (! file_exists($keyFile) || filesize($keyFile) === 0) {
            error('❌ Archivo temporal no creado correctamente');

            return '';
        }

        info('📁 Archivo temporal creado');
        info('📏 Tamaño del archivo: '.filesize($keyFile).' bytes');

        // Verificar line endings
        $content = file_get_contents($keyFile);
        $hasWindows = str_contains($content, "\r\n");
        $hasMac = str_contains($content, "\r") && ! str_contains($content, "\r\n");
        $endsWithNewline = str_ends_with($content, "\n");

        info('🔍 Line endings: '.($hasWindows ? 'Windows (CRLF)' : ($hasMac ? 'Mac (CR)' : 'Unix (LF)')));
        info('🔚 Termina con newline: '.($endsWithNewline ? '✅ Sí' : '❌ No'));

        // Verificar con ssh-keygen (funciona para todos los tipos)
        $output = [];
        $returnCode = 0;
        exec('ssh-keygen -l -f '.escapeshellarg($keyFile).' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            info('✅ Clave SSH válida según ssh-keygen');
            $this->line('Fingerprint: '.implode(' ', $output));
        } else {
            error('❌ Clave SSH inválida según ssh-keygen');
            $this->line('Error de validación detectado');
        }

        // Solo verificar con OpenSSL si es RSA tradicional
        if ($keyType === 'rsa') {
            $output = [];
            $returnCode = 0;
            exec('openssl rsa -in '.escapeshellarg($keyFile).' -check -noout 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                info('✅ Clave RSA válida según OpenSSL');
            } else {
                error('❌ Clave RSA inválida según OpenSSL');
                $this->line('Error de validación OpenSSL detectado');
            }
        } else {
            info('ℹ️  Verificación OpenSSL omitida (no es clave RSA tradicional)');
        }

        // NO borrar archivo para debug
        return $keyFile;
    }

    private function testConnection(Host $host, string $existingKeyFile = ''): void
    {
        info('=== PRUEBA DE CONEXIÓN SSH ===');

        // Usar archivo existente si se proporciona, sino crear uno nuevo
        if ($existingKeyFile && file_exists($existingKeyFile)) {
            $keyFile = $existingKeyFile;
            info("🔄 Reutilizando archivo de clave: {$keyFile}");
        } else {
            $privateKey = $host->hash;
            $keyFileName = 'ssh_conn_'.uniqid().'.key';
            $keyFile = storage_path('app/temp/'.$keyFileName);

            // Asegurar que el directorio temp existe
            if (! is_dir(dirname($keyFile))) {
                mkdir(dirname($keyFile), 0755, true);
            }

            // Normalizar line endings y asegurar newline final
            $normalizedKey = str_replace(["\r\n", "\r"], "\n", $privateKey);
            if (! str_ends_with($normalizedKey, "\n")) {
                $normalizedKey .= "\n";
            }

            if (file_put_contents($keyFile, $normalizedKey) === false) {
                error('❌ No se pudo crear archivo de clave temporal');

                return;
            }

            chmod($keyFile, 0600);
            info("📁 Nuevo archivo de clave: {$keyFile}");
        }

        $sshCmd = sprintf(
            'ssh -i %s -p %d -o StrictHostKeyChecking=no -o ConnectTimeout=10 root@%s whoami 2>&1',
            escapeshellarg($keyFile),
            $host->port_ssh ?? 22,
            escapeshellarg($host->fqdn)
        );

        info("Ejecutando: ssh -i [key] -p {$host->port_ssh} root@{$host->fqdn} whoami");

        $output = [];
        $returnCode = 0;
        exec($sshCmd, $output, $returnCode);

        $result = implode("\n", $output);

        if ($returnCode === 0 && trim($result) === 'root') {
            info('✅ CONEXIÓN SSH EXITOSA');
            $this->line('Usuario remoto: '.trim($result));
        } else {
            error('❌ CONEXIÓN SSH FALLÓ');
            $this->line('Código de salida: '.$returnCode);
            $this->line('Output completo:');
            $this->line($result);

            // Análisis específico de errores
            if (str_contains($result, 'Permission denied (publickey)')) {
                warning('🔑 Error de autenticación - clave SSH rechazada');
            }
            if (str_contains($result, 'error in libcrypto')) {
                warning('🔧 Error de libcrypto - clave corrupta o formato incorrecto');
            }
            if (str_contains($result, 'Connection refused')) {
                warning('🌐 Conexión rechazada - puerto o host incorrectos');
            }
        }

        // NO borrar archivo para debug
    }

    private function showDebugInfo(string $keyFile): void
    {
        info('=== INFORMACIÓN DE DEBUG ===');

        if (! $keyFile || ! file_exists($keyFile)) {
            warning('⚠️  No hay archivo de clave para debug');

            return;
        }

        info('📁 Archivo de clave mantenido para debug manual');
        info('📏 Tamaño: '.filesize($keyFile).' bytes');
        info('🔐 Permisos: '.substr(sprintf('%o', fileperms($keyFile)), -4));

        // REMOVED: No mostrar contenido de llaves privadas
        info('ℹ️  Contenido de clave SSH no mostrado por seguridad');
        info('🔧 Para debug manual: cat '.escapeshellarg($keyFile));
    }
}
