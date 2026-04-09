<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\User;

use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Application\User\Port\UserNotifierInterface;
use App\Domain\User\Model\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserNotifier implements UserNotifierInterface
{
    private const string TEMPLATE_REGISTER_ACTIVATION = 'emails/user/register-activation.html.twig';

    private const string TEMPLATE_RESET_PASSWORD = 'emails/user/reset-password.html.twig';

    public function __construct(
        private TranslatorInterface $translator,
        private ParameterBagInterface $bag,
        private MessageBusInterface $bus,
    ) {
    }

    public function sendActivationEmail(User $user, string $encodedToken): void
    {
        $this->sendUserMail(
            user: $user,
            subjectKey: 'user.register.activation.title',
            template: self::TEMPLATE_REGISTER_ACTIVATION,
            frontLinkParamKey: 'mailerFrontLinkRegisterValidation',
            encodedToken: $encodedToken,
        );
    }

    public function sendResetPasswordEmail(User $user, string $encodedToken): void
    {
        $this->sendUserMail(
            user: $user,
            subjectKey: 'user.reset.password.title',
            template: self::TEMPLATE_RESET_PASSWORD,
            frontLinkParamKey: 'mailerFrontLinkResetPassword',
            encodedToken: $encodedToken,
        );
    }

    private function sendUserMail(
        User $user,
        string $subjectKey,
        string $template,
        string $frontLinkParamKey,
        string $encodedToken,
    ): void {
        $locale = $this->resolveLocale($user);
        $subject = $this->translator->trans($subjectKey, [], 'messages', $locale);
        $base = (string) $this->bag->get($frontLinkParamKey);
        $link = $base . '?token=' . urlencode($encodedToken);

        $payload = [
            'firstname' => $user->getFirstname()?->toString(),
            'link' => $link,
            'userLocale' => $locale,
        ];

        $this->bus->dispatch(new SendEmailMessage(
            to: $user->getEmail()->toString(),
            subject: $subject,
            template: $template,
            context: $payload,
        ));
    }

    private function resolveLocale(User $user): string
    {
        $allowed = $this->bag->get('app.enabled_locales');
        $default = (string) $this->bag->get('app.default_locale');

        if (!is_array($allowed)) {
            $allowed = [$default];
        }

        $lang = $user->getPreferences()->getLang();

        return (in_array($lang, $allowed, true)) ? $lang : $default;
    }
}
