<?php

declare(strict_types=1);

namespace App\Tests\Domain\SharedKernel\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Garde-fou : chaque Entité/Agrégat (`Model/`) et Value Object (`ValueObject/`)
 * de la couche Domain doit avoir son test unitaire.
 *
 * Le build casse dès qu'un Model ou un VO est livré sans test, quel que soit le bounded context.
 *
 * Le mapping inverse catégorie et sous-contexte par rapport à `src/` :
 *   src   : src/Domain/<Ctx>/<SubContext?>/<Category>/<Name>.php
 *   test  : tests/Domain/<Ctx>/Unit/<Category>/<SubContext?>/<Name>Test.php
 *
 * **Pas de liste d'exclusion.** Elle n'avait qu'un usager, `Order` / `OrderLine`, du code mort
 * parti avec le contexte `Shop`. Une trappe pré-percée finit toujours par servir : en rajouter
 * une doit rester un geste visible en diff, pas remplir un trou déjà prêt.
 */
final class DomainTestCoverageTest extends TestCase
{
    public function testEveryEntityHasATest(): void
    {
        $this->assertModelIsFullyTested('Model');
    }

    public function testEveryValueObjectHasATest(): void
    {
        $this->assertModelIsFullyTested('ValueObject');
    }

    private function assertModelIsFullyTested(string $category): void
    {
        $missing = [];

        foreach ($this->domainContexts() as $context => $paths) {
            $sourceFiles = $this->findFiles($paths['src'], '#/' . $category . '/[^/]+\.php$#');

            foreach ($sourceFiles as $file) {
                $fqcn = $this->classFromFile($file, $paths['src'], 'App\\Domain\\' . $context . '\\');
                $expectedTestFile = $this->expectedTestFile($file, $paths['src'], $paths['tests']);

                if (!is_file($expectedTestFile)) {
                    $missing[] = sprintf('%s (expected %s)', $fqcn, $expectedTestFile);
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            sprintf("Missing domain %s test(s):\n  - %s", $category, implode("\n  - ", $missing)),
        );
    }

    /**
     * Discovers every bounded context under src/Domain/.
     *
     * @return array<string, array{src: string, tests: string}>
     */
    private function domainContexts(): array
    {
        $projectDir = dirname(__DIR__, 4);
        $domainDir = $projectDir . '/src/Domain';
        $testsDir = $projectDir . '/tests/Domain';
        $contexts = [];

        foreach (scandir($domainDir) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $src = $domainDir . '/' . $entry;

            if (is_dir($src)) {
                $contexts[$entry] = [
                    'src' => $src,
                    'tests' => $testsDir . '/' . $entry . '/Unit',
                ];
            }
        }

        return $contexts;
    }

    /**
     * Maps a source file to its expected test file, swapping category and sub-context, e.g.
     * src/Domain/User/ValueObject/Identity/UserId.php
     * → tests/Domain/User/Unit/ValueObject/Identity/UserIdTest.php.
     */
    private function expectedTestFile(string $file, string $srcDir, string $testsDir): string
    {
        $relative = substr($file, strlen($srcDir) + 1, -strlen('.php'));
        $segments = explode('/', $relative);
        $name = array_pop($segments);

        $category = null;
        $subContext = [];

        foreach ($segments as $segment) {
            if (null === $category && in_array($segment, ['Model', 'ValueObject'], true)) {
                $category = $segment;

                continue;
            }

            $subContext[] = $segment;
        }

        $parts = array_filter([$category, ...$subContext, $name . 'Test']);

        return $testsDir . '/' . implode('/', $parts) . '.php';
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
