<?php

declare(strict_types=1);

use App\Enums\PanelType;

test('PanelType has CPANEL case', function () {
    expect(PanelType::CPANEL->value)->toBe('cpanel')
        ->and(PanelType::CPANEL)->toBeInstanceOf(PanelType::class);
});

test('PanelType has DIRECTADMIN case', function () {
    expect(PanelType::DIRECTADMIN->value)->toBe('directadmin')
        ->and(PanelType::DIRECTADMIN)->toBeInstanceOf(PanelType::class);
});

test('PanelType has NONE case', function () {
    expect(PanelType::NONE->value)->toBe('none')
        ->and(PanelType::NONE)->toBeInstanceOf(PanelType::class);
});

test('PanelType getLabel returns correct labels', function () {
    expect(PanelType::CPANEL->getLabel())->toBe('cPanel')
        ->and(PanelType::DIRECTADMIN->getLabel())->toBe('DirectAdmin')
        ->and(PanelType::NONE->getLabel())->toBe('Sin Panel');
});

test('PanelType getColor returns correct colors', function () {
    expect(PanelType::CPANEL->getColor())->toBe('success')
        ->and(PanelType::DIRECTADMIN->getColor())->toBe('warning')
        ->and(PanelType::NONE->getColor())->toBe('gray');
});

test('PanelType getIcon returns correct icons', function () {
    expect(PanelType::CPANEL->getIcon())->toBe('heroicon-o-server')
        ->and(PanelType::DIRECTADMIN->getIcon())->toBe('heroicon-o-server-stack')
        ->and(PanelType::NONE->getIcon())->toBe('heroicon-o-x-circle');
});

test('PanelType toArray returns all values', function () {
    $array = PanelType::toArray();

    expect($array)->toBeArray()
        ->toHaveCount(3)
        ->toHaveKey('cpanel')
        ->toHaveKey('directadmin')
        ->toHaveKey('none')
        ->and($array['cpanel'])->toBe('cpanel')
        ->and($array['directadmin'])->toBe('directadmin')
        ->and($array['none'])->toBe('none');
});

test('PanelType options returns values with labels', function () {
    $options = PanelType::options();

    expect($options)->toBeArray()
        ->toHaveCount(3)
        ->toHaveKey('cpanel')
        ->toHaveKey('directadmin')
        ->toHaveKey('none')
        ->and($options['cpanel'])->toBe('cPanel')
        ->and($options['directadmin'])->toBe('DirectAdmin')
        ->and($options['none'])->toBe('Sin Panel');
});

test('PanelType cases returns all enum cases', function () {
    $cases = PanelType::cases();

    expect($cases)->toBeArray()
        ->toHaveCount(3)
        ->and($cases[0])->toBe(PanelType::CPANEL)
        ->and($cases[1])->toBe(PanelType::DIRECTADMIN)
        ->and($cases[2])->toBe(PanelType::NONE);
});

test('PanelType can be instantiated from string value', function () {
    expect(PanelType::from('cpanel'))->toBe(PanelType::CPANEL)
        ->and(PanelType::from('directadmin'))->toBe(PanelType::DIRECTADMIN)
        ->and(PanelType::from('none'))->toBe(PanelType::NONE);
});

test('PanelType tryFrom returns null for invalid value', function () {
    expect(PanelType::tryFrom('invalid'))->toBeNull();
});

test('PanelType from throws exception for invalid value', function () {
    PanelType::from('invalid');
})->throws(ValueError::class);

test('PanelType implements HasLabel interface', function () {
    expect(PanelType::CPANEL)->toBeInstanceOf(Filament\Support\Contracts\HasLabel::class);
});

test('PanelType implements HasColor interface', function () {
    expect(PanelType::CPANEL)->toBeInstanceOf(Filament\Support\Contracts\HasColor::class);
});

test('PanelType implements HasIcon interface', function () {
    expect(PanelType::CPANEL)->toBeInstanceOf(Filament\Support\Contracts\HasIcon::class);
});
