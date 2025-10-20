<?php

declare(strict_types=1);

namespace Tests\stubs;

class WhmcsTestData
{
    public static function getClientData(): array
    {
        return [
            [
                'id' => 1,
                'firstname' => 'John',
                'lastname' => 'Doe',
                'companyname' => 'Test Company',
                'email' => 'john@example.com',
                'password' => 'hashed_password',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'firstname' => 'Jane',
                'lastname' => 'Smith',
                'companyname' => 'Another Company',
                'email' => 'jane@example.com',
                'password' => 'hashed_password',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    public static function getHostingData(): array
    {
        return [
            [
                'id' => 1,
                'userid' => 1,
                'domain' => 'example.com',
                'username' => 'user1',
                'server' => 100,
                'domainstatus' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'userid' => 1,
                'domain' => 'example.org',
                'username' => 'user2',
                'server' => 101,
                'domainstatus' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    public static function getServerData(): array
    {
        return [
            [
                'id' => 100,
                'name' => 'Server 1',
                'ipaddress' => '192.168.1.100',
                'hostname' => 'server1.example.com',
                'active' => 1,
                'disabled' => 0,
            ],
            [
                'id' => 101,
                'name' => 'Server 2',
                'ipaddress' => '192.168.1.101',
                'hostname' => 'server2.example.com',
                'active' => 1,
                'disabled' => 0,
            ],
        ];
    }
}
