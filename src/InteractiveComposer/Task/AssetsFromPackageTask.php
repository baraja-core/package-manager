<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


use Baraja\PackageManager\Exception\PackageDescriptorCompileException;

/**
 * Priority: 200
 */
final class AssetsFromPackageTask extends BaseTask
{
	public function run(): bool
	{
		try {
			if (\count($this->packageRegistrator->getPackageDescriptorEntity()->getPackagest()) === 0) {
				return false;
			}
		} catch (PackageDescriptorCompileException $e) {
			return false;
		}

		echo 'BasePath:    ' . ($basePath = \dirname(__DIR__, 5) . '/') . "\n";
		echo 'ProjectRoot: ' . \rtrim(\dirname($basePath), '/') . '/';

		foreach (glob($basePath . '*') ?: [] as $namespace) {
			if (\is_dir($namespace)) {
				foreach (glob($namespace . '/*') ?: [] as $package) {
					if (\is_dir($package)) {
						$this->processPackage(rtrim($package) . '/', $basePath);
					}
				}
			}
		}

		return true;
	}


	public function getName(): string
	{
		return 'Assets from package copier';
	}


	private function processPackage(string $path, string $basePath): void
	{
		$this->copyInstallDir($path . 'install/', \rtrim(\dirname($basePath), '/') . '/');
		$this->copyInstallDir($path . 'update/', \rtrim(\dirname($basePath), '/') . '/', true);
	}


	private function copyInstallDir(string $source, string $projectRoot, bool $forceUpdate = false): bool
	{
		if (\is_dir($source) === false) {
			return false;
		}

		echo '|';

		clearstatcache();
		$this->copyFilesRecursively($source, '/', $projectRoot, $forceUpdate);
		clearstatcache();

		return true;
	}


	private function copyFilesRecursively(string $basePath, string $path, string $projectRoot, bool $forceUpdate): void
	{
		foreach (scandir(rtrim((string) preg_replace('/\/+/', '/', $basePath . '/' . $path), '/'), 1) ?: [] as $file) {
			if ($file !== '.' && $file !== '..') {
				$pathWithFile = (string) preg_replace('/\/+/', '/', $path . '/' . $file);
				$projectFilePath = rtrim($projectRoot, '/') . '/' . ltrim($pathWithFile, '/');

				if (\is_dir($basePath . '/' . $pathWithFile)) {
					if (\is_dir($projectFilePath) || \mkdir($projectFilePath, 0777, true)) {
						echo '.';
					} else {
						throw new \RuntimeException('Can not create directory "' . $projectFilePath . '".');
					}

					$this->copyFilesRecursively($basePath, $pathWithFile, $projectRoot, $forceUpdate);
				} elseif ($forceUpdate === false && \is_file($projectFilePath) === true) {
					echo '.';
				} else {
					$safeCopy = $this->safeCopy(
						$basePath . '/' . $pathWithFile,
						(string) preg_replace('/^(.*?)(\.dist)?$/', '$1', $projectFilePath)
					);

					if ($safeCopy === null || $safeCopy === true) {
						echo '.';
					} else {
						throw new \RuntimeException('Can not copy "' . $path . '".');
					}
				}
			}
		}
	}


	/**
	 * Copy file with exactly content or throw exception.
	 * If case of error try repeat copy 3 times by $ttl.
	 */
	private function safeCopy(string $from, string $to, int $ttl = 3): ?bool
	{
		if (($fromHash = md5_file($from)) === (file_exists($to) ? md5_file($to) : null)) {
			return null;
		}
		if (($copy = copy($from, $to)) === false || md5_file($to) !== $fromHash) {
			if ($ttl > 0) {
				clearstatcache();

				return $this->safeCopy($from, $to, $ttl - 1);
			}

			$return = null;
			if (($lastError = error_get_last()) && isset($lastError['message']) === true) {
				$return = trim((string) preg_replace('/\s*\[<a[^>]+>[a-z0-9.\-_()]+<\/a>]\s*/i', ' ', (string) $lastError['message']));
			}

			throw new \RuntimeException(
				'Can not copy file "' . $from . '" => "' . $to . '"'
				. ($return !== null ? ': ' . $return : '')
			);
		}

		return $copy;
	}
}
