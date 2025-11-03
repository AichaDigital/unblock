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
                <div class="fi-fo-placeholder-content text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex items-start gap-3 rounded-lg border border-info-600 bg-info-50 p-4 dark:border-info-400 dark:bg-info-950">
                        <svg class="h-5 w-5 text-info-600 dark:text-info-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                            <p class="font-medium text-info-900 dark:text-info-100">
                                '.__('hosts.ssh_keys.generation_notice_title').'
                            </p>
                            <p class="mt-1 text-info-700 dark:text-info-300">
                                '.__('hosts.ssh_keys.generation_notice_body').'
                            </p>
                        </div>
                    </div>
                </div>
            '));
    }
}
