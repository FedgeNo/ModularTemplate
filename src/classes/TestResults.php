<?php

declare(strict_types=1);

/**
 * The output of running both test suites, for the admin's Tests page. Each
 * runner is invoked as its own process and its console output shown verbatim,
 * so what the page reports is exactly what the command line would have said.
 *
 * The runs happen under the web server's account, which is not root - so the
 * database-backed tests skip themselves rather than building the throwaway
 * database they need. Reading this page cannot touch the live data.
 */
class TestResults extends Div
{
    public ?string $class = 'TestResults';

    public function toDOM(): \DOMElement
    {
        $this -> addSuite('PHP', 'php ' . escapeshellarg(__DIR__ . '/../../bin/run-tests.php'));

        // JavaScript needs Node and its one dev dependency; without either
        // there is nothing to report rather than a failure to report.
        $node = self::nodeBinary();

        if ($node === null) {
            $this -> addContent(new Paragraph(
                'No Node.js the web server can use. Install it system-wide (e.g. dnf install nodejs) to enable '
                . 'JavaScript tests - a version manager inside someone\'s home directory (nvm) is invisible here.'
            ));

            return parent::toDOM();
        }

        if (!is_dir(__DIR__ . '/../../node_modules')) {
            $this -> addContent(new Paragraph('Run `npm install` in the document root to enable JavaScript tests.'));

            return parent::toDOM();
        }

        $this -> addSuite('JS', escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/../../bin/run-js-tests.js'));

        return parent::toDOM();
    }

    /**
     * Runs one suite and adds its verdict and output. A Node that dies before
     * any test runs is reported as what it is rather than as a failing suite -
     * a hardened web server can refuse it the memory V8 wants, which says
     * nothing about the tests.
     */
    private function addSuite(string $name, string $command): void
    {
        $lines = [];
        $exit_code = 0;
        exec($command . ' 2>&1', $lines, $exit_code);

        $output = self::withoutColour(implode("\n", $lines));

        if ($exit_code !== 0 && str_contains($output, 'V8_Fatal')) {
            $this -> addContent(new Paragraph(
                'JavaScript tests could not be started. Node.js encountered a fatal error, '
                . 'most likely due to SELinux restrictions on the web server.'
            ));

            return;
        }

        $this -> addContent(new TestResultsBadge($name, $exit_code === 0));
        $this -> addContent(new TestResultsOutput($output));
    }

    /**
     * A node binary this process can actually run, or null. PATH under
     * PHP-FPM is a stub, so "not on PATH" proves nothing - the usual install
     * locations are probed directly before giving up.
     */
    private static function nodeBinary(): ?string
    {
        $on_path = trim((string) shell_exec('command -v node 2>/dev/null'));

        foreach (array_filter([$on_path, '/usr/bin/node', '/usr/local/bin/node', '/opt/homebrew/bin/node']) as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** The runners colour their output for a terminal; this is not one. */
    private static function withoutColour(string $output): string
    {
        return (string) preg_replace('/\e\[[0-9;]*m/', '', $output);
    }
}
