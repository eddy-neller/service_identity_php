<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Service\Notification\Messenger\Handler;

use App\Application\Shared\Messenger\Message\SendEmailMessage;
use App\Infrastructure\Symfony\Service\Notification\Mailer\Mailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'async.bus', sign: true)]
final readonly class SendEmailMessageHandler
{
    public function __construct(private Mailer $mailer)
    {
    }

    public function __invoke(SendEmailMessage $message): void
    {
        $this->mailer->sendEmail(
            $message->to,
            $message->subject,
            $message->template,
            $message->context
        );
    }
}
