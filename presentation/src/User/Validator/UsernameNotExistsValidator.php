<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Identity\ValueObject\Username;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UsernameNotExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UsernameNotExists) {
            throw new UnexpectedTypeException($constraint, UsernameNotExists::class);
        }

        if (empty($value)) {
            return;
        }

        /* @var string $value */
        $username = Username::fromString($value);
        $existingUser = $this->userRepository->findByUsername($username);

        if (null === $existingUser) {
            return;
        }

        $currentUserId = null;
        $request = $this->requestStack->getCurrentRequest();
        $requestUserId = $request?->attributes->get('id');

        if (is_string($requestUserId) && '' !== $requestUserId) {
            $currentUserId = $requestUserId;
        }

        if ($currentUserId === $existingUser->getId()->toString()) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setCode(UsernameNotExists::SAME_USERNAME_ERROR)
            ->addViolation();
    }
}
