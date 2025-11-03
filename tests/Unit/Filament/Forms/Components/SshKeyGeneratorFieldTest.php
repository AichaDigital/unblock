<?php

declare(strict_types=1);

use App\Filament\Forms\Components\SshKeyGeneratorField;
use Filament\Forms\Components\Placeholder;

test('can instantiate ssh key generator field', function () {
    $field = SshKeyGeneratorField::make();

    expect($field)
        ->toBeInstanceOf(SshKeyGeneratorField::class)
        ->toBeInstanceOf(Placeholder::class);
});

test('can instantiate with custom name', function () {
    $field = SshKeyGeneratorField::make('custom_name');

    expect($field)->toBeInstanceOf(SshKeyGeneratorField::class);
});

test('has info notice content', function () {
    $field = SshKeyGeneratorField::make();

    // Get the content
    $content = $field->getContent();

    expect($content)->not->toBeNull();
});
