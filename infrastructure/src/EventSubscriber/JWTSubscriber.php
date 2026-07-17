<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Shared\CQRS\Command\CommandBusInterface;
use App\Application\User\Port\UserRepositoryInterface;
use App\Application\User\UseCase\Command\RegisterWrongPasswordAttempt\RegisterWrongPasswordAttemptCommand;
use App\Application\User\UseCase\Command\ResetWrongPasswordAttempts\ResetWrongPasswordAttemptsCommand;
use App\Domain\User\ValueObject\EmailAddress;
use App\Infrastructure\Entity\User\User;
use App\Infrastructure\Service\InfoCodes;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class JWTSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private CommandBusInterface $commandBus,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => ['onAuthenticationSuccessResponse'],
            Events::AUTHENTICATION_FAILURE => ['onAuthenticationFailureResponse'],
            Events::JWT_CREATED => ['onJWTCreated'],
            Events::JWT_INVALID => ['onJWTInvalid'],
            Events::JWT_NOT_FOUND => ['onJWTNotFound'],
            Events::JWT_EXPIRED => ['onJWTExpired'],
        ];
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setNbLogin($user->getNbLogin() + 1);
        $user->setLastVisit(new DateTimeImmutable());

        $this->em->flush();

        // Reset wrong password attempts after a successful login
        $this->commandBus->dispatch(new ResetWrongPasswordAttemptsCommand(
            userId: (string) $user->getId(),
        ));
    }

    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event): void
    {
        $email = $this->extractUsernameFromRequest($event->getRequest());
        if ('' === $email) {
            $event->setResponse(new JWTAuthenticationFailureResponse(InfoCodes::JWT['BAD_CREDENTIALS']));

            return;
        }

        $this->commandBus->dispatch(new RegisterWrongPasswordAttemptCommand(
            email: $email,
        ));

        $user = $this->userRepository->findByEmail(EmailAddress::fromString($email));
        if (null !== $user && $user->isLocked()) {
            $event->setResponse(new JsonResponse(
                [
                    'code' => Response::HTTP_LOCKED,
                    'message' => InfoCodes::JWT['ACCOUNT_LOCKED'],
                ],
                Response::HTTP_LOCKED
            ));

            return;
        }

        $event->setResponse(new JWTAuthenticationFailureResponse(InfoCodes::JWT['BAD_CREDENTIALS']));
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload['id'] = $user->getId();
        $payload['username'] = $user->getUsername();

        $event->setData($payload);
    }

    public function onJWTInvalid(JWTInvalidEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['INVALID_TOKEN']);

        $event->setResponse($response);
    }

    public function onJWTNotFound(JWTNotFoundEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['MISSING_TOKEN']);

        $event->setResponse($response);
    }

    public function onJWTExpired(JWTExpiredEvent $event): void
    {
        $response = new JWTAuthenticationFailureResponse(InfoCodes::JWT['EXPIRED_TOKEN']);

        $event->setResponse($response);
    }

    private function extractUsernameFromRequest(?Request $request): string
    {
        if (!$request instanceof Request) {
            return '';
        }

        $contentType = $request->getContentTypeFormat();

        if ('json' === $contentType) {
            $data = json_decode($request->getContent(), true);

            if (is_array($data) && isset($data['email']) && is_string($data['email'])) {
                return trim($data['email']);
            }
        }

        $email = $request->get('email');

        return is_string($email) ? trim($email) : '';
    }
}
