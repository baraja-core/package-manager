<?php

declare(strict_types=1);

namespace Symplify\PackageBuilder\DependencyInjection\CompilerPass;

use Symplify\AutowireArrayParameter\DependencyInjection\CompilerPass\AutowireArrayParameterCompilerPass as DecoupledCompilerPassAlias;

final class AutowireArrayParameterCompilerPass extends DecoupledCompilerPassAlias
{
    /**
     * @param string[] $excludedFatalClasses
     */
    public function __construct(array $excludedFatalClasses = [])
    {
        parent::__construct($excludedFatalClasses);

        trigger_error(sprintf(
            'Compiler pass "%s" is deprecated and will be removed in Symplify 8 (May 2020). Use "%s" instead',
            self::class,
            DecoupledCompilerPassAlias::class
        ));

        sleep(3);
    }
}
