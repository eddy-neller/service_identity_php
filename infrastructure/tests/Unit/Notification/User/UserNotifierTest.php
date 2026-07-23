<?php

declare(strict_types=1);

namespace App\Infrastructure\Tests\Unit\Notification\User;

use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\Identity\EmailAddress;
use App\Domain\User\ValueObject\Identity\UserId;
use App\Domain\User\ValueObject\Identity\Username;
use App\Domain\User\ValueObject\Profile\Firstname;
use App\Domain\User\ValueObject\Profile\Preferences;
use App\Domain\User\ValueObject\Security\HashedPassword;
use App\Infrastructure\Notification\User\UserNotifier;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserNotifierTest extends KernelTestCase
{
    /** @var TranslatorInterface&MockObject */
    private TranslatorInterface $translator;

    /** @var ParameterBagInterface&MockObject */
    private ParameterBagInterface $parameterBag;

    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $bus;

    private UserNotifier $userNotifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->userNotifier = new UserNotifier(
            $this->translator,
            $this->parameterBag,
            $this->bus,
        );
    }

    public function testSendActivationEmail(): void
    {
        $user = $this->createUser('en');
        $subject = 'Account Activation Required';
        $baseLink = 'https://example.com/activate/';
        $encodedToken = 'encoded-token-123';
        $expectedLink = $baseLink . '?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'en')
            ->willReturn($subject);

        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkRegisterValidation', $baseLink],
                ['app.enabled_locales', ['en', 'fr']],
                ['app.default_locale', 'en'],
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (SendEmailMessage $message) use ($subject, $expectedLink): bool {
                return 'test@example.com' === $message->to
                    && $subject === $message->subject
                    && 'emails/user/register-activation.html.twig' === $message->template
                    && [
                        'username' => 'testuser',
                        'link' => $expectedLink,
                        'userLocale' => 'en',
                    ] === $message->context;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->userNotifier->sendActivationEmail($user, $encodedToken);
    }

    public function testSendResetPasswordEmail(): void
    {
        $user = $this->createUser('en');
        $subject = 'Password Reset Request';
        $baseLink = 'https://example.com/reset/';
        $encodedToken = 'encoded-token-456';
        $expectedLink = $baseLink . '?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.reset.password.title', [], 'messages', 'en')
            ->willReturn($subject);

        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkResetPassword', $baseLink],
                ['app.enabled_locales', ['en', 'fr']],
                ['app.default_locale', 'en'],
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (SendEmailMessage $message) use ($subject, $expectedLink): bool {
                return 'test@example.com' === $message->to
                    && $subject === $message->subject
                    && 'emails/user/reset-password.html.twig' === $message->template
                    && [
                        'username' => 'testuser',
                        'link' => $expectedLink,
                        'userLocale' => 'en',
                    ] === $message->context;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->userNotifier->sendResetPasswordEmail($user, $encodedToken);
    }

    public function testSendUserMailWithCustomLanguage(): void
    {
        $user = $this->createUser('fr');
        $subject = 'Activation de compte requise';
        $baseLink = 'https://example.com/activate/';
        $encodedToken = 'encoded-token-789';
        $expectedLink = $baseLink . '?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'fr')
            ->willReturn($subject);

        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkRegisterValidation', $baseLink],
                ['app.enabled_locales', ['en', 'fr']],
                ['app.default_locale', 'en'],
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (SendEmailMessage $message) use ($subject, $expectedLink): bool {
                return 'test@example.com' === $message->to
                    && $subject === $message->subject
                    && 'emails/user/register-activation.html.twig' === $message->template
                    && [
                        'username' => 'testuser',
                        'link' => $expectedLink,
                        'userLocale' => 'fr',
                    ] === $message->context;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->userNotifier->sendActivationEmail($user, $encodedToken);
    }

    public function testSendUserMailWithFallbackLanguage(): void
    {
        // Préférence utilisateur 'fr' (valide), mais non activée côté déploiement
        // => le notifier doit retomber sur le default 'en'
        $user = $this->createUser('fr');
        $subject = 'Account Activation Required';
        $baseLink = 'https://example.com/activate/';
        $encodedToken = 'encoded-token-abc';
        $expectedLink = $baseLink . '?token=' . urlencode($encodedToken);

        // fallback attendu => 'en'
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'en')
            ->willReturn($subject);

        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkRegisterValidation', $baseLink],
                ['app.enabled_locales', ['en']],
                ['app.default_locale', 'en'],
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (SendEmailMessage $message) use ($subject, $expectedLink): bool {
                return 'test@example.com' === $message->to
                    && $subject === $message->subject
                    && 'emails/user/register-activation.html.twig' === $message->template
                    && [
                        'username' => 'testuser',
                        'link' => $expectedLink,
                        'userLocale' => 'en',
                    ] === $message->context;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->userNotifier->sendActivationEmail($user, $encodedToken);
    }

    public function testResolveLocaleFallsBackWhenEnabledLocalesIsNotArray(): void
    {
        // Préférence utilisateur 'fr', mais enabled_locales n'est pas un tableau
        // donc le système doit utiliser le default 'en'
        $user = $this->createUser('fr');
        $subject = 'Account Activation Required';
        $baseLink = 'https://example.com/activate/';
        $encodedToken = 'encoded-token-mno';
        $expectedLink = $baseLink . '?token=' . urlencode($encodedToken);

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('user.register.activation.title', [], 'messages', 'en')
            ->willReturn($subject);

        // enabled_locales renvoie une chaîne -> $allowed = [$default]
        // 'fr' n'est pas dans [$default], donc fallback à 'en'
        $this->parameterBag->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['mailerFrontLinkRegisterValidation', $baseLink],
                ['app.enabled_locales', 'en'], // pas un tableau -> $allowed = [$default]
                ['app.default_locale', 'en'],
            ]);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (SendEmailMessage $message) use ($subject, $expectedLink): bool {
                return 'test@example.com' === $message->to
                    && $subject === $message->subject
                    && 'emails/user/register-activation.html.twig' === $message->template
                    && [
                        'username' => 'testuser',
                        'link' => $expectedLink,
                        'userLocale' => 'en',
                    ] === $message->context;
            }))
            ->willReturnCallback(static fn (SendEmailMessage $message): Envelope => new Envelope($message));

        $this->userNotifier->sendActivationEmail($user, $encodedToken);
    }

    private function createUser(?string $lang): User
    {
        $preferences = Preferences::fromArray($lang ? ['lang' => $lang] : []);

        return User::register(
            id: UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            username: Username::fromString('testuser'),
            email: EmailAddress::fromString('test@example.com'),
            password: HashedPassword::fromString('hashed-password'),
            preferences: $preferences,
            now: new DateTimeImmutable(),
            firstname: Firstname::fromString('John'),
        );
    }
}
