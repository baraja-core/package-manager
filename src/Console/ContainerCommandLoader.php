<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Console;


use Nette\DI\Container;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

final class ContainerCommandLoader implements CommandLoaderInterface
{
	private Container $container;

	/** @var string[] */
	private array $commandMap;


	/**
	 * @param string[] $commandMap
	 */
	public function __construct(Container $container, array $commandMap)
	{
		$this->container = $container;
		$this->commandMap = $commandMap;
	}


	/**
	 * @throws CommandNotFoundException
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint
	 */
	public function get(string $name): Command
	{
		if (!$this->has($name)) {
			throw new RuntimeException('Command "' . $name . '" does not exist.');
		}

		return $this->container->getService($this->commandMap[$name]);
	}


	/**
	 * Checks if a command exists.
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function has(string $name): bool
	{
		return array_key_exists($name, $this->commandMap);
	}


	/**
	 * @return string[] All registered command names
	 */
	public function getNames(): array
	{
		return array_keys($this->commandMap);
	}
}
