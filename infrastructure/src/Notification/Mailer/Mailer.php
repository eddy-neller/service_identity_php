<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification\Mailer;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @codeCoverageIgnore
 */
readonly class Mailer
{
    public function __construct(
        private Environment $twig,
        private ParameterBagInterface $parameter,
        private MailerInterface $mailer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function sendEmail(string $to, string $subject, string $template, array $context, ?bool $response = null): void
    {
        try {
            $email = new TemplatedEmail()
                ->from(new Address($this->parameter->get('mailer_reply'), $this->parameter->get('app_title')))
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context)
                ->html($this->twig->render($template, $context));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError $e) {
            $this->logger?->error('send-email', ['email' => $to, 'ex' => $e]);

            throw new RuntimeException('Failed to send email. Please try again later.');
        }
    }
}
