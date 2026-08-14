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
 *
 * **Pas de liste d'exclusion.** Elle n'avait que les cinq States du panier pour usagers, partis
 * avec le contexte `Shop`. Une trappe pré-percée finit toujours par servir : en rajouter une doit
 * rester un geste visible en diff, pas remplir un trou déjà prêt.
 */
final class StateTestCoverageTest extends TestCase
{
    public function testEveryStateHasATest(): void
    {
        $srcDir = dirname(__DIR__, 5) . '/src/Presentation';
        $stateTestsDir = dirname(__DIR__, 2) . '/State';

        $missing = [];

        foreach ($this->findFiles($srcDir, '#/State/.*(Provider|Processor)\.php$#') as $file) {
            $fqcn = $this->classFromFile($file, $srcDir, 'App\\Presentation\\');
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
