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
		private string $projectRoot,
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
			json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
		);
		if (is_array($composerJson) === false) {
			throw new \LogicException(sprintf('File "composer.json" should be array, but "%s" parsed.', get_debug_type($composerJson)));
		}
		if ($composerJson === []) {
			throw new \RuntimeException(sprintf('File "composer.json" can not be empty. Did you check path "%s"?', $path));
		}

		$packageDescriptor->setComposer($composerJson);
		$packageDescriptor->setPackages($this->getPackages($composerJson));

		return $packageDescriptor;
	}


	/**
	 * @param array{require?: array<string, mixed>} $composer
	 * @return array<int, array{
	 *     name: string,
	 *     version: string|null,
	 *     dependency: string,
	 *     config: array<string, array{data: array<string, mixed>|string, rewrite: bool}>|null,
	 *     composer: array{name?: string, description?: string}|null
	 * }>
	 * @throws PackageDescriptorException
	 */
	private function getPackages(array $composer): array
	{
		try {
			$packagesVersions = $this->getPackagesVersions();
		} catch (\Throwable) {
			$packagesVersions = [];
		}
		if (isset($composer['require']) === false) {
			throw new \LogicException('Key "require" is mandatory for your project "composer.json".');
		}

		$packageDirs = array_merge($composer['require'], $packagesVersions);

		// Find other packages
		foreach (new \DirectoryIterator($this->projectRoot . '/vendor') as $vendorNamespace) {
			$namespace = $vendorNamespace->getFilename();
			if ($namespace !== '.' && $namespace !== '..' && $vendorNamespace->isDir() === true) {
				foreach (new \DirectoryIterator($this->projectRoot . '/vendor/' . $namespace) as $packageName) {
					$name = $packageName->getFilename();
					$package = $namespace . '/' . $name;
					if (
						$name !== '.'
						&& $name !== '..'
						&& isset($packageDirs[$package]) === false
						&& $packageName->isDir() === true
					) {
						$packageDirs[$package] = '*';
					}
				}
			}
		}

		$return = [];
		foreach ($packageDirs as $name => $dependency) {
			if (str_starts_with($name, 'composer/')) {
				continue;
			}
			if (preg_match('/^(php|ext-\w+|[a-z0-9-_]+\/[a-z0-9-_]+)$/', $name) !== 1) {
				trigger_error(sprintf('Composer 2.0 compatibility: Package name "%s" is invalid, it must contain only lower english characters.', $name));
			}
			$name = mb_strtolower($name, 'UTF-8');
			$path = $this->projectRoot . '/vendor/' . $name;
			if (is_dir($path) === false) {
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
			$composerPath = $path . '/composer.json';
			$composerContentArray = null;
			if (is_file($composerPath)) {
				$composerContent = json_decode((string) file_get_contents($composerPath), flags: JSON_THROW_ON_ERROR);
				if ($composerContent === null) {
					throw PackageDescriptorCompileException::composerJsonIsBroken($name);
				}
				/** @var array{name?: string, description?: string} $composerContentArray */
				$composerContentArray = Helpers::haystackToArray($composerContent);
			} else {
				throw new \LogicException(sprintf('File "composer.json" does not exist on path "%s".', $composerPath));
			}

			assert(is_string($dependency));
			$return[] = [
				'name' => $name,
				'version' => $packagesVersions[$name] ?? null,
				'dependency' => $dependency,
				'config' => $configPath !== null ? $this->formatConfigSections($configPath) : null,
				'composer' => $composerContentArray,
			];
		}

		return $return;
	}


	/**
	 * @return array<string, array{data: array<string, mixed>|string, rewrite: bool}>
	 */
	private function formatConfigSections(string $path): array
	{
		$neon = Neon::decode((string) file_get_contents($path));
		$neonArray = is_array($neon) ? $neon : [];
		$return = [];
		foreach ($neonArray as $part => $haystack) {
			if (is_array($haystack) === false) {
				throw new \LogicException(sprintf('Service haystack should be iterable, but "%s" given.', get_debug_type($haystack)));
			}
			if ($part === 'services') {
				$servicesList = '';
				foreach ($haystack as $key => $serviceClass) {
					$servicesList .= (is_int($key) ? '- ' : $key . ': ') . Neon::encode($serviceClass) . "\n";
				}

				$return['services'] = [
					'data' => $servicesList,
					'rewrite' => false,
				];
			} else {
				$return[(string) $part] = [
					'data' => $haystack,
					'rewrite' => true,
				];
			}
		}

		return $return;
	}


	/**
	 * @return array<string, string>
	 */
	private function getPackagesVersions(): array
	{
		$return = [];
		$packages = [];
		if (class_exists(ClassLoader::class, false)) {
			$lockFile = null;
			try {
				$classLoader = (new \ReflectionClass(ClassLoader::class))->getFileName();
				if ($classLoader === false) {
					throw new \RuntimeException(
						'Composer classLoader (class "' . ClassLoader::class . '") does not exist. '
						. 'Please check your Composer installation.',
					);
				}
				$lockFile = \dirname($classLoader) . '/../../composer.lock';
			} catch (\ReflectionException) {
				// silence is golden.
			}
			if ($lockFile === null) {
				throw new \LogicException('File "composer.lock" does not exist or not been detected.');
			}
			if (is_file($lockFile) === false) {
				throw new \RuntimeException('Can not load "composer.lock", because path "' . $lockFile . '" does not exist.');
			}

			/** @var array{packages?: array<int, array{name: string, version: string, source: array{reference: string}}>} $composer */
			$composer = @json_decode((string) file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR); // @ may not exist or be valid
			$packages = $composer['packages'] ?? [];
			usort($packages, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
		}

		foreach ($packages as $package) {
			$return[$package['name']] = $package['version']
				. (!str_contains($package['version'], 'dev') ? '' : ' #' . substr($package['source']['reference'], 0, 4));
		}

		return $return;
	}
}
