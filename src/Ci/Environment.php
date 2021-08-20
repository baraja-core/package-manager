<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


/**
 * Encapsulate access to the environment variables
 */
final class Environment
{
	/** Environment variable value or false if the variable does not exist. */
	public function get(string $name): string|false
	{
		/** @var string|mixed[]|false $env */
		$env = getenv($name);

		return \is_array($env) === true ? false : $env;
	}


	public function getString(string $name): string
	{
		return (string) $this->get($name);
	}
}
