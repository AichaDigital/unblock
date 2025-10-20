<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * @deprecated This command is obsolete. User management is now handled through Filament admin panel.
 * Kept for reference purposes only. Use Filament UserResource instead.
 */
class UserEditionCommand extends Command
{
    protected $signature = 'add:user {--not-secure}';

    protected $description = 'Create and edit users';

    public function handle(): int
    {
        $email = $this->ask('Email ');
        $first_name = $this->ask('Nombre ');
        $last_name = $this->ask('Apellidos ');
        $password = $this->secret('Contraseña ');

        if (! $this->option('not-secure')) {
            $validator = Validator::make([
                'email' => $email,
                'first_name' => $first_name,
                'password' => $password,
            ], [
                'first_name' => [
                    'required',
                    'min:5',
                ],
                'last_name' => [
                    'required',
                    'min:5',
                ],
                'email' => [
                    'required',
                    'min:10',
                    'regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                ],
                'password' => [
                    'required',
                    Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised(),
                ],
            ]);

            if ($validator->fails()) {
                $this->error('El usuario no ha podido ser creado/actualizado por:');
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return Command::FAILURE;
            }
        }

        // Salvar los datos
        User::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'password' => bcrypt($password),
            ]
        );

        return Command::SUCCESS;
    }
}
