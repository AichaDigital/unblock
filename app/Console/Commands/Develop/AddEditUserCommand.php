<?php

namespace App\Console\Commands\Develop;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\{select, text};

/**
 * @deprecated This command is obsolete. User management is now handled through Filament admin panel.
 * Kept for reference purposes only. Use Filament UserResource instead.
 */
class AddEditUserCommand extends Command
{
    protected $signature = 'develop:add-edit-user
                            --S|security : Enable complex passwords';

    protected $description = 'Add or edit user';

    public function handle(): void
    {
        $email = text(
            label: 'What is your email?',
            placeholder: 'E.g. Mohammad',
        );

        $firstName = text(
            label: 'What is your name?',
            placeholder: 'E.g. Mohammad',
        );

        $lastName = text(
            label: 'What is your last name?',
            placeholder: 'E.g. Ibn Abdullah',
        );

        $password = text(
            label: 'What is your password?',
        );

        $isAdmin = select(
            label: 'What is your admin?',
            options: ['Yes', 'No'],
            default: 'No'
        );

        User::updateOrCreate(
            ['email' => $email], // Condición para buscar el usuario
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $password,
                'is_admin' => $isAdmin === 'Yes',
            ] // Atributos a actualizar o crear
        );
    }
}
