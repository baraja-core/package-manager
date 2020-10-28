<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Nette\SmartObject;

final class Package
{
	use SmartObject;

	private string $name;

	private ?string $version;

	private string $dependency;

	/** @var string[][]|mixed[][][] */
	private array $config;

	/** @var mixed[] */
	private array $composer;


	/**
	 * @param string $name
	 * @param string|null $version
	 * @param string $dependency
	 * @param string[][]|mixed[][][] $config
	 * @param mixed[] $composer
	 */
	public function __construct(string $name, ?string $version, string $dependency, array $config, array $composer)
	{
		$this->name = $name;
		$this->version = $version;
		$this->dependency = $dependency;
		$this->config = $config;
		$this->composer = $composer;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getVersion(): ?string
	{
		return $this->version;
	}


	public function getDependency(): string
	{
		return $this->dependency;
	}


	/**
	 * @return string[][]|bool[][]|mixed[][][]
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/**
	 * @return mixed[]
	 */
	public function getComposer(): array
	{
		return $this->composer;
	}
}
