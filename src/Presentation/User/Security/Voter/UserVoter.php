<?php

declare(strict_types=1);

namespace App\Presentation\User\Security\Voter;

use App\Domain\User\ValueObject\Access\RoleSet;
use App\Infrastructure\Entity\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    private const array GROUPS = [
        'user:item:write',
    ];

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, self::GROUPS, true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface || !method_exists($user, 'getId')) {
            // the user must be logged in; if not, deny access
            return false;
        }

        $userId = $user->getId();
        if (!is_object($userId) || !method_exists($userId, 'toString')) {
            return false;
        }

        return match ($attribute) {
            'user:item:write' => $this->security->isGranted(RoleSet::ROLE_ADMIN) || $userId->toString() === $subject->getId()->toString(),
            default => false,
        };
    }
}
