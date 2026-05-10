<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\CombineIfRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\If_\NullableCompareToNullRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\FunctionLike\NarrowWideUnionReturnTypeRector;
use Rector\Doctrine\Bundle230\Rector\Class_\AddAnnotationToRepositoryRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\AddInstanceofAssertForNullableInstanceRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\WithCallbackIdenticalToStandaloneAssertsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/application',
        __DIR__ . '/domain',
        __DIR__ . '/infrastructure',
        __DIR__ . '/migrations',
        __DIR__ . '/presentation',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    $rectorConfig->sets([
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);

    // Skip specific rule
    $rectorConfig->skip([
        __DIR__ . '/src/Entity/Translation',
        CatchExceptionNameMatchingTypeRector::class,
        AttributeKeyToClassConstFetchRector::class,
        ThrowWithPreviousExceptionRector::class,
        DisallowedEmptyRuleFixerRector::class,
        CombineIfRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        NullableCompareToNullRector::class,
        ExplicitBoolCompareRector::class,
        AddInstanceofAssertForNullableInstanceRector::class,
        AddAnnotationToRepositoryRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
        WithCallbackIdenticalToStandaloneAssertsRector::class,
        NarrowWideUnionReturnTypeRector::class,
        SafeDeclareStrictTypesRector::class => [
            __DIR__ . '/src/Kernel.php',
            __DIR__ . '/tests/bootstrap.php',
        ],
        PreferPHPUnitThisCallRector::class,
    ]);
};
