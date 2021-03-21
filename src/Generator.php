<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorCompileException;
use Baraja\PackageManager\Exception\PackageDescriptorException;
use Composer\Autoload\ClassLoader;
use Nette\Neon\Neon;

final class Generator
{
	public function __construct(
		private string $projectRoot
	) {
	}


	/**
	 * @throws PackageDescriptorException
	 * @internal
	 */
	public function run(): PackageDescriptorEntityInterface
	{
		$packageDescriptor = new PackageDescriptorEntity;
		$path = $this->projectRoot . '/composer.json';

		if (is_file($path) === false) {
			throw new \RuntimeException('File "composer.json" on path "' . $path . '" does not exist.');
		}

		$composerJson = Helpers::haystackToArray(
			json_decode((string) file_get_contents($path)),
		);

		if ($composerJson === [] || $composerJson === '') {
			throw new \RuntimeException(
				'File "composer.json" can not be empty. Did you check path "' . $path . '"?',
			);
		}

		$packageDescriptor->setComposer($composerJson);
		$packageDescriptor->setPackages($packages = $this->getPackages($composerJson));

		return $packageDescriptor;
	}


	/**
	 * @param string[][] $composer
	 * @return mixed[]
	 * @throws PackageDescriptorException
	 */
	private function getPackages(array $composer): array
	{
		try {
			$packagesVersions = $this->getPackagesVersions();
		} catch (\Throwable $e) {
			$packagesVersions = [];
		}
		if (isset($composer['require']) === false) {
			throw new \LogicException('Key "require" is mandatory for your project "composer.json".');
		}

		$packageDirs = array_merge($composer['require'], $packagesVersions);

		// Find other packages
		foreach (new \DirectoryIterator($this->projectRoot . '/vendor') as $vendorNamespace) {
			if (
				$vendorNamespace->isDir() === true
				&& ($namespace = $vendorNamespace->getFilename()) !== '.'
				&& $namespace !== '..'
			) {
				foreach (new \DirectoryIterator($this->projectRoot . '/vendor/' . $namespace) as $packageName) {
					if ($packageName->isDir() === true && ($name = $packageName->getFilename()) !== '.' && $name !== '..'
						&& isset($packageDirs[$package = $namespace . '/' . $name]) === false
					) {
						$packageDirs[$package] = '*';
					}
				}
			}
		}

		$return = [];
		foreach ($packageDirs as $name => $dependency) {
			if (!preg_match('/^(php|ext-\w+|[a-z0-9-_]+\/[a-z0-9-_]+)$/', $name)) {
				trigger_error('Composer 2.0 compatibility: Package name "' . $name . '" is invalid, it must contain only lower english characters.');
			}
			if (is_dir($path = $this->projectRoot . '/vendor/' . ($name = mb_strtolower($name, 'UTF-8'))) === false) {
				continue;
			}

			$configPath = null;
			if (\is_file($path . '/common.neon') === true) {
				$configPath = $path . '/common.neon';
			}
			if (\is_file($path . '/config.neon') === true) {
				if ($configPath !== null) {
					throw new \RuntimeException('Can not use multiple config files. Please merge "' . $configPath . '" and "config.neon" to "common.neon".');
				}
				trigger_error('File "config.neon" is deprecated for Nette 3.0, please use "common.neon" for path: "' . $path . '".');
				$configPath = $path . '/config.neon';
			}
			if (
				is_file($composerPath = $path . '/composer.json')
				&& json_decode((string) file_get_contents($composerPath)) === null
			) {
				PackageDescriptorCompileException::composerJsonIsBroken($name);
			}

			$return[] = [
				'name' => $name,
				'version' => $packagesVersions[$name] ?? null,
				'dependency' => $dependency,
				'config' => $configPath !== null ? $this->formatConfigSections($configPath) : null,
				'composer' => is_file($composerPath)
					? Helpers::haystackToArray(json_decode((string) file_get_contents($composerPath)))
					: null,
			];
		}

		return $return;
	}


	/**
	 * @return string[]|string[][]|mixed[][]
	 */
	private function formatConfigSections(string $path): array
	{
		$return = [];
		foreach (\is_array($neon = Neon::decode((string) file_get_contents($path))) ? $neon : [] as $part => $haystack) {
			if ($part === 'services') {
				$servicesList = '';
				foreach ($haystack as $key => $serviceClass) {
					$servicesList .= (\is_int($key) ? '- ' : $key . ': ') . Neon::encode($serviceClass) . "\n";
				}

				$return[$part] = [
					'data' => $servicesList,
					'rewrite' => false,
				];
			} else {
				$return[$part] = [
					'data' => $haystack,
					'rewrite' => true,
				];
			}
		}

		return $return;
	}


	/**
	 * @return string[]
	 */
	private function getPackagesVersions(): array
	{
		$return = [];
		$packages = [];
		if (class_exists(ClassLoader::class, false)) {
			try {
				if (($classLoader = (new \ReflectionClass(ClassLoader::class))->getFileName()) === false) {
					throw new \RuntimeException(
						'Composer classLoader (class "' . ClassLoader::class . '") does not exist. '
						. 'Please check your Composer installation.',
					);
				}
				$lockFile = \dirname($classLoader) . '/../../composer.lock';
			} catch (\ReflectionException $e) {
				$lockFile = null;
			}
			if ($lockFile !== null && is_file($lockFile) === false) {
				throw new \RuntimeException('Can not load "composer.lock", because path "' . $lockFile . '" does not exist.');
			}

			$composer = @json_decode((string) file_get_contents((string) $lockFile)); // @ may not exist or be valid
			$packages = (array) @$composer->packages;
			usort($packages, fn($a, $b) => strcmp($a->name, $b->name));
		}

		foreach ($packages as $package) {
			$return[$package->name] = $package->version
				. (!str_contains($package->version, 'dev') ? '' : ' #' . substr($package->source->reference, 0, 4));
		}

		return $return;
	}
}
