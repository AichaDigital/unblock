<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class SshKeyGeneratorField extends Placeholder
{
    public static function make(?string $name = 'ssh_key_generator'): static
    {
        return parent::make($name)
            ->label(__('hosts.ssh_keys.generation_notice_title'))
            ->content(fn () => new HtmlString('
                <div class="fi-fo-placeholder-content text-sm">
                    <div class="flex items-start gap-4 rounded-lg border border-warning-600 bg-warning-50 p-4 dark:border-warning-400 dark:bg-warning-950">
                        <svg class="shrink-0 text-warning-600 dark:text-warning-400" width="90" height="90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1 pt-2">
                            <p class="font-semibold text-warning-900 dark:text-warning-100 mb-2">
                                '.__('hosts.ssh_keys.generation_notice_title').'
                            </p>
                            <p class="text-warning-700 dark:text-warning-300">
                                '.__('hosts.ssh_keys.generation_notice_body').'
                            </p>
                        </div>
                    </div>
                </div>
            '));
    }
}
