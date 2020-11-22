<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Nette\IOException;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;

final class Storage
{
	private string $basePath;


	public function __construct(string $basePath)
	{
		$this->basePath = $basePath;
	}


	/**
	 * @internal
	 */
	public function load(): PackageDescriptorEntityInterface
	{
		static $loaded = false;
		if ($loaded === false) {
			if (trim($path = $this->getPath()) === '' || is_file($path) === false || filesize($path) < 10) {
				throw new \LogicException('Package description entity does not exist, because file "' . $path . '" does not exist.');
			}
			require_once $path;
			$loaded = true;
		}
		try {
			$ref = new \ReflectionClass($class = '\PackageDescriptorEntity');
		} catch (\ReflectionException $e) {
			throw new \LogicException('Package description entity does not exist, because class "' . $class . '" does not exist.');
		}

		/** @var PackageDescriptorEntityInterface $service */
		$service = $ref->newInstance();

		return $service;
	}


	public function save(PackageDescriptorEntityInterface $packageDescriptorEntity, ?string $composerHash = null): void
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
			->setBody('return \'' . ($composerHash ?? '') . '\';');

		foreach ((new \ReflectionObject($packageDescriptorEntity))->getProperties() as $property) {
			if ($property->getName() === '__close') {
				$class->addProperty($property->getName(), true)
					->setProtected()
					->setType('bool');
			} else {
				$property->setAccessible(true);
				$class->addProperty(
					ltrim($property->getName(), '_'),
					$this->makeScalarValueOnly($property->getValue($packageDescriptorEntity))
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
			. $class
		);
	}


	private function getPath(int $ttl = 3): string
	{
		static $entityFilePath;
		if ($entityFilePath === null) {
			$dir = $this->basePath . '/cache/baraja/packageDescriptor';
			$entityFilePath = $dir . '/PackageDescriptorEntity.php';

			try {
				FileSystem::createDir($dir, 0777);
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
					$e->getCode(), $e
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
		foreach (Finder::find('*')->in($basePath) as $path => $value) {
			@unlink($path);
		}

		return @rmdir($basePath);
	}


	/**
	 * @param mixed|mixed[] $data
	 * @return mixed|mixed[]
	 */
	private function makeScalarValueOnly($data)
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
