<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorCompileException;

/**
 * @internal
 */
class PackageDescriptorEntity implements PackageDescriptorEntityInterface
{
	protected bool $__close = false;

	/** @var \stdClass[] */
	protected array $composer;

	/** @var array<int, array{
	 *     name: string,
	 *     version: string|null,
	 *     dependency: string,
	 *     config: array<string, array{data: array<string, mixed>|string, rewrite: bool}>,
	 *     composer: array{name: string|null, description: string|null}|null
	 * }>
	 */
	protected array $packagest = [];


	public function isClose(): bool
	{
		return $this->__close;
	}


	public function setClose(): void
	{
		$this->__close = true;
	}


	public function checkIfClose(): void
	{
		if ($this->isClose() === true) {
			throw new \RuntimeException('Package descriptor was closed to insert. Setters can be used only in compile time.');
		}
	}


	/**
	 * @return \stdClass[]
	 */
	public function getComposer(): array
	{
		return $this->composer;
	}


	/**
	 * @param \stdClass[] $composer
	 */
	public function setComposer(array $composer): void
	{
		$this->checkIfClose();
		$this->composer = $composer;
	}


	/**
	 * @return array<int, Package>
	 */
	public function getPackagest(): array
	{
		$return = [];
		foreach ($this->packagest as $package) {
			$return[] = new Package(
				$package['name'],
				$package['version'],
				$package['dependency'],
				$package['config'],
				$package['composer'] ?? ['name' => $package, 'description' => null],
			);
		}

		return $return;
	}


	/**
	 * @param array<int, array{
	 *     name: string,
	 *     version: string|null,
	 *     dependency: string,
	 *     config: array<string, array{data: array<string, mixed>|string, rewrite: bool}>|null,
	 *     composer: array{name?: string, description?: string}|null
	 * }> $packagest
	 */
	public function setPackages(array $packagest): void
	{
		$this->checkIfClose();

		$return = [];
		foreach ($packagest as $package) {
			$composer = null;
			if (isset($package['composer']['name'], $package['composer']['description']) === true) {
				$composer = [
					'name' => $package['composer']['name'],
					'description' => $package['composer']['description'],
				];
			}

			$return[] = [
				'name' => $package['name'],
				'version' => $package['version'] ?? null,
				'dependency' => $package['dependency'],
				'config' => $package['config'] ?? [],
				'composer' => $composer,
			];
		}

		$this->packagest = $return;
	}


	public function getGeneratedDate(): string
	{
		return date('Y-m-d H:i:s');
	}


	public function getGeneratedDateTimestamp(): int
	{
		return time();
	}


	public function getComposerHash(): string
	{
		return md5((string) time());
	}
}
