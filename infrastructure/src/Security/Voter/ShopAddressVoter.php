<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\User\ValueObject\Security\RoleSet;
use App\Infrastructure\Entity\Shop\Address;
use App\Infrastructure\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ShopAddressVoter extends Voter
{
    private const string ITEM_READ = 'shop_address:item:read';

    private const string ITEM_WRITE = 'shop_address:item:write';

    private const array GROUPS = [
        self::ITEM_READ,
        self::ITEM_WRITE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, self::GROUPS, true)) {
            return false;
        }

        if (is_string($subject) || $subject instanceof UuidInterface) {
            return true;
        }

        if (is_object($subject)) {
            return property_exists($subject, 'id') || method_exists($subject, 'getId');
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted(RoleSet::ROLE_ADMIN)) {
            return true;
        }

        $addressId = $this->extractId($subject);
        if (null === $addressId) {
            return false;
        }

        $address = $this->em->find(Address::class, $addressId);
        if (!$address instanceof Address) {
            return false;
        }

        return $user->getId()->toString() === $address->getCustomer()->getUserAccountId()->toString();
    }

    private function extractId(mixed $subject): ?string
    {
        if ($subject instanceof UuidInterface) {
            return $subject->toString();
        }

        if (is_object($subject)) {
            if (method_exists($subject, 'getId')) {
                $id = $subject->getId();
                if ($id instanceof UuidInterface) {
                    return $id->toString();
                }

                if (is_string($id) && Uuid::isValid($id)) {
                    return $id;
                }
            }

            if (property_exists($subject, 'id') && is_string($subject->id) && Uuid::isValid($subject->id)) {
                return $subject->id;
            }
        }

        return null;
    }
}
