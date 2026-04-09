<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Helper;

use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * @deprecated Cette classe utilise la réflexion pour mapper des DTOs vers des entités, ce qui contourne l'encapsulation.
 *             Elle doit être remplacée par des mappers explicites dans la couche application.
 *             Utilisée temporairement dans UserManager::registerUser() et UserManager::createUserByAdmin().
 * @see App\Infrastructure\Service\User\UserManager
 */
final class ObjectHelper
{
    /**
     * @deprecated Remplacer par un mapping explicite dans un mapper dédié de la couche application
     */
    public function hydrateEntityFromDto(object $source, object $target): object
    {
        $targetReflection = new ReflectionClass($target);

        foreach ($targetReflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if (!property_exists($source, $propertyName)) {
                continue;
            }

            $value = $source->$propertyName;
            $propertyType = $property->getType();
            if (!$propertyType instanceof ReflectionNamedType) {
                continue;
            }

            if (is_array($value)) {
                $nestedTarget = $property->getValue($target);

                if (is_object($nestedTarget)) {
                    $this->hydrateEntityFromArray($value, $nestedTarget);
                }
            } elseif (is_object($value) && $this->isObjectProperty($propertyType)) {
                $nestedTarget = $property->getValue($target);

                if (null === $nestedTarget) {
                    $nestedTargetClass = $propertyType->getName();
                    $nestedTarget = new $nestedTargetClass();
                    $property->setValue($target, $nestedTarget);
                }

                $this->hydrateEntityFromDto($value, $nestedTarget);
            } elseif ($this->isTypeCompatible($propertyType, $value)) {
                $property->setValue($target, $value);
            }
        }

        return $target;
    }

    /**
     * @deprecated Remplacer par un mapping explicite dans un mapper dédié de la couche application
     */
    public function hydrateDtoFromArray(array $data, object $dto): object
    {
        $dtoReflection = new ReflectionClass($dto);

        foreach ($data as $key => $value) {
            try {
                $property = $dtoReflection->getProperty($key);

                $propertyType = $property->getType();

                if ($propertyType && $this->isTypeCompatible($propertyType, $value)) {
                    $property->setValue($dto, $value);
                } elseif (is_array($value) && $this->isObjectProperty($propertyType)) {
                    /** @var ReflectionNamedType $propertyType */
                    $nestedClass = $propertyType->getName();
                    $nestedObject = new $nestedClass();
                    $this->hydrateDtoFromArray($value, $nestedObject);
                    $property->setValue($dto, $nestedObject);
                }
            } catch (Exception) {
                continue;
            }
        }

        return $dto;
    }

    private function hydrateEntityFromArray(array $data, object $target): void
    {
        $targetReflection = new ReflectionClass($target);

        foreach ($data as $key => $value) {
            try {
                $property = $targetReflection->getProperty($key);
                if ($this->isTypeCompatible($property->getType(), $value)) {
                    $property->setValue($target, $value);
                }
            } catch (Exception) {
                continue;
            }
        }
    }

    private function isObjectProperty(mixed $propertyType): bool
    {
        if (!$propertyType instanceof ReflectionNamedType) {
            return false;
        }

        return class_exists($propertyType->getName());
    }

    private function isTypeCompatible(mixed $propertyType, mixed $value): bool
    {
        if (is_null($propertyType)) {
            return true;
        }

        if ($propertyType instanceof ReflectionUnionType) {
            foreach ($propertyType->getTypes() as $type) {
                if ($this->isTypeCompatible($type, $value)) {
                    return true;
                }
            }

            return false;
        }

        $typeName = $propertyType->getName();

        if (is_null($value)) {
            return $propertyType->allowsNull();
        }

        return match ($typeName) {
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'iterable' => is_iterable($value),
            default => $value instanceof $typeName,
        };
    }
}
