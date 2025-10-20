<?php

/** @file app/Console/Commands/Develop/UpdateStructureSql.php */

namespace App\Console\Commands\Develop;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File};

class UpdateStructureSql extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'develop:sql-structure';

    /**
     * The console command description.
     */
    protected $description = 'Updates database.sql file with current database structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Updating database structure...');

        try {
            // Get all tables
            $tables = DB::select('SHOW FULL TABLES');
            $dbName = DB::connection()->getDatabaseName();

            $tableKey = 'Tables_in_'.$dbName;
            $tableTypeKey = 'Table_type';

            // Initialize file content
            $content = $this->getFileHeader($dbName);

            // Disable foreign key checks
            $content .= "\n-- Disable foreign key checks during creation\n";
            $content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            // Get structure for each table
            foreach ($tables as $table) {
                $tableName = $table->$tableKey;
                $tableType = $table->$tableTypeKey ?? 'BASE TABLE';

                if ($tableType === 'VIEW') {
                    // Handle views
                    $createView = DB::select("SHOW CREATE VIEW `$tableName`");
                    $viewObj = $createView[0];

                    // Find the correct property for view definition
                    $viewProperty = null;
                    if (property_exists($viewObj, 'Create View')) {
                        $viewProperty = 'Create View';
                    } elseif (property_exists($viewObj, 'create view')) {
                        $viewProperty = 'create view';
                    } elseif (property_exists($viewObj, 'CREATE VIEW')) {
                        $viewProperty = 'CREATE VIEW';
                    } else {
                        $this->components->warn("Could not find view definition property for: $tableName");
                        $this->components->warn('Available properties: '.implode(', ', array_keys((array) $viewObj)));

                        continue;
                    }

                    // Add view definition
                    $content .= "-- View: $tableName\n";
                    $content .= "DROP VIEW IF EXISTS `$tableName`;\n";
                    $content .= $viewObj->{$viewProperty}.";\n\n";
                } else {
                    // Handle regular tables
                    $createTable = DB::select("SHOW CREATE TABLE `$tableName`");
                    $tableObj = $createTable[0];

                    // Find the correct property for table definition
                    $tableProperty = null;
                    if (property_exists($tableObj, 'Create Table')) {
                        $tableProperty = 'Create Table';
                    } elseif (property_exists($tableObj, 'create table')) {
                        $tableProperty = 'create table';
                    } elseif (property_exists($tableObj, 'CREATE TABLE')) {
                        $tableProperty = 'CREATE TABLE';
                    } else {
                        $this->components->warn("Could not find table definition property for: $tableName");
                        $this->components->warn('Available properties: '.implode(', ', array_keys((array) $tableObj)));

                        continue;
                    }

                    // Add table definition
                    $content .= "-- Table: $tableName\n";
                    $content .= $tableObj->{$tableProperty}.";\n\n";
                }
            }

            // Enable foreign key checks
            $content .= "-- Enable foreign key checks\n";
            $content .= "SET FOREIGN_KEY_CHECKS=1;\n";

            // Save the file
            $filePath = database_path("sql_prompts/$dbName.sql");
            $this->info("Saving file to: $filePath");

            File::put($filePath, $content);

            $this->components->info('Structure updated successfully!');
            $this->components->info("File saved at: $filePath");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->components->error('Error updating structure:');
            $this->components->error($e->getMessage().' '.$e->getLine());

            return Command::FAILURE;
        }
    }

    /**
     * Get the SQL file header
     */
    private function getFileHeader(string $dbName): string
    {
        $version = config('app.version', '1.0.0');
        $date = now()->format('Y-m-d');

        return <<<SQL
-- --------------------------------------------------------
-- $dbName Database Structure
-- Version: $version
-- Date: $date
-- --------------------------------------------------------

SQL;
    }
}
