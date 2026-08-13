<?php

declare(strict_types=1);

/**
 * DatabaseTestCase-derived tests only run under sudo (see TestDatabase), so
 * this exercises exactly the root/unix_socket path DB::adminConnection()
 * itself tries first - the same path bin/install.php's own unattended root
 * runs rely on.
 */
class DBAdminConnectionTest extends DatabaseTestCase
{
    public function testReturnsAWorkingConnectionWhenRunningAsRoot(): void
    {
        $connection = DB::adminConnection();

        $this -> assertNotNull($connection);

        $result = mysqli_query($connection, 'SELECT 1 AS `one`');
        $row = mysqli_fetch_assoc($result);

        $this -> assertSame(1, (int) $row['one']);
    }

    public function testCachesTheConnectionAcrossCalls(): void
    {
        $this -> assertSame(DB::adminConnection(), DB::adminConnection());
    }
}
