<?php

declare(strict_types=1);

// The test suite's entry point: `php bin/run-tests.php`. No external test
// runner (no PHPUnit/Composer) - a hand-rolled reflection-based discovery
// loop, same instinct as everywhere else in this app that reaches for "we
// write it ourselves" over a dependency. A test class is anything under
// tests/ extending TestCase; a test case is any public method on it named
// test*. Exits non-zero on any failure, so this is CI-usable as-is.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// A PHP warning is a bug this suite should catch rather than a line scrolling
// past in a production log - an undefined property means the code is wrong
// about its own data, and nothing about a green run should hide that. Thrown,
// so it fails the case that provoked it and names it. Suppressed diagnostics
// (@) are left alone, since those are deliberate.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../tests/TestCase.php';
require __DIR__ . '/../tests/DatabaseTestCase.php';
require __DIR__ . '/../tests/TestDatabase.php';

$tests_dir = __DIR__ . '/../tests';

foreach (glob($tests_dir . '/*Test.php') ?: [] as $file) {
    require $file;
}

$test_classes = array_filter(
    get_declared_classes(),
    fn (string $class) => is_subclass_of($class, TestCase::class) && !(new \ReflectionClass($class)) -> isAbstract()
);

sort($test_classes);

if ($test_classes === []) {
    fwrite(STDERR, "No test classes found under tests/ (expected files named *Test.php defining a class extending TestCase).\n");
    exit(1);
}

// DatabaseTestCase-derived tests need a throwaway database this process
// builds and drops itself (CREATE/DROP DATABASE, table copies) - only
// possible via the DB server's root account, so they're skipped rather than
// failing noisily when this script isn't run with sudo.
$db_test_classes = array_values(array_filter(
    $test_classes,
    fn (string $class) => is_subclass_of($class, DatabaseTestCase::class)
));
$run_classes = array_values(array_diff($test_classes, $db_test_classes));

$running_as_root = trim((string) shell_exec('id -u 2>/dev/null')) === '0';

// A whole class stood down counts as every test in it, not as one line. A
// summary saying four were skipped while several hundred silently did not run
// is worse than no summary: it reads as a suite that passed.
$test_method_count = static fn (string $class): int => count(array_filter(
    get_class_methods($class),
    fn (string $method) => str_starts_with($method, 'test')
));

$skipped_classes = [];

if ($db_test_classes !== [] && $running_as_root && TestDatabase::setUp()) {
    register_shutdown_function([TestDatabase::class, 'tearDown']);
    $run_classes = array_merge($run_classes, $db_test_classes);
    sort($run_classes);
} elseif ($db_test_classes !== []) {
    $skipped_classes = [
        'classes' => count($db_test_classes),
        'tests' => array_sum(array_map($test_method_count, $db_test_classes)),
        'reason' => $running_as_root
            ? 'test database setup failed, see above'
            : 're-run with sudo to include them',
    ];

    echo 'Skipping ' . $skipped_classes['tests'] . ' test(s) in ' . $skipped_classes['classes']
        . ' database-backed class(es) - ' . $skipped_classes['reason'] . ".\n\n";
}

$total = 0;
$failures = [];
$skipped = [];
$timings = [];
$started_at = microtime(true);

foreach ($run_classes as $class) {
    $instance = new $class();
    $methods = array_filter(
        get_class_methods($class),
        fn (string $method) => str_starts_with($method, 'test')
    );

    sort($methods);

    foreach ($methods as $method) {
        $total++;
        $label = $class . '::' . $method;

        $test_started_at = microtime(true);

        try {
            $instance -> $method();
            $timings[$label] = microtime(true) - $test_started_at;
            echo "  \033[32mPASS\033[0m  {$label}\n";
        } catch (TestSkippedException $exception) {
            // Neither passed nor failed - it never ran. Counted apart from
            // both so an unprivileged run reads as honest rather than green.
            echo "  \033[33mSKIP\033[0m  {$label}\n";
            $skipped[] = ['label' => $label, 'message' => $exception -> getMessage()];
        } catch (AssertionFailedException $exception) {
            echo "  \033[31mFAIL\033[0m  {$label}\n";
            $failures[] = ['label' => $label, 'message' => $exception -> getMessage()];
        } catch (\Throwable $exception) {
            echo "  \033[31mERROR\033[0m {$label}\n";
            $failures[] = ['label' => $label, 'message' => get_class($exception) . ': ' . $exception -> getMessage()];
        }
    }
}

$elapsed_ms = (int) round((microtime(true) - $started_at) * 1000);

echo "\n";

// The twenty that cost the most, so a suite getting slower says which test
// did it rather than only that it happened.
if (getenv('SHOW_SLOWEST') !== false) {
    arsort($timings);

    echo "Slowest:\n";

    foreach (array_slice($timings, 0, 20, true) as $label => $seconds) {
        printf("  %6.2fs  %s\n", $seconds, $label);
    }

    echo "\n";
}

if ($failures !== []) {
    echo "Failures:\n";

    foreach ($failures as $failure) {
        echo "  {$failure['label']}\n    {$failure['message']}\n";
    }

    echo "\n";
}

// Everything that did not run, however it came to be stood down - the tests
// that asked to be, and the whole classes never reached at all.
$skipped_total = count($skipped) + ($skipped_classes['tests'] ?? 0);

if ($skipped_total > 0) {
    echo 'Skipped ' . $skipped_total . ":\n";

    if ($skipped_classes !== []) {
        echo '  ' . $skipped_classes['tests'] . ' in ' . $skipped_classes['classes']
            . " database-backed class(es)\n    " . $skipped_classes['reason'] . "\n";
    }

    foreach ($skipped as $skip) {
        echo "  {$skip['label']}\n    {$skip['message']}\n";
    }

    echo "\n";
}

// Skipped tests leave the denominator rather than joining the numerator: a run
// that skipped a hundred is not a run that passed them.
$ran = $total - count($skipped);
$passed = $ran - count($failures);
echo "{$passed}/{$ran} passed" . ($skipped_total > 0 ? ', ' . $skipped_total . ' skipped' : '') . " ({$elapsed_ms}ms)\n";

exit($failures === [] ? 0 : 1);
