<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Nette\DI\CompilerExtension;

abstract class SmartCompilerExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [];
	}


	/**
	 * @return string[]
	 */
	public static function mustBeDefinedAfter(): array
	{
		return [];
	}
}
