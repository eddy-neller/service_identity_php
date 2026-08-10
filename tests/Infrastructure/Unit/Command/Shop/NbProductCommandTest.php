<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Command\Shop;

use App\Infrastructure\Command\Shop\NbProductCommand;
use App\Infrastructure\Entity\Shop\Category;
use App\Infrastructure\Entity\Shop\Product;
use App\Infrastructure\Persistence\Doctrine\Shop\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class NbProductCommandTest extends TestCase
{
    public function testNoModification(): void
    {
        $tester = $this->getCommandTesterForScenario(5, 5);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Aucune modification effectuée.', $output);
    }

    public function testModificationDetected(): void
    {
        $tester = $this->getCommandTesterForScenario(2, 5);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('modification(s) effectuée(s)', $output);
    }

    public function testVerboseOutput(): void
    {
        $tester = $this->getCommandTesterForScenario(2, 6);
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('DIFFERENCE DETECTED.', $output);
        $this->assertStringContainsString('FROM "2" TO "6"', $output);
    }

    public function testDebugOutput(): void
    {
        $tester = $this->getCommandTesterForScenario(5, 5, ['debug' => true]);
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('NB Items registered', $output);
        $this->assertStringContainsString('NB Items found', $output);
    }

    private function getCommandTesterForScenario(int $currentNbProduct, int $foundNbProduct, array $options = []): CommandTester
    {
        // Mock Category
        $category = $this->createMock(Category::class);
        $category->method('getTitle')->willReturn('TestCategory');
        $category->method('getNbProduct')->willReturn($currentNbProduct);
        $category->method('getId')->willReturn(Uuid::uuid4());

        if ($foundNbProduct !== $currentNbProduct) {
            $category->expects($this->once())->method('setNbProduct')->with($foundNbProduct);
        } else {
            $category->expects($this->never())->method('setNbProduct');
        }

        // Crée un mock d'EntityManager pour le repository
        $dummyEm = $this->createStub(EntityManagerInterface::class);
        $dummyMetadata = new ClassMetadata(Category::class);

        // Mock Category Repository (hérite bien d'EntityRepository)
        $categoryRepo = $this->getMockBuilder(EntityRepository::class)
            ->setConstructorArgs([$dummyEm, $dummyMetadata])
            ->onlyMethods(['findAll'])
            ->getMock();
        $categoryRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([$category]);

        // Mock Product Repository
        $productRepo = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['countNbProductByCategory'])
            ->getMock();
        $productRepo->expects($this->once())
            ->method('countNbProductByCategory')
            ->willReturn($foundNbProduct);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))
            ->method('getRepository')
            ->willReturnMap([
                [Category::class, $categoryRepo],
                [Product::class, $productRepo],
            ]);

        if (!($options['debug'] ?? false)) {
            $em->expects($this->once())->method('flush');
        } else {
            $em->expects($this->never())->method('flush');
        }

        // Instancie la commande
        $command = new NbProductCommand($em);

        return new CommandTester($command);
    }
}
