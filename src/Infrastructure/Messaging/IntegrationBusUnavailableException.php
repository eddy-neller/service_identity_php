<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use RuntimeException;
use Throwable;

/**
 * Le bus est momentanément injoignable : la publication n'a pas abouti, mais la même publication
 * aboutira quand il reviendra.
 *
 * C'est la seule distinction que {@see UserIntegrationEventPublisherInterface} demande à ses
 * implémentations : **attendre** a un sens, ou non. Elle ne nomme aucune technologie, pour que le
 * handler puisse en tirer une politique de retry sans rien savoir de SNS.
 *
 * Tout autre échec — requête refusée, configuration fausse, bug — lève autre chose, et garde le
 * chemin ordinaire : quelques essais, puis la file d'échec, où un humain le verra.
 */
final class IntegrationBusUnavailableException extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('Integration bus unavailable: ' . $previous->getMessage(), 0, $previous);
    }
}
