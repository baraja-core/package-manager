<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorException;
use Nette\IOException;
use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;

final class Storage
{
	private string $composerHash;

	private Generator $generator;

	private ?PackageDescriptorEntityInterface $descriptor = null;


	public function __construct(
		private string $basePath,
		private string $configPackagePath,
		private string $configLocalPath,
		string $projectRoot
	) {
		$this->composerHash = @md5_file($projectRoot . '/vendor/composer/installed.json') ?: md5((string) time());
		$this->generator = new Generator($projectRoot);
	}


	public function load(): PackageDescriptorEntityInterface
	{
		if ($this->descriptor === null) {
			if (trim($path = $this->getPath()) === '' || is_file($path) === false || filesize($path) < 10) {
				$this->descriptor = $this->save();
			}
			require_once $path;
			/** @var string $class class-string */
			$class = '\PackageDescriptorEntity';
			try {
				if (\class_exists($class) === false) {
					throw new \RuntimeException('Package descriptor does not exist, because class "' . $class . '" does not exist or is not autoloaded.');
				}
				$ref = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				throw new \LogicException('Package description entity does not exist, because class "' . $class . '" does not exist.');
			}

			/** @var PackageDescriptorEntityInterface $service */
			$service = $ref->newInstance();
			$descriptor = $this->descriptor = $service;
		} elseif ($this->isCacheExpired($this->descriptor)) {
			$descriptor = $this->descriptor = $this->save();
		} else {
			$descriptor = $this->descriptor;
		}

		$this->createPackageConfig($descriptor);

		return $descriptor;
	}


	/**
	 * @throws PackageDescriptorException
	 */
	public function createPackageConfig(PackageDescriptorEntityInterface $descriptor): void
	{
		if (is_file($this->configPackagePath) === true) {
			return;
		}

		$extensions = [];
		$neon = [];
		foreach ($descriptor->getPackagest() as $package) {
			foreach ($package->getConfig() as $param => $value) {
				if ($param === 'extensions') {
					foreach ((array) ($value['data'] ?? []) as $extensionName => $extensionType) {
						$extensions[$extensionName] = $extensionType;
					}
				} elseif ($param !== 'includes') {
					$neon[$param][] = [
						'name' => $package->getName(),
						'version' => $package->getVersion(),
						'data' => $value,
					];
				}
			}
		}

		$return = '';
		$anonymousServiceCounter = 0;
		$neonKeys = array_keys($neon);
		sort($neonKeys);
		foreach ($neonKeys as $neonKey) {
			$packageInfos = $neon[$neonKey];
			$return .= "\n" . $neonKey . ':' . "\n\t";
			$tree = [];
			foreach ($packageInfos as $packageInfo) {
				$packageData = $packageInfo['data']['data'] ?? $packageInfo['data'];
				$neonData = \is_array($packageData)
					? $packageData
					: Neon::decode((string) $packageData);
				foreach ($neonData as $treeKey => $treeValue) {
					if (is_int($treeKey) || (is_string($treeKey) && preg_match('/^-?\d+\z/', $treeKey))) {
						unset($neonData[$treeKey]);
						$neonData['helperKey_' . $anonymousServiceCounter] = $treeValue;
						$anonymousServiceCounter++;
					}
				}
				$tree = Helpers::recursiveMerge($tree, $neonData);
			}

			$treeNumbers = [];
			$treeOthers = [];
			foreach ($tree as $treeKey => $treeValue) {
				if (preg_match('/^helperKey_\d+$/', $treeKey)) {
					$treeNumbers[] = $treeValue;
				} else {
					$treeOthers[$treeKey] = $treeValue;
				}
			}

			ksort($treeOthers);

			usort($treeNumbers, function ($left, $right): int {
				$score = static function ($item): int {
					if (\is_string($item)) {
						return 1;
					}

					$array = [];
					$score = 0;
					if (\is_iterable($item)) {
						$score = 2;
					}
					if ($item instanceof Entity) {
						$array = (array) $item->value;
						$score += 3;
					}
					if (isset($array['factory']) === true) {
						return $score + 1;
					}

					return $score;
				};

				if (($a = $score($left)) > ($b = $score($right))) {
					return -1;
				}

				return $a === $b ? 0 : 1;
			});

			if ($treeOthers !== []) {
				$return .= str_replace("\n", "\n\t", Neon::encode($treeOthers, Neon::BLOCK));
			}
			if ($treeNumbers !== []) {
				$return .= str_replace("\n", "\n\t", Neon::encode($treeNumbers, Neon::BLOCK));
			}
			$return = trim($return) . "\n";
		}
		if ($extensions !== []) {
			$return .= "\n" . ExtensionSorter::serializeExtensionList($extensions);
		}

		FileSystem::write($this->configPackagePath, trim((string) preg_replace('/(\s)\[]-(\s)/', '$1-$2', $return)) . "\n");
	}


	private function save(): PackageDescriptorEntityInterface
	{
		$class = new ClassType('PackageDescriptorEntity');

		$class->setFinal()
			->setExtends(PackageDescriptorEntity::class)
			->addComment('This is temp class of PackageDescriptorEntity' . "\n")
			->addComment('@author Baraja PackageManager')
			->addComment('@generated ' . ($generatedDate = date('Y-m-d H:i:s')));

		$class->addConstant('GENERATED', time())
			->setPublic();

		$class->addMethod('getGeneratedDateTime')
			->setReturnType('string')
			->setBody('return \'' . $generatedDate . '\';');

		$class->addMethod('getGeneratedDateTimestamp')
			->setReturnType('int')
			->setBody('static $cache;'
				. "\n\n" . 'if ($cache === null) {'
				. "\n\t" . '$cache = (int) strtotime($this->getGeneratedDateTime());'
				. "\n" . '}'
				. "\n\n" . 'return $cache;');

		$class->addMethod('getComposerHash')
			->setReturnType('string')
			->setBody('return \'' . $this->composerHash . '\';');

		$packageDescriptorEntity = $this->generator->run();
		foreach ((new \ReflectionObject($packageDescriptorEntity))->getProperties() as $property) {
			if ($property->getName() === '__close') {
				$class->addProperty($property->getName(), true)
					->setProtected()
					->setType('bool');
			} else {
				$property->setAccessible(true);
				$class->addProperty(
					ltrim($property->getName(), '_'),
					$this->makeScalarValueOnly($property->getValue($packageDescriptorEntity)),
				)->setProtected()
					->setType((static function (?\ReflectionType $type) {
						if ($type instanceof \ReflectionNamedType) {
							return $type->getName();
						}

						return null;
					})($property->getType()));
			}
		}

		FileSystem::write(
			$this->getPath(),
			'<?php' . "\n\n"
			. 'declare(strict_types=1);' . "\n\n"
			. $class,
		);

		return $packageDescriptorEntity;
	}


	private function isCacheExpired(PackageDescriptorEntityInterface $descriptor): bool
	{
		if (!is_file($this->configPackagePath) || !is_file($this->configLocalPath)) {
			return true;
		}
		if ($descriptor->getComposerHash() !== $this->composerHash) {
			return true;
		}

		return false;
	}


	private function getPath(int $ttl = 3): string
	{
		static $entityFilePath;
		if ($entityFilePath === null) {
			$dir = $this->basePath . '/cache/baraja/packageDescriptor';
			$entityFilePath = $dir . '/PackageDescriptorEntity.php';

			try {
				FileSystem::createDir($dir, 0_777);
				if (\is_file($entityFilePath) === false) {
					FileSystem::write($entityFilePath, '');
				}
			} catch (IOException $e) {
				if ($ttl > 0) {
					$this->tryFixTemp($dir);

					return $this->getPath($ttl - 1);
				}

				throw new \RuntimeException(
					'Can not create PackageDescriptionEntity file: ' . $e->getMessage() . "\n"
					. 'Package Manager tried to create a directory "' . $dir . '" and a file "' . $entityFilePath . '" inside.',
					$e->getCode(),
					$e,
				);
			}
		}

		return $entityFilePath;
	}


	private function tryFixTemp(string $basePath): bool
	{
		if (\is_dir($basePath) === false) {
			return true;
		}
		foreach (array_keys(iterator_to_array(Finder::find('*')->in($basePath))) as $path) {
			if (\is_file((string) $path)) {
				@unlink((string) $path);
			}
		}

		return @rmdir($basePath);
	}


	/**
	 * @param mixed|mixed[] $data
	 */
	private function makeScalarValueOnly(mixed $data): mixed
	{
		if (\is_array($data) === true) {
			$return = [];
			foreach ($data as $key => $value) {
				if (is_object($value) === false) {
					$return[$key] = $this->makeScalarValueOnly($value);
				} else {
					$return[$key] = (array) $value;
				}
			}

			return $return;
		}

		return $data;
	}
}
