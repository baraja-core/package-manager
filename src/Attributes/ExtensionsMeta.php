<?php

declare(strict_types=1);

namespace Baraja\PackageManager;

use Attribute;


#[Attribute]
final class ExtensionsMeta
{
	/**
	 * @param class-string[] $mustBeDefinedBefore
	 * @param class-string[] $mustBeDefinedAfter
	 */
	public function __construct(
		private array $mustBeDefinedBefore = [],
		private array $mustBeDefinedAfter = [],
	) {
	}


	/**
	 * @return class-string[]
	 */
	public function getMustBeDefinedBefore(): array
	{
		return $this->mustBeDefinedBefore;
	}


	/**
	 * @return class-string[]
	 */
	public function getMustBeDefinedAfter(): array
	{
		return $this->mustBeDefinedAfter;
	}
}
