<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Unit\Symfony\Service\Notification\User;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Firstname;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Symfony\Service\Notification\Mailer\Mailer;
use App\Infrastructure\Symfony\Service\Notification\User\UserNotifier;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserNotifierTest extends TestCase
{
    private const string USER_ID = '550e8400-e29b-41d4-a716-446655440000';

    /** @var TranslatorInterface&MockObject */
    private TranslatorInterface $translator;

    /** @var ParameterBagInterface&MockObject */
    private ParameterBagInterface $parameterBag;

    /** @var Mailer&MockObject */
    private Mailer $mailer;

    /** @var LockFactory&MockObject */
    private LockFactory $lockFactory;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    private UserNotifier $userNotifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->mailer = $this->createMock(Mailer::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->userNotifier = new UserNotifier(
            $this->translator,
            $this->parameterBag,
            $this->mailer,
            $this->lockFactory,
            $this->logger,
        );
    }

    public function testSendActivationEmailDirectlyFromTheDomainEventWorker(): void
    {
        $user = $this->createUser('en');
        $lock = $this->acquiredLock();
        $encodedToken = 'encoded-token-123';
        $expectedLink = 'https://example.com/activate/?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'en')
            ->willReturn('Account Activation Required');
        $this->configureParameters('mailerFrontLinkRegisterValidation', 'https://example.com/activate/', ['en', 'fr']);
        $this->expectLockForToken($lock, 'activation_email', $encodedToken);
        $this->mailer->expects($this->once())
            ->method('sendEmail')
            ->with(
                'test@example.com',
                'Account Activation Required',
                'emails/user/register-activation.html.twig',
                [
                    'username' => 'testuser',
                    'link' => $expectedLink,
                    'userLocale' => 'en',
                ],
            );
        $this->logger->expects($this->never())->method('info');

        $this->userNotifier->sendActivationEmail($user, $encodedToken);
    }

    public function testSendResetPasswordEmailDirectlyFromTheDomainEventWorker(): void
    {
        $user = $this->createUser('fr');
        $lock = $this->acquiredLock();
        $encodedToken = 'encoded-token-456';
        $expectedLink = 'https://example.com/reset/?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.reset.password.title', [], 'messages', 'fr')
            ->willReturn('Password Reset Request');
        $this->configureParameters('mailerFrontLinkResetPassword', 'https://example.com/reset/', ['en', 'fr']);
        $this->expectLockForToken($lock, 'password_reset_email', $encodedToken);
        $this->mailer->expects($this->once())
            ->method('sendEmail')
            ->with(
                'test@example.com',
                'Password Reset Request',
                'emails/user/reset-password.html.twig',
                [
                    'username' => 'testuser',
                    'link' => $expectedLink,
                    'userLocale' => 'fr',
                ],
            );
        $this->logger->expects($this->never())->method('info');

        $this->userNotifier->sendResetPasswordEmail($user, $encodedToken);
    }

    public function testItLogsAndSkipsAnIdenticalMailAlreadyBeingSent(): void
    {
        $user = $this->createUser('en');
        $lock = $this->createMock(SharedLockInterface::class);

        $this->translator->expects($this->once())->method('trans')->willReturn('Account Activation Required');
        $this->configureParameters('mailerFrontLinkRegisterValidation', 'https://example.com/activate/', ['en']);
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with($this->callback(static fn (mixed $key): bool => is_string($key)), 300.0)
            ->willReturn($lock);
        $lock->expects($this->once())->method('acquire')->willReturn(false);
        $lock->expects($this->never())->method('release');
        $this->mailer->expects($this->never())->method('sendEmail');
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Duplicate user mail discarded, an identical one is already in flight.',
                [
                    'user_id' => self::USER_ID,
                    'template' => 'emails/user/register-activation.html.twig',
                ],
            );

        $this->userNotifier->sendActivationEmail($user, 'encoded-token');
    }

    public function testRotatedTokensUseDifferentLockKeys(): void
    {
        $user = $this->createUser('en');
        $firstLock = $this->acquiredLock();
        $secondLock = $this->acquiredLock();
        $keys = [];

        $this->translator->expects($this->exactly(2))->method('trans')->willReturn('Account Activation Required');
        $this->parameterBag->expects($this->exactly(6))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkRegisterValidation', 'https://example.com/activate/'],
                ['app.enabled_locales', ['en']],
                ['app.default_locale', 'en'],
            ]);
        $this->lockFactory->expects($this->exactly(2))
            ->method('createLock')
            ->willReturnCallback(static function (string $key, float $ttl) use (&$keys, $firstLock, $secondLock): SharedLockInterface {
                self::assertSame(300.0, $ttl);
                $keys[] = $key;

                return 1 === count($keys) ? $firstLock : $secondLock;
            });
        $this->mailer->expects($this->exactly(2))->method('sendEmail');
        $this->logger->expects($this->never())->method('info');

        $this->userNotifier->sendActivationEmail($user, 'token-1');
        $this->userNotifier->sendActivationEmail($user, 'token-2');

        self::assertNotSame($keys[0], $keys[1]);
        self::assertStringNotContainsString('token-1', $keys[0]);
        self::assertStringNotContainsString('token-2', $keys[1]);
    }

    public function testItFallsBackToTheDefaultLocaleWhenThePreferenceIsDisabled(): void
    {
        $user = $this->createUser('fr');
        $lock = $this->acquiredLock();

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'en')
            ->willReturn('Account Activation Required');
        $this->configureParameters('mailerFrontLinkRegisterValidation', 'https://example.com/activate/', ['en']);
        $this->expectLockForToken($lock, 'activation_email', 'encoded-token');
        $this->mailer->expects($this->once())
            ->method('sendEmail')
            ->with(
                'test@example.com',
                'Account Activation Required',
                'emails/user/register-activation.html.twig',
                $this->callback(static fn (array $context): bool => 'en' === $context['userLocale']),
            );
        $this->logger->expects($this->never())->method('info');

        $this->userNotifier->sendActivationEmail($user, 'encoded-token');
    }

    private function acquiredLock(): SharedLockInterface&MockObject
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        return $lock;
    }

    private function expectLockForToken(SharedLockInterface $lock, string $channel, string $token): void
    {
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturnCallback(static function (string $key, float $ttl) use ($channel, $token, $lock): SharedLockInterface {
                self::assertSame(300.0, $ttl);
                self::assertStringStartsWith('user.' . $channel . '.' . self::USER_ID . '.', $key);
                self::assertStringNotContainsString($token, $key);

                return $lock;
            });
    }

    private function configureParameters(string $linkParameter, string $link, array $enabledLocales): void
    {
        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                [$linkParameter, $link],
                ['app.enabled_locales', $enabledLocales],
                ['app.default_locale', 'en'],
            ]);
    }

    private function createUser(string $lang): User
    {
        return User::register(
            id: UserId::fromString(self::USER_ID),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: HashedPassword::fromString('hashed-password'),
            preferences: Preferences::fromArray(['lang' => $lang]),
            now: new DateTimeImmutable(),
            firstname: Firstname::fromString('John'),
        );
    }
}
