<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\{AdminConnectionErrorMail, UserSystemErrorMail};
use App\Models\{Host, User};
use Exception;
use Illuminate\Support\Facades\{Log, Mail};

class FirewallConnectionErrorService
{
    /**
     * Maneja errores de conexión SSH y envía notificaciones apropiadas
     *
     * @param  string  $ip  IP que se estaba verificando
     * @param  Host  $host  Servidor donde falló la conexión
     * @param  User  $user  Usuario que solicitó la verificación
     * @param  string  $errorMessage  Mensaje de error detallado
     * @param  Exception  $exception  Excepción original
     */
    public function handleConnectionError(
        string $ip,
        Host $host,
        User $user,
        string $errorMessage,
        Exception $exception
    ): void {
        // 1. Registrar error detallado en logs
        $this->logConnectionError($ip, $host, $user, $errorMessage, $exception);

        // 2. Enviar notificación al administrador
        $this->notifyAdministrator($ip, $host, $user, $errorMessage, $exception);

        // 3. Enviar notificación al usuario
        $this->notifyUser($ip, $host, $user);
    }

    /**
     * Registra el error de conexión en los logs del sistema
     */
    private function logConnectionError(
        string $ip,
        Host $host,
        User $user,
        string $errorMessage,
        Exception $exception
    ): void {
        Log::error('SSH Connection Error - Firewall Check Failed', [
            'ip' => $ip,
            'host_id' => $host->id,
            'host_fqdn' => $host->fqdn,
            'host_port' => $host->port_ssh ?? 22,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'error_message' => $errorMessage,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Envía notificación al administrador sobre el error de conexión
     */
    private function notifyAdministrator(
        string $ip,
        Host $host,
        User $user,
        string $errorMessage,
        Exception $exception
    ): void {
        try {
            // Obtener email del administrador desde configuración
            $adminEmail = config('mail.admin_email', 'admin@example.com');

            Mail::to($adminEmail)->send(new AdminConnectionErrorMail(
                $ip,
                $host,
                $user,
                $errorMessage,
                $exception
            ));

            Log::info('Admin notification sent for SSH connection error', [
                'admin_email' => $adminEmail,
                'host_fqdn' => $host->fqdn,
                'user_email' => $user->email,
            ]);

        } catch (Exception $mailException) {
            Log::error('Failed to send admin notification for SSH error', [
                'original_error' => $errorMessage,
                'mail_error' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * Envía notificación al usuario sobre el error del sistema
     */
    private function notifyUser(string $ip, Host $host, User $user): void
    {
        try {
            Mail::to($user->email)->send(new UserSystemErrorMail(
                $ip,
                $host,
                $user
            ));

            Log::info('User notification sent for system error', [
                'user_email' => $user->email,
                'host_fqdn' => $host->fqdn,
                'ip' => $ip,
            ]);

        } catch (Exception $mailException) {
            Log::error('Failed to send user notification for system error', [
                'user_email' => $user->email,
                'mail_error' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * Determina si un error de conexión es crítico y requiere notificación inmediata
     */
    public function isCriticalError(Exception $exception): bool
    {
        $criticalPatterns = [
            'proc_open',
            'error in libcrypto',
            'Permission denied (publickey)',
            'Connection refused',
            'Connection timed out',
            'Host key verification failed',
        ];

        $errorMessage = $exception->getMessage();

        foreach ($criticalPatterns as $pattern) {
            if (str_contains($errorMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene información de diagnóstico del error para incluir en notificaciones
     */
    public function getDiagnosticInfo(Exception $exception): array
    {
        $diagnostics = [
            'error_type' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'timestamp' => now()->toISOString(),
        ];

        // Análisis específico según el tipo de error
        $message = $exception->getMessage();

        if (str_contains($message, 'proc_open')) {
            $diagnostics['likely_cause'] = 'proc_open function disabled on remote server';
            $diagnostics['suggested_action'] = 'Enable proc_open on remote server or implement phpseclib3 fallback';
        }

        if (str_contains($message, 'error in libcrypto')) {
            $diagnostics['likely_cause'] = 'SSH key format issue (line endings or corruption)';
            $diagnostics['suggested_action'] = 'Verify SSH key format and line endings';
        }

        if (str_contains($message, 'Permission denied (publickey)')) {
            $diagnostics['likely_cause'] = 'SSH key authentication failed';
            $diagnostics['suggested_action'] = 'Verify SSH key is correctly installed on remote server';
        }

        if (str_contains($message, 'Connection refused')) {
            $diagnostics['likely_cause'] = 'SSH service not running or port blocked';
            $diagnostics['suggested_action'] = 'Check SSH service status and firewall rules';
        }

        return $diagnostics;
    }
}
