<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Nette\DI\CompilerExtension;

final class PackageManagerExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('packageRegistrator'))
			->setFactory(PackageRegistrator::class)
			->setAutowired(PackageRegistrator::class);
	}
}
