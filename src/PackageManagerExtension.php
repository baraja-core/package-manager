<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Contributte\Console\Application;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

class PackageManagerExtension extends CompilerExtension
{

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');

		if (PHP_SAPI === 'cli' && class_exists(Application::class)) {
			$initialize->setBody(
				$initialize->getBody() . "\n"
				. Console::class . '::setContainer($this);' . "\n"
				. 'register_shutdown_function([' . Console::class . '::class, \'run\']);'
			);
		}
	}


}