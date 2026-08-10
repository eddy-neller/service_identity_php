<?php

declare(strict_types=1);

namespace App\Application\Shared\Messenger\Message;

final readonly class SendEmailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $template,
        public array $context = [],
    ) {
    }
}
