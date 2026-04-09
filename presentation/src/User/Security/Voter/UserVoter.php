<?php

declare(strict_types=1);

namespace App\Presentation\User\Security\Voter;

use App\Domain\User\Security\ValueObject\RoleSet;
use App\Infrastructure\Entity\User\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

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

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        return match ($attribute) {
            'user:item:write' => $this->security->isGranted(RoleSet::ROLE_ADMIN) || $user->getId()->toString() === $subject->getId()->toString(),
            default => false,
        };
    }
}
