<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

final class SqliteConnection {
    /**
     * Opens the database with foreign keys on, creates the schema on an empty database, and removes
     * items whose group is gone (left over from deletes before foreign keys were switched on).
     */
    public static function open(string $dsn, string $schemaPath): \PDO {
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        // SQLite ignores ON DELETE CASCADE unless this is set on every connection
        $pdo->exec('PRAGMA foreign_keys = ON');

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        if ($tables === false || $tables->fetchAll() === []) {
            $schema = file_get_contents($schemaPath);
            if ($schema !== false) {
                $pdo->exec($schema);
            }
        }

        $pdo->exec('DELETE FROM items WHERE group_id NOT IN (SELECT id FROM groups)');

        return $pdo;
    }
}
