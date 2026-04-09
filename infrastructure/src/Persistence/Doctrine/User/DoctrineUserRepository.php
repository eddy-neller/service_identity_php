<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Application\Shared\Port\EventDispatcherInterface;
use App\Application\Shared\Port\UuidGeneratorInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\ReadModel\UserList;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use App\Domain\User\Identity\ValueObject\UserId;
use App\Domain\User\Identity\ValueObject\Username;
use App\Domain\User\Model\User as DomainUser;
use App\Infrastructure\Entity\User\User as DoctrineUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @codeCoverageIgnore
 */
final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $repository,
        private UserMapper $mapper,
        private UuidGeneratorInterface $uuidGenerator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function nextIdentity(): UserId
    {
        return UserId::fromString($this->uuidGenerator->generate());
    }

    public function list(array $filters, array $orderBy, int $page, int $itemsPerPage): UserList
    {
        $qb = $this->repository->createQueryBuilder('u');

        $this->applyFilters($qb, $filters);
        $this->applyOrdering($qb, $orderBy);

        $offset = max(0, ($page - 1) * $itemsPerPage);
        $qb->setFirstResult($offset)->setMaxResults($itemsPerPage);

        $paginator = new Paginator($qb);
        $totalItems = count($paginator);
        $totalPages = $itemsPerPage > 0 ? (int) ceil($totalItems / $itemsPerPage) : 1;

        $users = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof DoctrineUser) {
                $users[] = $this->mapper->toDomain($entity);
            }
        }

        return new UserList(
            users: $users,
            totalItems: $totalItems,
            totalPages: $totalPages,
        );
    }

    public function save(DomainUser $user): void
    {
        $entity = $this->findEntity($user->getId());
        $entity = $this->mapper->toDoctrine($user, $entity);

        $this->em->persist($entity);
        $this->em->flush();

        $this->dispatchDomainEvents($user);
    }

    public function delete(DomainUser $user): void
    {
        $id = $user->getId()->toString();
        $entity = $this->repository->find($id);

        if (null !== $entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }

        $this->dispatchDomainEvents($user);
    }

    public function findById(UserId $id): ?DomainUser
    {
        $entity = $this->repository->find($id->toString());

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByUsername(Username $username): ?DomainUser
    {
        $entity = $this->repository->findOneBy(['username' => $username->toString()]);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByEmail(EmailAddress $email): ?DomainUser
    {
        $entity = $this->repository->findOneBy(['email' => $email->toString()]);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByActivationToken(string $token): ?DomainUser
    {
        $entity = $this->repository->findInJsonField('activeEmail', 'token', $token);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByResetPasswordToken(string $token): ?DomainUser
    {
        $entity = $this->repository->findInJsonField('resetPassword', 'token', $token);

        return $entity ? $this->mapper->toDomain($entity) : null;
    }

    private function dispatchDomainEvents(DomainUser $user): void
    {
        $events = $user->getDomainEvents();

        if (!empty($events)) {
            $this->eventDispatcher->dispatchAll($events);
            $user->clearDomainEvents();
        }
    }

    private function findEntity(?UserId $id): ?DoctrineUser
    {
        if (null === $id) {
            return null;
        }

        return $this->repository->find($id->toString());
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $username = $filters['username'] ?? null;
        if (is_string($username) && '' !== trim($username)) {
            $qb->andWhere('u.username LIKE :username')
                ->setParameter('username', '%' . trim($username) . '%');
        }

        $email = $filters['email'] ?? null;
        if (is_string($email) && '' !== trim($email)) {
            $qb->andWhere('u.email LIKE :email')
                ->setParameter('email', '%' . trim($email) . '%');
        }
    }

    private function applyOrdering(QueryBuilder $qb, array $orderBy): void
    {
        $allowedFields = [
            'username' => 'u.username',
            'email' => 'u.email',
            'createdAt' => 'u.createdAt',
        ];

        foreach ($orderBy as $field => $direction) {
            if (!isset($allowedFields[$field])) {
                continue;
            }

            $normalizedDirection = strtoupper((string) $direction);
            if (!in_array($normalizedDirection, ['ASC', 'DESC'], true)) {
                $normalizedDirection = 'ASC';
            }

            $qb->addOrderBy($allowedFields[$field], $normalizedDirection);
        }
    }
}
