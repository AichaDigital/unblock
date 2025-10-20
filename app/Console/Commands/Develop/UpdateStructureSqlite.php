<?php

/** @file app/Console/Commands/Develop/UpdateStructureSqlite.php */

namespace App\Console\Commands\Develop;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File};

class UpdateStructureSqlite extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'develop:sqlite-structure';

    /**
     * The console command description.
     */
    protected $description = 'Updates database.sql file with current SQLite database structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Actualizando estructura de la base de datos SQLite...');

        try {
            // Obtener el nombre de la base de datos
            $connection = DB::connection();
            $dbName = basename($connection->getDatabaseName());
            $this->components->info("Base de datos: {$dbName}");

            // Obtener las tablas (excepto las de sistema)
            $tables = $this->getTables();

            // Inicializar contenido del archivo
            $content = $this->getFileHeader($dbName);

            // Obtener la estructura para cada tabla
            foreach ($tables as $tableName) {
                $this->components->info("Procesando tabla: {$tableName}");

                // Obtener la sentencia CREATE de la tabla
                $createTable = $this->getCreateTableStatement($tableName);

                if ($createTable) {
                    // Agregar definición de tabla
                    $content .= "-- Tabla: {$tableName}\n";
                    $content .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    $content .= "{$createTable};\n\n";

                    // Obtener índices
                    $indexes = $this->getTableIndexes($tableName);
                    foreach ($indexes as $index) {
                        if ($index->name !== 'sqlite_autoindex_'.$tableName.'_1') {
                            $content .= "-- Índice: {$index->name} para {$tableName}\n";
                            $content .= "{$index->sql};\n\n";
                        }
                    }
                }
            }

            // Obtener las vistas
            $views = $this->getViews();
            foreach ($views as $viewName) {
                $this->components->info("Procesando vista: {$viewName}");

                // Obtener la sentencia CREATE de la vista
                $createView = $this->getCreateViewStatement($viewName);

                if ($createView) {
                    // Agregar definición de vista
                    $content .= "-- Vista: {$viewName}\n";
                    $content .= "DROP VIEW IF EXISTS `{$viewName}`;\n";
                    $content .= "{$createView};\n\n";
                }
            }

            // Guardar el archivo
            $dirPath = database_path('sql_prompts');
            if (! File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }

            $filePath = "{$dirPath}/{$dbName}.sql";
            File::put($filePath, $content);

            $this->components->info('Estructura actualizada correctamente!');
            $this->components->info("Archivo guardado en: {$filePath}");

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->components->error('Error al actualizar la estructura:');
            $this->components->error($e->getMessage().' en la línea '.$e->getLine());
            $this->components->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Obtener las tablas de la base de datos (excepto las de sistema)
     */
    private function getTables(): array
    {
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        return array_column($tables, 'name');
    }

    /**
     * Obtener las vistas de la base de datos
     */
    private function getViews(): array
    {
        $views = DB::select("SELECT name FROM sqlite_master WHERE type='view'");

        return array_column($views, 'name');
    }

    /**
     * Obtener la sentencia CREATE para una tabla
     */
    private function getCreateTableStatement(string $tableName): ?string
    {
        $result = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$tableName]);

        return $result ? $result->sql : null;
    }

    /**
     * Obtener la sentencia CREATE para una vista
     */
    private function getCreateViewStatement(string $viewName): ?string
    {
        $result = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='view' AND name = ?", [$viewName]);

        return $result ? $result->sql : null;
    }

    /**
     * Obtener los índices de una tabla
     */
    private function getTableIndexes(string $tableName): array
    {
        return DB::select("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name = ? AND sql IS NOT NULL", [$tableName]);
    }

    /**
     * Obtener la cabecera del archivo SQL
     */
    private function getFileHeader(string $dbName): string
    {
        $version = config('app.version', '1.0.0');
        $date = now()->format('Y-m-d');

        return <<<SQL
-- --------------------------------------------------------
-- Base de datos SQLite: {$dbName}
-- Versión: {$version}
-- Fecha: {$date}
-- --------------------------------------------------------

PRAGMA foreign_keys = OFF;

SQL;
    }
}
