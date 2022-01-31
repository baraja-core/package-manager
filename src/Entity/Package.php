<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


final class Package
{
	/** @var array<string, array{data: array<string, mixed>|string, rewrite: bool}> */
	private array $config;

	/** @var array{name: string|null, description: string|null} */
	private array $composer;


	/**
	 * @param array<string, array{data: array<string, mixed>|string, rewrite: bool}> $config
	 * @param array{name: string|null, description: string|null} $composer
	 */
	public function __construct(
		private string $name,
		private ?string $version,
		private string $dependency,
		array $config,
		array $composer,
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
	 * @return array<string, array{data: array<string, mixed>|string, rewrite: bool}>
	 */
	public function getConfig(): array
	{
		return $this->config;
	}


	/**
	 * @return array{name: string|null, description: string|null}
	 */
	public function getComposer(): array
	{
		return $this->composer;
	}
}
