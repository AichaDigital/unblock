<?php

namespace App\Console\Commands\Develop;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File, Schema};
use Illuminate\Support\Str;

class BackupSqliteCommand extends Command
{
    protected $signature = 'dev:backup
                          {action : Action to perform (backup|export-table|restore|restore-table)}
                          {--table= : Table name for export-table/restore-table action}
                          {--file= : File name for backup/restore (default: backup_YYYY_MM_DD_HHmmss.sqlite)}
                          {--force : Force restore without asking for confirmation}';

    protected $description = 'Manage SQLite database backups';

    protected string $backupPath;

    protected string $databasePath;

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups');
        $this->databasePath = database_path('database/unblock.sqlite');
    }

    public function handle(): void
    {
        // Asegurar que existe el directorio de backups
        if (! File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }

        match ($this->argument('action')) {
            'backup' => $this->backupDatabase(),
            'export-table' => $this->exportTable(),
            'restore' => $this->restoreDatabase(),
            'restore-table' => $this->restoreTable(),
            default => $this->error('Invalid action. Use backup, export-table, restore or restore-table'),
        };
    }

    protected function backupDatabase(): void
    {
        $backupFile = $this->getBackupFileName();

        try {
            // Copiar el archivo SQLite
            File::copy(
                $this->databasePath,
                $this->backupPath.'/'.$backupFile
            );

            $this->info("Database backup created successfully: {$backupFile}");
        } catch (\Exception $e) {
            $this->error("Error creating backup: {$e->getMessage()}");
        }
    }

    protected function exportTable(): void
    {
        $table = $this->option('table');

        if (! $table) {
            $this->error('Please specify a table name with --table option');

            return;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist");

            return;
        }

        $fileName = Str::slug($table).'_'.date('Y_m_d_His').'.sql';
        $filePath = $this->backupPath.'/'.$fileName;

        try {
            // Obtener la estructura de la tabla
            $createTable = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table])[0]->sql;

            // Obtener los datos
            $rows = DB::table($table)->get();

            $output = "-- Backup of table {$table}\n";
            $output .= '-- Date: '.date('Y-m-d H:i:s')."\n\n";
            $output .= "PRAGMA foreign_keys=OFF;\n\n";
            $output .= $createTable.";\n\n";

            // Generar las sentencias INSERT
            foreach ($rows as $row) {
                $columns = implode(', ', array_keys((array) $row));
                $values = implode(', ', array_map(function ($value) {
                    return is_null($value) ? 'NULL' : DB::getPdo()->quote($value);
                }, (array) $row));

                $output .= "INSERT INTO {$table} ({$columns}) VALUES ({$values});\n";
            }

            File::put($filePath, $output);

            $this->info("Table {$table} exported successfully to {$fileName}");
        } catch (\Exception $e) {
            $this->error("Error exporting table: {$e->getMessage()}");
        }
    }

    protected function restoreDatabase(): void
    {
        $backupFile = $this->option('file');

        if (! $backupFile) {
            // Listar backups disponibles
            $files = File::files($this->backupPath);
            $backups = collect($files)
                ->filter(function ($file) {
                    return Str::endsWith($file->getFilename(), '.sqlite');
                })
                ->map(function ($file) {
                    return $file->getFilename();
                })->toArray();

            if (empty($backups)) {
                $this->error('No backup files found');

                return;
            }

            $backupFile = $this->choice(
                'Which backup would you like to restore?',
                $backups
            );
        }

        $backupPath = $this->backupPath.'/'.$backupFile;

        if (! File::exists($backupPath)) {
            $this->error("Backup file not found: {$backupFile}");

            return;
        }

        if (! $this->option('force') && ! $this->confirm('This will overwrite your current database. Do you wish to continue?')) {
            $this->info('Operation cancelled');

            return;
        }

        try {
            // Cerrar todas las conexiones a la base de datos
            DB::disconnect();

            // Restaurar el backup
            File::copy($backupPath, $this->databasePath);

            $this->info('Database restored successfully');
        } catch (\Exception $e) {
            $this->error("Error restoring database: {$e->getMessage()}");
        }
    }

    protected function restoreTable(): void
    {
        $table = $this->option('table');
        $file = $this->option('file');

        if (! $table || ! $file) {
            $this->error('Please specify both --table and --file options');

            return;
        }

        $filePath = $this->backupPath.'/'.$file;

        if (! File::exists($filePath)) {
            $this->error("SQL file not found: {$file}");

            return;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist in the database");

            return;
        }

        if (! $this->option('force') && ! $this->confirm("This will delete all data in table '{$table}'. Do you wish to continue?")) {
            $this->info('Operation cancelled');

            return;
        }

        try {
            DB::beginTransaction();

            // Desactivar restricciones de claves foráneas
            DB::statement('PRAGMA foreign_keys=OFF');

            // Limpiar la tabla
            DB::table($table)->delete();

            // Leer y ejecutar el archivo SQL
            $sql = File::get($filePath);

            // Dividir el archivo en statements individuales
            $statements = array_filter(
                array_map('trim',
                    explode(";\n", $sql)
                )
            );

            // Ejecutar cada statement, ignorando los comentarios y la estructura
            foreach ($statements as $statement) {
                if (
                    Str::startsWith($statement, 'INSERT INTO') &&
                    Str::contains($statement, $table)
                ) {
                    DB::statement($statement);
                }
            }

            // Reactivar restricciones de claves foráneas
            DB::statement('PRAGMA foreign_keys=ON');

            DB::commit();

            $this->info("Table {$table} restored successfully");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error restoring table: {$e->getMessage()}");
        }
    }

    protected function getBackupFileName(): string
    {
        return $this->option('file') ?? 'backup_'.date('Y_m_d_His').'.sqlite';
    }
}
