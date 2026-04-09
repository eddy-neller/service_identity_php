<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity;

use App\Infrastructure\Persistence\Doctrine\RefreshTokenRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Model\AbstractRefreshToken;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends AbstractRefreshToken
{
    /**
     * Doctrine ne lit pas les attributs sur la classe parente : on redéclare donc
     * les propriétés héritées pour que le mapping soit explicite et fonctionnel.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected int|string|null $id = null;

    #[ORM\Column(name: 'refresh_token', type: Types::STRING, length: 128, unique: true)]
    protected ?string $refreshToken = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected ?string $username = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    protected ?DateTimeInterface $valid = null;
}
