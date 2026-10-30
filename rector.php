<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use RectorLaravel\Rector\ClassMethod\ScopeNamedClassMethodToScopeAttributedClassMethodRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/workbench',
    ])
    ->withRootFiles()

    /*
     * File-backed cache so repeat runs only analyse what changed. The
     * directory sits under build/ so a single actions/cache entry covers it
     * in CI -- see .github/workflows/tests.yml.
     */
    ->withCache(
        cacheDirectory: __DIR__.'/build/rector',
        cacheClass: FileCacheStorage::class,
    )

    ->withParallel()

    // PHP 8.2 is this package's floor, so upgrade no further than that.
    ->withPhpSets(php82: true)

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
    )

    /*
     * Deliberately UP_TO_LARAVEL_120 and not 130: level sets migrate code
     * *to* the named version, and this package still has to run on Laravel
     * 12. Targeting 13 here would rewrite us onto APIs that do not exist on
     * the oldest version in our test matrix.
     */
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_IF_HELPERS,
    ])

    ->withSkip([
        __DIR__.'/build',
        __DIR__.'/vendor',
        __DIR__.'/workbench/database',

        /*
         * Laravel's own first-party packages do not declare strict types, and
         * this package is meant to sit alongside them. It would also change
         * coercion behaviour for env()-sourced config values, which arrive as
         * strings even when they represent ints.
         */
        SafeDeclareStrictTypesRector::class,

        /*
         * Rewrites scopeX() methods onto the #[Scope] attribute, which Laravel
         * added in 12.4. The floor is ^12.0, and on anything older the
         * attribute is ignored and every scope stops resolving.
         */
        ScopeNamedClassMethodToScopeAttributedClassMethodRector::class,
    ]);
