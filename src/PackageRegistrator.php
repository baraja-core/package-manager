<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorCompileException;
use Baraja\PackageManager\Exception\PackageDescriptorException;
use Baraja\PackageManager\Exception\PackageEntityDoesNotExistsException;
use Nette\Neon\Entity;
use Nette\Neon\Neon;
use Tracy\Debugger;

class PackageRegistrator
{

	/** @var bool */
	private static $created = false;

	/** @var string */
	private static $projectRoot;

	/** @var string */
	private static $configPath;

	/** @var string */
	private static $configPackagePath;

	/** @var string */
	private static $configLocalPath;

	/** @var PackageDescriptorEntity */
	private static $packageDescriptorEntity;


	/**
	 * @param string|null $projectRoot
	 * @param string|null $tempPath
	 */
	public function __construct(?string $projectRoot = null, ?string $tempPath = null)
	{
		if (self::$created === true || $projectRoot === null || $tempPath === null) {
			return;
		}

		self::$created = true;
		self::$projectRoot = rtrim($projectRoot, '/');
		self::$configPath = self::$projectRoot . '/app/config/common.neon';
		self::$configPackagePath = self::$projectRoot . '/app/config/package.neon';
		self::$configLocalPath = self::$projectRoot . '/app/config/local.neon';

		try {
			$storage = new Storage($tempPath);
			try {
				self::$packageDescriptorEntity = $storage->load();

				if ($this->isCacheExpired(self::$packageDescriptorEntity)) {
					throw new PackageEntityDoesNotExistsException('Cache expired');
				}
			} catch (PackageEntityDoesNotExistsException $e) {
				$storage->save(
					self::$packageDescriptorEntity = (new Generator($projectRoot))->run(),
					$this->getComposerHash()
				);
				$this->createPackageConfig(self::$packageDescriptorEntity);
			}
		} catch (PackageDescriptorException $e) {
			Debugger::log($e);
			Helpers::terminalRenderError($e->getMessage());
		}
	}


	public static function composerPostAutoloadDump(): void
	{
		self::composerRenderCiDetectorInfo();

		try {
			(new InteractiveComposer(new self(__DIR__ . '/../../../../', __DIR__ . '/../../../../temp/')))->run();
		} catch (\Exception $e) {
			Helpers::terminalRenderError($e->getMessage());
			Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			Debugger::log($e);
			echo 'Error was logged to file.' . "\n\n";
		}
	}


	/**
	 * Render all information about current runner (CLI, CI or other).
	 */
	public static function composerRenderCiDetectorInfo(): void
	{
		try {
			$ci = self::getCiDetect();
		} catch (\Exception $e) {
			Helpers::terminalRenderError($e->getMessage());
			Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			Debugger::log($e);
			echo 'Error was logged to file.' . "\n\n";
			$ci = null;
		}

		echo 'CI status: ' . ($ci === null ? 'No detected' : 'detected 👍') . "\n\n";
		if ($ci !== null) {
			echo 'CI name: ' . $ci->getCiName() . "\n";
			echo 'is Pull request? ' . $ci->isPullRequest()->describe() . "\n";
			echo 'Build number: ' . $ci->getBuildNumber() . "\n";
			echo 'Build URL: ' . $ci->getBuildUrl() . "\n";
			echo 'Git commit: ' . $ci->getGitCommit() . "\n";
			echo 'Git branch: ' . $ci->getGitBranch() . "\n";
			echo 'Repository name: ' . $ci->getRepositoryName() . "\n";
			echo 'Repository URL: ' . $ci->getRepositoryUrl() . "\n";
			echo '---------------------------------' . "\n\n";
		}
	}


	/**
	 * @return CiInterface|null
	 * @throws PackageDescriptorException
	 */
	public static function getCiDetect(): ?CiInterface
	{
		/** @var CiInterface|null $cache */
		static $cache;

		if ($cache === null && ($ciDetector = new CiDetector)->isCiDetected()) {
			$cache = $ciDetector->detect();
		}

		return $cache;
	}


	/**
	 * @return PackageDescriptorEntity
	 */
	public static function getPackageDescriptorEntityStatic(): PackageDescriptorEntity
	{
		return self::$packageDescriptorEntity;
	}


	/**
	 * @return string
	 */
	public function getProjectRoot(): string
	{
		return self::$projectRoot;
	}


	/**
	 * @return PackageDescriptorEntity
	 */
	public function getPackageDescriptorEntity(): PackageDescriptorEntity
	{
		return self::$packageDescriptorEntity;
	}


	/**
	 * @return string
	 * @throws PackageDescriptorException
	 */
	public function getConfig(): string
	{
		if (!is_file(self::$configPackagePath)) {
			$this->createPackageConfig($this->getPackageDescriptorEntity());
		}

		return (string) file_get_contents(self::$configPackagePath);
	}


	/**
	 * @internal please use DIC, this is for legacy support only!
	 * @param string $packageName
	 * @return bool
	 * @throws PackageDescriptorCompileException
	 */
	public function isPackageInstalled(string $packageName): bool
	{
		foreach (self::$packageDescriptorEntity->getPackagest() as $package) {
			if ($package->getName() === $packageName) {
				return true;
			}
		}

		return false;
	}


	/**
	 * @deprecated since 2020-03-29
	 */
	public function runAfterActions(): void
	{
		throw new \RuntimeException('Method "' . __METHOD__ . '" is deprecated. Please use DIC extension.');
	}


	/**
	 * @param PackageDescriptorEntity $packageDescriptorEntity
	 * @throws PackageDescriptorException
	 */
	private function createPackageConfig(PackageDescriptorEntity $packageDescriptorEntity): void
	{
		$neon = [];

		foreach ($packageDescriptorEntity->getPackagest() as $package) {
			foreach ($package->getConfig() as $param => $value) {
				if ($param !== 'includes') {
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

		foreach ($neon as $param => $values) {
			$return .= "\n" . $param . ':' . "\n\t";
			$tree = [];

			if ($param === 'services') {
				foreach ($values as $value) {
					foreach ($neonData = Neon::decode($value['data']['data']) as $treeKey => $treeValue) {
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

						if (isset($array['factory'])) {
							return $score + 1;
						}

						return $score;
					};

					if (($a = $score($left)) > ($b = $score($right))) {
						return -1;
					}

					return $a === $b ? 0 : 1;
				});

				$return .= str_replace("\n", "\n\t", Neon::encode($treeOthers, Neon::BLOCK));
				$return .= str_replace("\n", "\n\t", Neon::encode($treeNumbers, Neon::BLOCK));
				$tree = [];
			} else {
				foreach ($values as $value) {
					if ((bool) $value['data']['rewrite'] === false) {
						$return .= '# ' . $value['name'] . ($value['version'] ? ' (' . $value['version'] . ')' : '')
							. "\n\t" . str_replace("\n", "\n\t", $value['data']['data']);
					}

					if ((bool) $value['data']['rewrite'] === true) {
						$tree = Helpers::recursiveMerge($tree, $value['data']['data']);
					}
				}
			}

			if ($tree !== []) {
				$return .= str_replace("\n", "\n\t", Neon::encode($tree, Neon::BLOCK));
			}

			$return = trim($return) . "\n";
		}

		$return = (string) preg_replace('/(\s)\[\]\-(\s)/', '$1-$2', $return);

		if (!@file_put_contents(self::$configPackagePath, trim($return) . "\n")) {
			PackageDescriptorException::canNotRewritePackageNeon(self::$configPackagePath);
		}
	}


	/**
	 * @param PackageDescriptorEntity $packageDescriptorEntity
	 * @return bool
	 */
	private function isCacheExpired(PackageDescriptorEntity $packageDescriptorEntity): bool
	{
		if (!is_file(self::$configPackagePath) || !is_file(self::$configLocalPath)) {
			return true;
		}

		if ($packageDescriptorEntity->getComposerHash() !== $this->getComposerHash()) {
			return true;
		}

		return false;
	}


	/**
	 * Return hash of installed.json, if composer does not used, return empty string
	 *
	 * @return string
	 */
	private function getComposerHash(): string
	{
		static $cache;

		if ($cache === null) {
			$cache = (@md5_file(self::$projectRoot . '/vendor/composer/installed.json')) ?: md5((string) time());
		}

		return $cache;
	}
}