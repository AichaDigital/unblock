<?php

namespace App\Console\Commands;

use App\Mail\LogNotificationMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Mail};
use Illuminate\Support\Str;

class PandoraCommand extends Command
{
    protected $signature = 'pandora';

    protected $description = 'Command description';

    public function handle(): void
    {
        $user = User::find(27);

        $report = [
            'csf' => '',
            'exim_directadmin' => '2024-11-13 16:44:21 login authenticator failed for (193-114-36-114.mvno.rakuten.jp) [193.114.36.114]: 535 Incorrect authentication data (set_id=info)',
            'dovecot_directadmin' => '',
            'mod_security_da' => '',
        ];

        // Simulamos una IP y que no está bloqueada
        $ip = '192.168.0.1';
        // $is_unblock = true; // No hay desbloqueo porque no estaba bloqueada

        // Enviamos el correo electrónico usando LogNotificationMail
        Mail::to($user->email)->send(new LogNotificationMail(
            user: $user,
            report: $report,
            ip: $ip,
            is_unblock: true,
            report_uuid: (string) Str::uuid(),
        ));

        // Mensaje de confirmación en consola
        $this->info('Correo enviado a '.$user->email);
    }
}
