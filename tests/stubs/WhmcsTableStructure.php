<?php

declare(strict_types=1);

namespace Tests\stubs;

use Illuminate\Database\Schema\Blueprint;

/**
 * Estructura de las tablas WHMCS relevantes para pruebas
 * Basado en la estructura real de WHMCS pero solo con los campos necesarios
 */
class WhmcsTableStructure
{
    /**
     * Estructura de la tabla tblclients
     *
     * @return array<string, string>
     */
    public static function getClientsStructure(): array
    {
        return [
            'id' => 'int NOT NULL AUTO_INCREMENT',
            'firstname' => 'text NOT NULL',
            'lastname' => 'text NOT NULL',
            'companyname' => 'text NOT NULL',
            'email' => 'text NOT NULL',
            'password' => 'text NOT NULL',
            'status' => "enum('Active','Inactive','Closed') NOT NULL DEFAULT 'Active'",
            'created_at' => "timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'",
            'updated_at' => "timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'",
        ];
    }

    /**
     * Estructura de la tabla tblhosting
     *
     * @return array<string, string>
     */
    public static function getHostingStructure(): array
    {
        return [
            'id' => 'int NOT NULL AUTO_INCREMENT',
            'userid' => 'int NOT NULL',
            'server' => 'int NOT NULL',
            'domain' => 'text NOT NULL',
            'domainstatus' => "enum('Pending','Active','Suspended','Terminated','Cancelled','Fraud','Completed') NOT NULL DEFAULT 'Pending'",
            'username' => 'text NOT NULL',
            'created_at' => "timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'",
            'updated_at' => "timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'",
        ];
    }

    /**
     * Estructura de la tabla tblservers
     *
     * @return array<string, string>
     */
    public static function getServersStructure(): array
    {
        return [
            'id' => 'int NOT NULL AUTO_INCREMENT',
            'name' => 'text NOT NULL',
            'ipaddress' => 'text NOT NULL',
            'hostname' => 'text NOT NULL',
            'active' => 'int NOT NULL',
            'disabled' => 'int NOT NULL',
        ];
    }

    public static function getTableDefinitions(): array
    {
        return [
            'tblclients' => function (Blueprint $table) {
                $table->id();
                $table->string('firstname');
                $table->string('lastname');
                $table->string('companyname')->nullable();
                $table->string('email');
                $table->string('password');
                $table->enum('status', ['Active', 'Inactive', 'Closed'])->default('Active');
                $table->timestamps();
            },
            'tblhosting' => function (Blueprint $table) {
                $table->id();
                $table->integer('userid');
                $table->integer('server');
                $table->string('domain');
                $table->string('username');
                $table->enum('domainstatus', [
                    'Pending',
                    'Active',
                    'Suspended',
                    'Terminated',
                    'Cancelled',
                    'Fraud',
                    'Completed',
                ])->default('Pending');
                $table->timestamps();

                $table->foreign('userid')->references('id')->on('tblclients')->onDelete('cascade');
            },
            'tblservers' => function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('ipaddress');
                $table->string('hostname');
                $table->integer('active');
                $table->integer('disabled');
                $table->timestamps();
            },
        ];
    }
}
