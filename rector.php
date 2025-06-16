<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php81\Rector\ClassConst\FinalizePublicClassConstantRector;

return static function (RectorConfig $rectorConfig): void {
    // Paths to refactor
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // Skip vendor and generated files
    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/tests/fixtures/cassettes',
    ]);

    // PHP version and sets
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ]);

    // Custom rules for financial SDK
    $rectorConfig->rules([
        // Add void return types where appropriate
        AddVoidReturnTypeWhereNoReturnRector::class,
        
        // Use constructor property promotion
        InlineConstructorDefaultToPropertyRector::class,
        
        // Remove unnecessary PHPDoc tags
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        
        // PHP 8.1 features
        ReadOnlyPropertyRector::class,
        FinalizePublicClassConstantRector::class,
    ]);

    // Configure specific rules
    $rectorConfig->ruleWithConfiguration(
        LocallyCalledStaticMethodToNonStaticRector::class,
        [
            // Keep factory methods static
            'Netresearch\EuVatSdk\Factory\VatRetrievalClientFactory' => ['create*'],
        ]
    );

    // Parallel processing
    $rectorConfig->parallel();
    
    // Cache directory
    $rectorConfig->cacheDirectory(__DIR__ . '/var/rector');
    
    // Import names
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
};