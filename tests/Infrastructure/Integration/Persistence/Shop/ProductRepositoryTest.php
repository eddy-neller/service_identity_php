<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Integration\Persistence\Shop;

use App\Infrastructure\Entity\Shop\Category;
use App\Infrastructure\Persistence\Doctrine\Shop\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ProductRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->repository = $container->get(ProductRepository::class);
    }

    public function testCountNbProductByCategory(): void
    {
        $categoryId = $this->em->getRepository(Category::class)->findOneBy([])->getId()->toString();

        $res = $this->repository->countNbProductByCategory($categoryId);

        $this->assertIsNumeric($res);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->close();
    }
}
