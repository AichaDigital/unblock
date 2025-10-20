<?php

namespace App\Console\Commands\Develop;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\{confirm, select, text};

/**
 * @deprecated This command is obsolete. User management is now handled through Filament admin panel.
 * Kept for reference purposes only. Use Filament UserResource instead.
 */
class DeleteUserCommand extends Command
{
    protected $signature = 'develop:delete-user';

    protected $description = 'Delete user';

    public function handle(): void
    {
        $searchType = select(
            label: 'What field use for search?',
            options: ['id', 'email']
        );

        $placeholder = $searchType === 'id' ? '1234' : 'email@domain.tld';

        $searchable = text(
            label: 'What do like search?',
            placeholder: $placeholder,
        );

        $user = User::where($searchType, $searchable)->first();

        if (! $user) {
            $this->error('User not found');
        } else {
            $confirmed = confirm(
                label: 'Do you want delete user '.$user->full_name.'?',
                default: false,
                yes: 'I want',
                no: ' I don\'t want to delete',
                hint: 'You must be '
            );
            if ($confirmed) {
                $user->delete();
                $this->info('User deleted successfully');
            } else {
                $this->info('User not deleted');
            }
        }
    }
}
