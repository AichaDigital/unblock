<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\{HasColor, HasIcon, HasLabel};

enum PanelType: string implements HasColor, HasIcon, HasLabel
{
    case CPANEL = 'cpanel';
    case DIRECTADMIN = 'directadmin';
    case NONE = 'none';

    /**
     * Get the label for the panel type
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::CPANEL => 'cPanel',
            self::DIRECTADMIN => 'DirectAdmin',
            self::NONE => 'Sin Panel',
        };
    }

    /**
     * Get the color for badges in Filament
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CPANEL => 'success',
            self::DIRECTADMIN => 'warning',
            self::NONE => 'gray',
        };
    }

    /**
     * Get the icon for the panel type
     */
    public function getIcon(): ?string
    {
        return match ($this) {
            self::CPANEL => 'heroicon-o-server',
            self::DIRECTADMIN => 'heroicon-o-server-stack',
            self::NONE => 'heroicon-o-x-circle',
        };
    }

    /**
     * Get all panel types as an array for select options
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value', 'value');
    }

    /**
     * Get panel types with labels for select options
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
