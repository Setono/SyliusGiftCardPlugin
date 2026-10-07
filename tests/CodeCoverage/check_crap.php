<?php

declare(strict_types=1);

/*
 * Fails when a method in PHPUnit's Crap4J report has a CRAP index at or above the threshold:
 *
 *     php tests/CodeCoverage/check_crap.php <crap4j.xml> <threshold>
 *
 * `composer check-crap` runs it on the report the code coverage job writes. A method's CRAP index is
 * complexity² × (1 − coverage)³ + complexity, coverage being the share of its lines the tests run: a fully covered
 * method scores its cyclomatic complexity, and an uncovered one complexity² + complexity
 */

if (3 !== $argc || !is_numeric($argv[2]) || (float) $argv[2] <= 0) {
    fwrite(\STDERR, sprintf("Usage: php %s <crap4j.xml> <threshold>\n", $argv[0]));

    exit(2);
}

[, $report, $threshold] = $argv;
$threshold = (float) $threshold;

libxml_use_internal_errors(true);
$document = new DOMDocument();
if (!is_file($report) || !$document->load($report)) {
    fwrite(\STDERR, sprintf(
        "Cannot read the Crap4J report %s. PHPUnit writes it when it runs the unit and the functional suite with a coverage driver (pcov or Xdebug) and --coverage-crap4j=%s\n",
        $report,
        $report,
    ));

    exit(2);
}

$value = static fn (DOMElement $method, string $name): string => trim((string) $method->getElementsByTagName($name)->item(0)?->textContent);

/** @var list<array{float, string}> $methods each method's CRAP index and description */
$methods = [];
foreach ($document->getElementsByTagName('method') as $method) {
    $methods[] = [(float) $value($method, 'crap'), sprintf(
        '%s::%s (CRAP %s, complexity %s, coverage %s%%)',
        $value($method, 'className'),
        $value($method, 'methodName'),
        $value($method, 'crap'),
        $value($method, 'complexity'),
        $value($method, 'coverage'),
    )];
}

if ([] === $methods) {
    fwrite(\STDERR, sprintf("The Crap4J report %s lists no methods\n", $report));

    exit(2);
}

usort($methods, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
$crappy = array_filter($methods, static fn (array $method): bool => $method[0] >= $threshold);

if ([] === $crappy) {
    printf("The CRAP index of all %d methods is below %s. The highest: %s\n", count($methods), $threshold, $methods[0][1]);

    exit(0);
}

printf("Methods with a CRAP index of %s or more (%d of %d):\n\n", $threshold, count($crappy), count($methods));
foreach ($crappy as [, $description]) {
    printf("  %s\n", $description);
}

printf(
    "\nCover each with tests, or split it: fully covered, a method scores its cyclomatic complexity, so one with %s or more paths fails however well it is tested. Closures count toward the method they are written in\n",
    $threshold,
);

exit(1);
