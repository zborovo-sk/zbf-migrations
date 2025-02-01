<?php

namespace ZborovoSK\ZBFMigrations;

use PDO;
use PDOException;
use ZborovoSK\ZBFMigrations\MigratorException;


class Migrator
{
    private string $migrationsTable = 'migrations';

    private string $migrationsDir = 'migrations';

    protected PDO $pdo;

    /**
     * Migrator constructor.
     * @param PDO $pdo
     * @param string $migrationsTable
     * @param string $migrationsDir
     */
    public function __construct(PDO $pdo, string $migrationsTable = 'migrations', string $migrationsDir = 'migrations')
    {
        $this->pdo = $pdo;
        $this->setMigrationsTable($migrationsTable);
        $this->setMigrationsDir($migrationsDir);
    }

    /**
     * Set migrations table name
     * @param string $migrationsTable
     * @return void
     */
    public function setMigrationsTable(string $migrationsTable): void
    {
        $this->migrationsTable = $migrationsTable;
    }

    /**
     * Set migrations directory
     * @param string $migrationsDir
     * @return void
     */
    public function setMigrationsDir(string $migrationsDir): void
    {
        $this->migrationsDir = $migrationsDir;
    }

    /**
     * Get database type
     * @return string
     */
    private function getDatabaseType(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Create migrations table if not exists
     * @return void
     */
    private function createMigrationsTable()
    {
        // we get the database type
        $databaseType = $this->getDatabaseType();

        // we create the migrations table based on the database type
        switch ($databaseType) {
            case 'mysql':
                $this->createMigrationsTableForMySQL();
                break;
            case 'sqlite':
                $this->createMigrationsTableForSQLite();
                break;
            case 'pgsql':
                $this->createMigrationsTableForPostgreSQL();
                break;
            default:
                throw new MigratorException('Database type not supported');
        }

    }

    /**
     * Create migrations table for MySQL
     * @return void
     */
    private function createMigrationsTableForMySQL(): void{
        try {
            echo ' - Creating migrations table for MySQL... (if not exists)'.PHP_EOL;
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            throw new MigratorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create migrations table for SQLite
     * @return void
     */
    private function createMigrationsTableForSQLite(): void{
        try {
            echo ' - Creating migrations table for SQLite... (if not exists)'.PHP_EOL;
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            throw new MigratorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create migrations table for PostgreSQL
     */
    private function createMigrationsTableForPostgreSQL(): void{
        try {
            echo ' - Creating migrations table for PostgreSQL... (if not exists)'.PHP_EOL;
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (PDOException $e) {
            throw new MigratorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get list of migrated files
     * @return array
     */
    private function getMigratedFiles(): array
    {
        $migratedFiles = [];

        try {
            $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrationsTable}");
            $stmt->execute();
            $migratedFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw new MigratorException($e->getMessage(), $e->getCode(), $e);
        }

        return $migratedFiles;
    }

    private function filesToMigrate(): array
    {
        $files = scandir($this->migrationsDir);
        $migratedFiles = $this->getMigratedFiles();
        $filesToMigrate = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (!in_array($file, $migratedFiles)) {
                $filesToMigrate[] = $file;
            }
        }

        return $filesToMigrate;
    }

    /**
     * Run migrations
     * @return void
     */
    public function migrate()
    {
        echo 'Migrating...';

        // Create migrations table if not exists
        $this->createMigrationsTable();

        echo ' - Checking for new migrations...'.PHP_EOL;

        $filesToMigrate = $this->filesToMigrate();

        if (empty($filesToMigrate)) {
            echo ' - No new migrations found'.PHP_EOL;
            return;
        }

        echo ' - Found '.count($filesToMigrate).' new migration(s)'.PHP_EOL;

        foreach ($filesToMigrate as $file) {
            echo PHP_EOL.' - Migrating '.$file.PHP_EOL;

            try {
                //migrations are in form of SQL files, so we create transaction,
                //read the content of the file, split it by `;` and execute each query separately
                //add insert statement to migrations table and commit the transaction

                $this->pdo->beginTransaction();

                $content = file_get_contents($this->migrationsDir.'/'.$file);

                $queries = explode(';', $content);

                if (empty($queries)) {
                    throw new MigratorException(' - No queries found in migration file');
                }

                $queryCount = 0;

                foreach ($queries as $query) {
                    $query = trim($query);
                    if (empty($query)) {
                        continue;
                    }

                    $this->pdo->exec($query);
                    $queryCount++;
                }

                $stmt = $this->pdo->prepare("INSERT INTO {$this->migrationsTable} (migration) VALUES (:migration)");
                $stmt->execute(['migration' => $file]);

                $this->pdo->commit();

                echo ' - Migrated '.$queryCount.' queries'.PHP_EOL;


            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw new MigratorException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}
