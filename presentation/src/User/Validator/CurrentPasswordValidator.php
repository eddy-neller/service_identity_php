<?php

declare(strict_types=1);

namespace App\Presentation\User\Validator;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CurrentPasswordValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CurrentPassword) {
            throw new UnexpectedTypeException($constraint, CurrentPassword::class);
        }

        $user = $this->security->getUser();

        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            $this->context->buildViolation($constraint->message)
                ->setCode(CurrentPassword::INVALID_PASSWORD_ERROR)
                ->addViolation();

            return;
        }

        if (!$this->passwordHasher->isPasswordValid($user, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setCode(CurrentPassword::INVALID_PASSWORD_ERROR)
                ->addViolation();
        }
    }
}
