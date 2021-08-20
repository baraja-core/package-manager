<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


final class Package
{
	/** @var string[][]|mixed[][][] */
	private array $config;

	/** @var mixed[] */
	private array $composer;


	/**
	 * @param string[][]|mixed[][][] $config
	 * @param mixed[] $composer
	 */
	public function __construct(
		private string $name,
		private ?string $version,
		private string $dependency,
		array $config,
		array $composer
	) {
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
