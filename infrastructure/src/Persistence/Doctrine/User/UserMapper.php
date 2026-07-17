<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Domain\User\Model\User as DomainUser;
use App\Domain\User\ValueObject\Avatar;
use App\Domain\User\ValueObject\EmailAddress;
use App\Domain\User\ValueObject\Firstname;
use App\Domain\User\ValueObject\Lastname;
use App\Domain\User\ValueObject\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Domain\User\ValueObject\Security\RoleSet;
use App\Domain\User\ValueObject\Security\UserStatus;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\Username;
use App\Infrastructure\Entity\User\User as DoctrineUser;
use Ramsey\Uuid\Uuid;

final class UserMapper
{
    public function toDomain(DoctrineUser $entity): DomainUser
    {
        return DomainUser::reconstitute(
            id: UserId::fromString($entity->getId()->toString()),
            username: Username::fromString($entity->getUsername()),
            email: EmailAddress::fromString($entity->getEmail()),
            password: HashedPassword::fromString($entity->getPassword()),
            roles: RoleSet::fromArray($entity->getRoles()),
            status: UserStatus::fromInt($entity->getStatus()),
            security: $entity->getSecurity(),
            activeEmail: $entity->getActiveEmail(),
            resetPassword: $entity->getResetPassword(),
            preferences: Preferences::fromArray($entity->getPreferences() ?? []),
            avatar: new Avatar(
                fileName: $entity->getAvatarName(),
            ),
            lastVisit: $entity->getLastVisit(),
            loginCount: $entity->getNbLogin(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            firstname: $entity->firstname ? Firstname::fromString($entity->firstname) : null,
            lastname: $entity->lastname ? Lastname::fromString($entity->lastname) : null,
        );
    }

    public function toDoctrine(DomainUser $user, ?DoctrineUser $entity = null): DoctrineUser
    {
        $entity = $entity ?? new DoctrineUser();

        $entity->setId(Uuid::fromString($user->getId()->toString()));
        $entity->setUsername($user->getUsername()->toString());
        $entity->firstname = $user->getFirstname()?->toString();
        $entity->lastname = $user->getLastname()?->toString();
        $entity->setEmail($user->getEmail()->toString());
        $entity->setPassword($user->getPassword()->toString());
        $entity->setRoles($user->getRoles()->all());
        $entity->setStatus($user->getStatus()->toInt());
        $entity->setSecurity($user->getSecurity());
        $entity->setActiveEmail($user->getActiveEmail());
        $entity->setResetPassword($user->getResetPassword());
        $entity->setPreferences($user->getPreferences()->toArray());
        $entity->setAvatarName($user->getAvatar()->fileName());
        $entity->setLastVisit($user->getLastVisit());
        $entity->setNbLogin($user->getLoginCount());
        $entity->setCreatedAt($user->getCreatedAt());
        $entity->setUpdatedAt($user->getUpdatedAt());

        return $entity;
    }
}
