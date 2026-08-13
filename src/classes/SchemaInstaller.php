<?php

declare(strict_types=1);

class SchemaInstaller
{
    /**
     * Every CREATE TABLE in schema.sql, keyed by table name, boundaries found
     * on comment-stripped text. The statements are split on semicolons, and a
     * semicolon inside a `--` comment ("the public key is a P-256 ECDH JWK;")
     * would otherwise truncate its statement mid-column-list - executing that
     * fragment is a syntax error, so a fresh install of the table would fail.
     * MariaDB doesn't need the comments; whoever reads schema.sql does.
     *
     * @return array<string, string> table name => its CREATE TABLE statement
     */
    public static function createTableStatements(): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $code_lines = array_filter(
            explode("\n", (string) file_get_contents($schema_path)),
            static fn (string $line): bool => !str_starts_with(trim($line), '--')
        );

        preg_match_all('/CREATE TABLE `(\w+)` \([^;]+;/s', implode("\n", $code_lines), $matches, PREG_SET_ORDER);

        if ($matches === []) {
            throw new \RuntimeException('Could not parse any CREATE TABLE statements out of schema.sql.');
        }

        $statements = [];

        foreach ($matches as $match) {
            $statements[$match[1]] = $match[0];
        }

        return $statements;
    }

    /**
     * @return array<string, string> table name => its CREATE TABLE statement, for every table
     *                                in schema.sql not yet present in $connection's current database
     */
    public static function missingTables(\mysqli $connection): array
    {
        $existing_tables_result = mysqli_query($connection, '
SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
');
        $existing_tables = array_column(mysqli_fetch_all($existing_tables_result, MYSQLI_ASSOC), 'TABLE_NAME');

        return array_filter(
            self::createTableStatements(),
            static fn (string $table): bool => !in_array($table, $existing_tables, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Whether the database has NONE of the app's tables yet - a genuinely fresh
     * install, which should get the current schema created directly rather than
     * run through the incremental upgrade steps (drift/type migrations).
     * An empty-but-installed database (its tables exist, just no rows) is NOT
     * fresh and takes the normal upgrade path - the code updates daily, so an
     * install created yesterday can need upgrading today even with no data.
     */
    public static function isFreshInstall(): bool
    {
        $existing_result = mysqli_query(DB::connection(), '
SELECT `TABLE_NAME`
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
');
        $existing = array_column(mysqli_fetch_all($existing_result, MYSQLI_ASSOC), 'TABLE_NAME');

        foreach (array_keys(self::schemaTableBodies()) as $table) {
            if (in_array($table, $existing, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $statements table name => its CREATE TABLE statement
     */
    public static function createTables(\mysqli $admin_connection, array $statements): void
    {
        foreach ($statements as $statement) {
            mysqli_query($admin_connection, $statement);
        }
    }

    /**
     * The non-CREATE-TABLE, non-ALTER-TABLE statements in schema.sql -
     * idempotent DML maintenance the installer runs after tables are ensured,
     * on every install and upgrade. Kept in schema.sql so the whole schema,
     * data-maintenance included, has one source of truth. Same
     * one-statement-per-`;` assumption as the CREATE TABLE parsing (no
     * semicolons inside a statement). ALTER TABLE statements are excluded
     * here - they're DDL (see indexMigrationStatements()) and need admin
     * privileges the runtime account this runs on deliberately doesn't have.
     *
     * @return string[]
     */
    public static function maintenanceStatements(): array
    {
        return array_values(array_filter(
            self::nonTableStatements(),
            static fn (string $statement): bool => !str_starts_with($statement, 'ALTER TABLE')
        ));
    }

    public static function runMaintenance(\mysqli $connection): void
    {
        foreach (self::maintenanceStatements() as $statement) {
            mysqli_query($connection, $statement);
        }
    }

    /**
     * The idempotent index migrations in schema.sql (ALTER TABLE ... ADD/DROP
     * INDEX IF NOT EXISTS/IF EXISTS, and MODIFY COLUMN column-type fixes) -
     * DDL, so unlike maintenanceStatements() these need admin privileges.
     * Returns only the ones actually still needed (an ADD whose index already
     * exists, a DROP whose index is already gone, or a MODIFY whose column
     * already has the target type, is left out), so callers only have to
     * reach for admin credentials when there's genuinely DDL work pending -
     * the same "admin creds only when there's real work" principle
     * missingDefinitions() already follows for column/index/FK drift.
     *
     * @return string[]
     */
    public static function neededIndexMigrations(): array
    {
        $needed = [];
        $existing_by_table = [];
        $statements = self::indexMigrationStatements();
        $count = count($statements);

        for ($i = 0; $i < $count; $i++) {
            $statement = $statements[$i];

            if (preg_match('/^ALTER TABLE `(\w+)` MODIFY COLUMN `(\w+)` (\S+(?: unsigned)?)(?: NOT NULL)?(?: DEFAULT (\S+))?/', $statement, $column_match)) {
                [, $table, $column, $target_type] = $column_match;
                // Only present for a statement that names one (e.g. a bare
                // type change like the int(11)->unsigned migrations above
                // doesn't) - some column changes are a default-value fix
                // with the type unchanged (e.g. Users.verified), which the
                // type-only comparison alone would never flag as needed.
                $target_default = $column_match[4] ?? null;
                $current_type = self::columnType($table, $column);

                if ($current_type === null) {
                    continue;
                }

                $needs_migration = strcasecmp($current_type, $target_type) !== 0;

                if (!$needs_migration && $target_default !== null) {
                    $current_default = self::columnDefault($table, $column);
                    $needs_migration = $current_default === null || strcasecmp(trim($current_default, '\''), trim($target_default, '\'')) !== 0;
                }

                if ($needs_migration) {
                    $needed[] = $statement;
                }

                continue;
            }

            // An FK rule change: a DROP FOREIGN KEY immediately followed by
            // an ADD CONSTRAINT of the same name - two separate statements,
            // not one combined DROP+ADD (MariaDB errors re-adding the same
            // constraint name within the ALTER TABLE it was just dropped
            // in). Detected and applied as a pair.
            if (
                preg_match('/^ALTER TABLE `(\w+)` DROP FOREIGN KEY `(\w+)`$/', $statement, $drop_match)
                && isset($statements[$i + 1])
                && preg_match(
                    '/^ALTER TABLE `' . preg_quote($drop_match[1], '/') . '` ADD CONSTRAINT `' . preg_quote($drop_match[2], '/') . '` FOREIGN KEY .+ ON DELETE (\w+)$/',
                    $statements[$i + 1],
                    $add_match
                )
            ) {
                [, $table, $constraint] = $drop_match;
                $target_rule = $add_match[1];
                $current_rule = self::foreignKeyDeleteRule($table, $constraint);

                if ($current_rule !== null && strcasecmp($current_rule, $target_rule) !== 0) {
                    $needed[] = $statement;
                    $needed[] = $statements[$i + 1];
                }

                $i++; // the paired ADD statement is already accounted for

                continue;
            }

            if (!preg_match('/^ALTER TABLE `(\w+)` (ADD|DROP) INDEX IF (?:NOT )?EXISTS `(\w+)`/', $statement, $match)) {
                continue;
            }

            [, $table, $action, $index] = $match;

            if (!isset($existing_by_table[$table])) {
                $existing_by_table[$table] = self::existingIndexes($table);
            }

            $index_exists = in_array($index, $existing_by_table[$table], true);

            if (($action === 'ADD' && !$index_exists) || ($action === 'DROP' && $index_exists)) {
                $needed[] = $statement;
            }
        }

        return $needed;
    }

    private static function columnType(string $table, string $column): ?string
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `COLUMN_TYPE`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null ? (string) $row['COLUMN_TYPE'] : null;
    }

    private static function columnDefault(string $table, string $column): ?string
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `COLUMN_DEFAULT`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null && $row['COLUMN_DEFAULT'] !== null ? (string) $row['COLUMN_DEFAULT'] : null;
    }

    private static function foreignKeyDeleteRule(string $table, string $constraint): ?string
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `DELETE_RULE`
    FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
    WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `CONSTRAINT_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $constraint);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null ? (string) $row['DELETE_RULE'] : null;
    }

    /**
     * @return string[]
     */
    private static function indexMigrationStatements(): array
    {
        return array_values(array_filter(
            self::nonTableStatements(),
            static fn (string $statement): bool => str_starts_with($statement, 'ALTER TABLE')
        ));
    }

    /**
     * Every statement in schema.sql outside the CREATE TABLE blocks - the
     * shared parse behind maintenanceStatements() (DML) and
     * indexMigrationStatements() (DDL), split apart by their differing
     * privilege requirements.
     *
     * @return string[]
     */
    private static function nonTableStatements(): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $schema_sql = (string) file_get_contents($schema_path);

        // Strip full-line `--` comments first, before anything else looks at a
        // semicolon. Prose contains them freely, and both steps below treat a
        // semicolon as a statement boundary: one inside a comment would end the
        // CREATE TABLE match early and spill the rest of the table's columns
        // out as though they were statements of their own.
        $code_lines = [];

        foreach (explode("\n", $schema_sql) as $line) {
            if (!str_starts_with(trim($line), '--')) {
                $code_lines[] = $line;
            }
        }

        // Remove the CREATE TABLE blocks (created separately).
        $without_tables = (string) preg_replace('/CREATE TABLE `\w+` \([^;]+;/s', '', implode("\n", $code_lines));

        $statements = [];

        foreach (explode(';', $without_tables) as $chunk) {
            $statement = trim($chunk);

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Schema drift on tables that already exist: columns, indexes, and
     * foreign keys that schema.sql defines but the live table lacks - the
     * situation an existing install lands in after upgrading to a version
     * whose schema.sql gained something. Compares by name only (a changed
     * definition under a kept name is out of scope) and returns ready-to-run
     * ALTER statements keyed by a human-readable label.
     *
     * @return array<string, array<string, string>> table name => [label => ALTER statement]
     */
    public static function missingDefinitions(): array
    {
        $missing = [];

        foreach (self::schemaTableBodies() as $table => $body) {
            if (!self::tableExists($table)) {
                continue;
            }

            $existing_columns = self::existingColumns($table);
            $existing_indexes = self::existingIndexes($table);
            $existing_constraints = self::existingConstraints($table);
            $previous_column = null;
            $combined_indexes = [];

            foreach (self::parseBodyLines($body) as $line) {
                if (preg_match('/^`(\w+)`/', $line, $column_match)) {
                    if (!in_array($column_match[1], $existing_columns, true)) {
                        $position = $previous_column === null ? 'FIRST' : 'AFTER `' . $previous_column . '`';
                        $add = 'ALTER TABLE `' . $table . '` ADD COLUMN ' . $line . ' ' . $position;

                        // MySQL/MariaDB refuse an AUTO_INCREMENT column that
                        // isn't defined as a key within the same statement, so
                        // its covering key from the body rides along in this
                        // ALTER - and the index pass below skips it, or the
                        // same key would be added twice.
                        if (str_contains($line, 'AUTO_INCREMENT')) {
                            $key_line = self::keyLineCovering($body, $column_match[1]);

                            if ($key_line !== null && preg_match('/`(\w+)`/', $key_line, $key_name_match) === 1 && !in_array($key_name_match[1], $existing_indexes, true)) {
                                $add .= ', ADD ' . $key_line;
                                $combined_indexes[] = $key_name_match[1];
                            }
                        }

                        $missing[$table]['column ' . $column_match[1]] = $add;
                    }

                    $previous_column = $column_match[1];
                } elseif (preg_match('/^(UNIQUE KEY|FULLTEXT KEY|KEY) `(\w+)`/', $line, $index_match)) {
                    if (!in_array($index_match[2], $existing_indexes, true) && !in_array($index_match[2], $combined_indexes, true)) {
                        $missing[$table]['index ' . $index_match[2]] = 'ALTER TABLE `' . $table . '` ADD ' . $line;
                    }
                } elseif (preg_match('/^CONSTRAINT `(\w+)`/', $line, $constraint_match)) {
                    if (!in_array($constraint_match[1], $existing_constraints, true)) {
                        $missing[$table]['foreign key ' . $constraint_match[1]] = 'ALTER TABLE `' . $table . '` ADD ' . $line;
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * The body's key line whose leading column is $column - what an
     * AUTO_INCREMENT column's ADD COLUMN drift statement must carry along,
     * since the column can't exist unkeyed even for a moment. Null when the
     * body declares no key led by it (a schema that did that would be invalid
     * to CREATE in the first place).
     */
    private static function keyLineCovering(string $body, string $column): ?string
    {
        foreach (self::parseBodyLines($body) as $line) {
            if (preg_match('/^(?:PRIMARY KEY|(?:UNIQUE |FULLTEXT )?KEY `\w+`) \(`' . preg_quote($column, '/') . '`/', $line) === 1) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> table name => the body between CREATE TABLE's parentheses
     */
    private static function schemaTableBodies(): array
    {
        $schema_path = __DIR__ . '/../../schema.sql';

        if (!is_file($schema_path)) {
            throw new \RuntimeException('schema.sql not found at ' . $schema_path . '.');
        }

        $schema_sql = (string) file_get_contents($schema_path);

        preg_match_all('/CREATE TABLE `(\w+)` \((.+?)\) ENGINE[^;]*;/s', $schema_sql, $matches, PREG_SET_ORDER);

        $bodies = [];

        foreach ($matches as $match) {
            $bodies[$match[1]] = $match[2];
        }

        return $bodies;
    }

    /**
     * @return string[] the body's definition lines, trimmed, without trailing commas
     */
    private static function parseBodyLines(string $body): array
    {
        $lines = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim(rtrim(trim($line), ','));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function tableExists(string $table): bool
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT 1
    FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * @return string[]
     */
    private static function existingColumns(string $table): array
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT `COLUMN_NAME`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'COLUMN_NAME');
    }

    /**
     * @return string[]
     */
    private static function existingIndexes(string $table): array
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT DISTINCT `INDEX_NAME`
    FROM `information_schema`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ?
');
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'INDEX_NAME');
    }

    /**
     * @return string[]
     */
    private static function existingConstraints(string $table): array
    {
        $foreign_key_type = 'FOREIGN KEY';

        $stmt = mysqli_prepare(DB::connection(), '
SELECT `CONSTRAINT_NAME`
    FROM `information_schema`.`TABLE_CONSTRAINTS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `CONSTRAINT_TYPE` = ?
');
        mysqli_stmt_bind_param($stmt, 'ss', $table, $foreign_key_type);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'CONSTRAINT_NAME');
    }
}
