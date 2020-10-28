<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Exception;


final class PackageDescriptorCompileException extends PackageDescriptorException
{

	/**
	 * @throws PackageDescriptorCompileException
	 */
	public static function canNotLoadComposerLock(string $lockPath): void
	{
		throw new self('Can not load "composer.lock", because path "' . $lockPath . '" does not exist.');
	}


	/**
	 * @throws PackageDescriptorCompileException
	 */
	public static function composerJsonIsBroken(string $packageName): void
	{
		throw new self('File "composer.json" in package "' . $packageName . '" does not exist.');
	}
}
