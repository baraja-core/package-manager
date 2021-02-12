<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


/**
 * Unified adapter to retrieve environment variables from current continuous integration server
 */
abstract class AbstractCi implements CiInterface
{
	protected Environment $env;


	public function __construct(Environment $env)
	{
		$this->env = $env;
	}
}
