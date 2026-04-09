<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use App\Application\User\Port\UserRepositoryInterface;
use App\Domain\User\Identity\ValueObject\EmailAddress;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class EmailNotExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EmailNotExists) {
            throw new UnexpectedTypeException($constraint, EmailNotExists::class);
        }

        if (empty($value)) {
            return;
        }

        /* @var string $value */
        $email = EmailAddress::fromString($value);
        $existingUser = $this->userRepository->findByEmail($email);

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
            ->setCode(EmailNotExists::SAME_EMAIL_ERROR)
            ->addViolation();
    }
}
