<?php

declare(strict_types=1);

namespace App\Tests\Presentation\Unit\State\Shared;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Garde-fou : chaque State API Platform (Provider/Processor) de la couche Presentation
 * doit avoir son test unitaire. Le build casse dès qu'un Provider/Processor est livré sans test.
 *
 * L'arborescence des tests **reflète** celle de `src/`, en remontant le segment `State` :
 *   src   : src/Presentation/<Context>/State/<rest...>/<Name>.php
 *   test  : tests/Presentation/Unit/State/<Context>/<rest...>/<Name>Test.php
 */
final class StateTestCoverageTest extends TestCase
{
    /**
     * FQCN de States volontairement exclus du garde-fou.
     * À ne renseigner que pour du code non encore branché ; retirer dès qu'il l'est.
     */
    private const array EXCLUDED = [
        // feature/cart en cours : States du sous-domaine Ordering pas encore testés.
        \App\Presentation\Shop\State\Ordering\Cart\CartDeleteProcessor::class,
        \App\Presentation\Shop\State\Ordering\Cart\CartGetProvider::class,
        \App\Presentation\Shop\State\Ordering\Cart\CartLineDeleteProcessor::class,
        \App\Presentation\Shop\State\Ordering\Cart\CartLinePatchProcessor::class,
        \App\Presentation\Shop\State\Ordering\Cart\CartLinePostProcessor::class,
    ];

    public function testEveryStateHasATest(): void
    {
        $srcDir = dirname(__DIR__, 5) . '/src/Presentation';
        $stateTestsDir = dirname(__DIR__, 2) . '/State';

        $missing = [];

        foreach ($this->findFiles($srcDir, '#/State/.*(Provider|Processor)\.php$#') as $file) {
            $fqcn = $this->classFromFile($file, $srcDir, 'App\\Presentation\\');

            if (in_array($fqcn, self::EXCLUDED, true)) {
                continue;
            }

            $expectedTestFile = $this->expectedTestFile($file, $srcDir, $stateTestsDir);

            if (!is_file($expectedTestFile)) {
                $missing[] = sprintf('%s (expected %s)', $fqcn, $expectedTestFile);
            }
        }

        $this->assertSame(
            [],
            $missing,
            sprintf("Missing presentation State test(s):\n  - %s", implode("\n  - ", $missing)),
        );
    }

    /**
     * Mirrors a State source file onto its test, hoisting the `State` segment to the test root:
     * src  src/Presentation/Shop/State/Catalog/Category/CategoryGetProvider.php
     * test tests/Presentation/Unit/State/Shop/Catalog/Category/CategoryGetProviderTest.php.
     */
    private function expectedTestFile(string $file, string $srcDir, string $stateTestsDir): string
    {
        $relative = substr($file, strlen($srcDir) + 1, -strlen('.php'));
        $segments = explode('/', $relative);

        // Drop the first `State` segment; the remaining path mirrors under tests/Unit/State.
        $stateIndex = array_search('State', $segments, true);
        if (false !== $stateIndex) {
            unset($segments[$stateIndex]);
        }

        $segments[array_key_last($segments)] .= 'Test';

        return $stateTestsDir . '/' . implode('/', $segments) . '.php';
    }

    /**
     * @return array<int, string>
     */
    private function findFiles(string $baseDir, string $pattern): array
    {
        if (!is_dir($baseDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir)
        );

        $files = new RegexIterator($iterator, $pattern);
        $results = [];

        foreach ($files as $file) {
            $results[] = $file->getPathname();
        }

        return $results;
    }

    private function classFromFile(string $file, string $baseDir, string $baseNamespace): string
    {
        $relative = substr($file, strlen($baseDir) + 1);

        return $baseNamespace . str_replace(['/', '.php'], ['\\', ''], $relative);
    }
}
