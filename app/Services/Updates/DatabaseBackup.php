<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\DB;

/**
 * Back up the database before a CMS update — without mysqldump, so it also
 * works on shared hosting.
 *
 *   SQLite        copy of the file
 *   MySQL/MariaDB SQL file with structure and data (table by table, in chunks)
 *   others        no backup (null) — the update page points this out
 *
 * The backup is only restored by hand; a failed update automatically
 * reverts its migrations via migrate:rollback.
 */
final class DatabaseBackup
{
    /** @return string|null Path of the backup */
    public function create(string $directory): ?string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $file = (string) $connection->getConfig('database');
            if ($file === '' || $file === ':memory:' || ! is_file($file)) {
                return null;
            }
            $target = $directory.'/database.sqlite';
            copy($file, $target);

            return $target;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }

        $target = $directory.'/database.sql';
        $out = fopen($target, 'wb');
        $pdo = $connection->getPdo();

        fwrite($out, "-- Inverys CMS: backup before the update\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($connection->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = (string) array_values((array) $row)[0];
            $create = (array) $connection->selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`');
            fwrite($out, 'DROP TABLE IF EXISTS `'.$table."`;\n".array_values($create)[1].";\n\n");

            foreach ($connection->table($table)->cursor() as $record) {
                $values = array_map(
                    fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    (array) $record,
                );
                fwrite($out, 'INSERT INTO `'.$table.'` VALUES ('.implode(',', $values).");\n");
            }
            fwrite($out, "\n");
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);

        return $target;
    }
}
