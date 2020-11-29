<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorException;
use Composer\Autoload\ClassLoader;
use Nette\Utils\FileSystem;
use Tracy\Debugger;

class PackageRegistrator
{
	private static string $projectRoot;

	private static string $configPath;

	private static string $configPackagePath;

	private static string $configLocalPath;

	private static PackageDescriptorEntityInterface $packageDescriptorEntity;

	private static bool $configurationMode = false;


	public function __construct(?string $projectRoot = null, ?string $tempPath = null)
	{
		static $created = false;

		if ($created === true) {
			return;
		}
		if ($projectRoot === null || $tempPath === null) { // path auto detection
			try {
				$loaderRc = class_exists(ClassLoader::class) ? new \ReflectionClass(ClassLoader::class) : null;
				$vendorDir = $loaderRc ? dirname($loaderRc->getFileName(), 2) : null;
			} catch (\ReflectionException $e) {
				$vendorDir = null;
			}
			if ($vendorDir !== null && PHP_SAPI === 'cli' && (strncmp($vendorDir, 'phar://', 7) === 0 || strncmp($vendorDir, '/usr/share', 10) === 0)) {
				$vendorDir = (string) preg_replace('/^(.+?[\\\\|\/]vendor)(.*)$/', '$1', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0]['file']);
			}
			if ($projectRoot === null) {
				$projectRoot = dirname($vendorDir);
			}
			if ($tempPath === null) {
				$tempPath = rtrim($projectRoot, '/') . '/temp';
			}
		}
		if (Debugger::$logDirectory === null) {
			FileSystem::createDir($projectRoot . '/log');
			Debugger::enable(false, $projectRoot . '/log');
		}

		$created = true;
		self::$projectRoot = rtrim($projectRoot, '/');
		self::$configPath = self::$projectRoot . '/app/config/common.neon';
		self::$configPackagePath = self::$projectRoot . '/app/config/package.neon';
		self::$configLocalPath = self::$projectRoot . '/app/config/local.neon';
		$storage = new Storage($tempPath, $projectRoot, self::$configPackagePath, self::$configLocalPath);
		try {
			self::$packageDescriptorEntity = $storage->load();
		} catch (PackageDescriptorException $e) {
			Debugger::log($e, 'critical');
			if (PHP_SAPI === 'cli') {
				Helpers::terminalRenderError($e->getMessage());
			}
		}
	}


	final public static function isConfigurationMode(): bool
	{
		return self::$configurationMode;
	}


	/**
	 * Smart helper for automated Composer actions. This method will be called automatically.
	 *
	 * For register please add "scripts" section to your composer.json in project root:
	 *
	 * "scripts": {
	 *    "post-autoload-dump": "Baraja\\PackageManager\\PackageRegistrator::composerPostAutoloadDump"
	 * }
	 */
	public static function composerPostAutoloadDump(): void
	{
		if (PHP_SAPI !== 'cli') {
			throw new \RuntimeException('PackageRegistrator: Composer action can be called only in CLI environment.');
		}

		echo 'Starting Composer post autoload dump task.' . "\n\n";
		echo 'Server time: ' . date('Y-m-d H:i:s') . "\n";
		if (defined('PHP_VERSION') && defined('PHP_OS')) {
			echo 'Using PHP version: ' . PHP_VERSION . ' (' . PHP_OS . ')' . "\n";
		}
		if (isset($_SERVER['USER'])) {
			echo 'User: ' . $_SERVER['USER'] . "\n";
		}
		if (isset($_SERVER['SCRIPT_FILENAME'])) {
			echo 'Called by script: ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
		}
		if (isset($_SERVER['argv'], $_SERVER['argc']) && $_SERVER['argc'] > 0) {
			echo 'Command arguments:' . "\n";
			echo '   - ' . implode("\n" . '   - ', $_SERVER['argv']) . "\n";
		}
		echo '------------------------------------------' . "\n\n";
		self::composerRenderCiDetectorInfo();

		if (isset($_SERVER['argv'][2]) === true && $_SERVER['argv'][2] === '--') {
			self::$configurationMode = true;
		}

		if (self::isConfigurationMode() === true) {
			echo '️⚙️️  This is a advance configuration mode.' . "\n";
			echo '---------------------------------' . "\n\n";
		} else {
			echo '️⚔️  This is a regular mode.' . "\n";
			echo '   If you want use advance configuration, please use command "composer dump --".' . "\n";
			echo '---------------------------------' . "\n\n";
		}

		try {
			FileSystem::delete(dirname(__DIR__, 4) . '/temp/cache/baraja/packageDescriptor');
			(new InteractiveComposer(new self))->run();
		} catch (\Exception $e) {
			Helpers::terminalRenderError($e->getMessage());
			Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			Debugger::log($e, 'critical');
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
			Debugger::log($e, 'critical');
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


	public static function getPackageDescriptorEntityStatic(): PackageDescriptorEntityInterface
	{
		return self::$packageDescriptorEntity;
	}


	public function getProjectRoot(): string
	{
		return self::$projectRoot;
	}


	public function getPackageDescriptorEntity(): PackageDescriptorEntityInterface
	{
		return self::$packageDescriptorEntity;
	}
}
