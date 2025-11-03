<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\Develop\TestHostConnectionCommand;
use App\Enums\PanelType;
use Tests\TestCase;

class TestHostConnectionCommandTest extends TestCase
{
    public function test_command_can_be_instantiated_without_error(): void
    {
        $command = new TestHostConnectionCommand;

        $this->assertInstanceOf(TestHostConnectionCommand::class, $command);
    }

    public function test_command_signature_exists(): void
    {
        $command = new TestHostConnectionCommand;

        $this->assertSame('develop:test-host-connection', $command->getName());
    }

    public function test_command_has_host_id_option(): void
    {
        $command = new TestHostConnectionCommand;

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('host-id'));
    }

    public function test_panel_type_enum_has_value_method(): void
    {
        foreach (PanelType::cases() as $panelType) {
            $this->assertIsString($panelType->value);
            $this->assertNotEmpty($panelType->value);
        }
    }
}
