<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\User;

use App\Application\Shared\Port\FileInterface;
use App\Application\User\Port\AvatarUploaderInterface;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\ValueObject\Avatar;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Entity\User\User as DoctrineUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Effectuer l'upload au préalable afin de récupérer le nom final du fichier généré par Vich.
 * Single Responsability Principle : cette classe ne s'occupe que d'uploader le fichier physique.
 * Evite d'avoir la gestion de fichier physique dans DoctrineUserRepository.
 */
final readonly class AvatarUploader implements AvatarUploaderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function upload(UserId $id, FileInterface $file): Avatar
    {
        try {
            /** @var DoctrineUser $entity */
            $entity = $this->em->getReference(DoctrineUser::class, $id->toString());

            $uploadedFile = new UploadedFile(
                $file->getPathname(),
                $file->getClientOriginalName(),
                '' !== $file->getMimeType() ? $file->getMimeType() : null,
                UPLOAD_ERR_OK,
                true,
            );

            $entity->setAvatarFile($uploadedFile);
            $this->em->flush();

            return new Avatar($entity->getAvatarName());
        } catch (EntityNotFoundException|ORMException) {
            throw new UserNotFoundException();
        }
    }
}
