<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shop\Ordering;

use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\Shop\Port\CartRepositoryInterface;
use App\Domain\Shop\Customer\ValueObject\CustomerId;
use App\Domain\Shop\Ordering\Model\Cart as DomainCart;
use App\Domain\Shop\Ordering\ValueObject\CartId;
use App\Domain\Shop\Ordering\ValueObject\CartLineId;
use App\Infrastructure\Entity\Shop\Cart as DoctrineCart;
use App\Infrastructure\Entity\Shop\CartLine as DoctrineCartLine;
use App\Infrastructure\Entity\Shop\Customer as DoctrineCustomer;
use App\Infrastructure\Entity\Shop\Product as DoctrineProduct;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

final readonly class DoctrineCartRepository implements CartRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UuidGeneratorInterface $uuidGenerator,
        private CartMapper $mapper,
    ) {
    }

    public function nextIdentity(): CartId
    {
        return CartId::fromString($this->uuidGenerator->generate());
    }

    public function nextLineIdentity(): CartLineId
    {
        return CartLineId::fromString($this->uuidGenerator->generate());
    }

    public function findByOwner(CustomerId $ownerId): ?DomainCart
    {
        $entity = $this->findEntityByOwner($ownerId);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function findByOwnerForUpdate(CustomerId $ownerId): ?DomainCart
    {
        $this->em->find(DoctrineCustomer::class, $ownerId->toString(), LockMode::PESSIMISTIC_WRITE);
        $entity = $this->findEntityByOwner($ownerId);
        if (null !== $entity) {
            $this->em->lock($entity, LockMode::PESSIMISTIC_WRITE);
        }

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }

    public function save(DomainCart $cart): void
    {
        $entity = $this->em->find(DoctrineCart::class, $cart->getId()->toString());
        $entity ??= new DoctrineCart();
        $entity->setId(Uuid::fromString($cart->getId()->toString()));
        $entity->setCustomer($this->em->getReference(DoctrineCustomer::class, $cart->getOwnerId()->toString()));
        $entity->setCreatedAt($cart->getCreatedAt());
        $entity->setUpdatedAt($cart->getUpdatedAt());

        $domainLines = [];
        foreach ($cart->getLines() as $line) {
            $domainLines[$line->getId()->toString()] = $line;
        }

        foreach ($entity->getLines()->toArray() as $lineEntity) {
            if (!isset($domainLines[$lineEntity->getId()->toString()])) {
                $entity->removeLine($lineEntity);
            }
        }

        $existing = [];
        foreach ($entity->getLines() as $lineEntity) {
            $existing[$lineEntity->getId()->toString()] = $lineEntity;
        }

        foreach ($domainLines as $id => $line) {
            $lineEntity = $existing[$id] ?? new DoctrineCartLine();
            $lineEntity->setId(Uuid::fromString($id));
            $lineEntity->setProduct($this->em->getReference(DoctrineProduct::class, $line->getProductId()->toString()));
            $lineEntity->setQuantity($line->getQuantity());
            $entity->addLine($lineEntity);
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    private function findEntityByOwner(CustomerId $ownerId): ?DoctrineCart
    {
        $entity = $this->em->getRepository(DoctrineCart::class)->findOneBy([
            'customer' => $ownerId->toString(),
        ]);

        return $entity instanceof DoctrineCart ? $entity : null;
    }
}
