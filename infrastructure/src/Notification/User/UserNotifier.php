<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\User;

use App\Domain\User\Model\User;
use App\Infrastructure\Notification\Mailer\Mailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserNotifier implements UserNotifierInterface
{
    private const string TEMPLATE_REGISTER_ACTIVATION = 'emails/user/register-activation.html.twig';

    private const string TEMPLATE_RESET_PASSWORD = 'emails/user/reset-password.html.twig';

    private const string CHANNEL_ACTIVATION = 'activation_email';

    private const string CHANNEL_PASSWORD_RESET = 'password_reset_email';

    /**
     * Durée de vie du verrou de déduplication. Le middleware le relâche dès que l'envoi a
     * réussi : cette valeur ne sert que de filet si un worker meurt en le tenant.
     */
    private const float DEDUPLICATION_TTL = 300.0;

    public function __construct(
        private TranslatorInterface $translator,
        private ParameterBagInterface $bag,
        private Mailer $mailer,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
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
            deduplicationKey: $this->mailDeduplicationKey(self::CHANNEL_ACTIVATION, $user, $encodedToken),
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
            deduplicationKey: $this->mailDeduplicationKey(self::CHANNEL_PASSWORD_RESET, $user, $encodedToken),
        );
    }

    private function sendUserMail(
        User $user,
        string $subjectKey,
        string $template,
        string $frontLinkParamKey,
        string $encodedToken,
        string $deduplicationKey,
    ): void {
        $locale = $this->resolveLocale($user);
        $subject = $this->translator->trans($subjectKey, [], 'messages', $locale);
        $base = (string) $this->bag->get($frontLinkParamKey);
        $link = $base . '?token=' . $encodedToken;

        $payload = [
            'username' => $user->getUsername()->toString(),
            'link' => $link,
            'userLocale' => $locale,
        ];

        $lock = $this->lockFactory->createLock($deduplicationKey, self::DEDUPLICATION_TTL);

        if (!$lock->acquire()) {
            $this->logger->info('Duplicate user mail discarded, an identical one is already in flight.', [
                'user_id' => $user->getId()->toString(),
                'template' => $template,
            ]);

            return;
        }

        try {
            // Ce code s'exécute déjà dans le worker domain_events. Le lien contenant le token
            // reste donc uniquement en mémoire et n'est jamais sérialisé dans RabbitMQ ou failed.
            $this->mailer->sendEmail(
                to: $user->getEmail()->toString(),
                subject: $subject,
                template: $template,
                context: $payload,
            );
        } finally {
            $lock->release();
        }
    }

    private function mailDeduplicationKey(string $channel, User $user, string $encodedToken): string
    {
        return sprintf(
            'user.%s.%s.%s',
            $channel,
            $user->getId()->toString(),
            substr(hash('sha256', $encodedToken), 0, 32),
        );
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
