<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorCompileException;

interface PackageDescriptorEntityInterface
{
	public function getComposerHash(): string;

	/**
	 * @return Package[]
	 * @throws PackageDescriptorCompileException
	 */
	public function getPackagest(): array;
}
