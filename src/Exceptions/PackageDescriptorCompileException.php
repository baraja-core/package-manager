<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Exception;


final class PackageDescriptorCompileException extends PackageDescriptorException
{
	public static function composerJsonIsBroken(string $packageName): self
	{
		return new self('File "composer.json" in package "' . $packageName . '" does not exist.');
	}
}
