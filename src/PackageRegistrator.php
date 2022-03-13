<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\Console\Helpers as ConsoleHelpers;
use Baraja\Lock\Lock;
use Baraja\Lock\Transaction\FileTransactionProvider;
use Baraja\PackageManager\Composer\TaskManager;
use Baraja\PackageManager\Exception\PackageDescriptorException;
use Baraja\PathResolvers\Resolvers\RootDirResolver;
use Baraja\PathResolvers\Resolvers\TempDirResolver;
use Baraja\PathResolvers\Resolvers\VendorResolver;
use Baraja\ShutdownTerminator\Terminator;
use Baraja\ShutdownTerminator\TerminatorHandler;
use Nette\Utils\FileSystem;
use Tracy\Debugger;
use Tracy\ILogger;

class PackageRegistrator implements TerminatorHandler
{
	private const MAINTENANCE_LOCK = 'package-manager-maintenance';

	private static string $projectRoot;

	private static string $configPackagePath;

	private static string $configLocalPath;

	private static PackageDescriptorEntityInterface $packageDescriptorEntity;

	private static bool $configurationMode = false;


	public function __construct(?string $rootDir = null, ?string $tempDir = null)
	{
		if (PHP_SAPI !== 'cli') {
			self::setupInternalLock($tempDir);
			Lock::wait(self::MAINTENANCE_LOCK, maxExecutionTimeMs: 120000, ttl: 50000);
		}
		static $created = false;

		if ($created === true) {
			return;
		}
		if ($rootDir === null || $tempDir === null) { // path auto detection
			$rootDirResolver = new RootDirResolver(new VendorResolver);
			if ($rootDir === null) {
				$rootDir = $rootDirResolver->get();
			}
			if ($tempDir === null) {
				$tempDir = (new TempDirResolver($rootDirResolver))->get();
			}
		}
		if (class_exists(Debugger::class) === true && Debugger::$logDirectory === null) {
			FileSystem::createDir($rootDir . '/log');
			try {
				Debugger::enable(false, $rootDir . '/log');
			} catch (\Throwable $e) {
				if (PHP_SAPI === 'cli') {
					ConsoleHelpers::terminalRenderError($e->getMessage());
					ConsoleHelpers::terminalRenderCode($e->getFile(), $e->getLine());
				} else {
					trigger_error($e->getMessage());
				}
			}
		}

		$created = true;
		self::$projectRoot = rtrim($rootDir, DIRECTORY_SEPARATOR);
		self::$configPackagePath = self::$projectRoot . '/app/config/package.neon';
		self::$configLocalPath = self::$projectRoot . '/app/config/local.neon';
		$storage = new Storage($tempDir, self::$configPackagePath, self::$configLocalPath, $rootDir);
		try {
			self::$packageDescriptorEntity = $storage->load();
		} catch (PackageDescriptorException $e) {
			if (class_exists(Debugger::class) === true) {
				Debugger::log($e, 'critical');
			}
			if (PHP_SAPI === 'cli') {
				ConsoleHelpers::terminalRenderError($e->getMessage());
			} else {
				throw new \RuntimeException(sprintf('Can not load PackageDescriptor: %s', $e->getMessage()), 500, $e);
			}
		}
	}


	public static function get(): self
	{
		static $cache;

		return $cache ?? $cache = new self;
	}


	final public static function isConfigurationMode(): bool
	{
		return self::$configurationMode;
	}


	final public function processTerminatorHandler(): void
	{
		if (PHP_SAPI !== 'cli') {
			return;
		}
		echo "\n\n" . 'Stopping transaction...' . "\n\n";
		Lock::stopTransaction(self::MAINTENANCE_LOCK);
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
		self::setupInternalLock();
		if (PHP_SAPI !== 'cli') {
			throw new \RuntimeException('PackageRegistrator: Composer action can be called only in CLI environment.');
		}
		if (Lock::isTransactionRunning(self::MAINTENANCE_LOCK)) {
			echo 'Stopping last running transaction... ';
			Lock::stopTransaction(self::MAINTENANCE_LOCK);
			echo 'done.' . "\n\n";
		}

		echo "\n" . 'Composer post autoload dump task manager' . "\n" . str_repeat('=', 40) . "\n\n";
		echo ConsoleHelpers::terminalRenderLabel('Server time') . ': ' . date('Y-m-d H:i:s') . "\n";
		if (defined('PHP_VERSION') && defined('PHP_OS')) {
			echo ConsoleHelpers::terminalRenderLabel('Using PHP version') . ': ' . PHP_VERSION . ' (' . PHP_OS . ')' . "\n";
		}
		if (isset($_SERVER['USER'])) {
			echo ConsoleHelpers::terminalRenderLabel('User') . ': ' . $_SERVER['USER'] . "\n";
		}
		if (isset($_SERVER['SCRIPT_FILENAME'])) {
			echo ConsoleHelpers::terminalRenderLabel('Called by script') . ': ' . $_SERVER['SCRIPT_FILENAME'] . "\n";
		}
		if (isset($_SERVER['argv'], $_SERVER['argc']) && $_SERVER['argc'] > 0) {
			echo ConsoleHelpers::terminalRenderLabel('Command arguments') . ':' . "\n";
			echo '   - ' . implode("\n" . '   - ', $_SERVER['argv']) . "\n";
		}
		echo "\n" . 'CI status' . "\n" . '=========' . "\n\n";
		self::composerRenderCiDetectorInfo();
		echo "\n";

		echo 'Starting safe transaction... ';
		try {
			Lock::startTransaction(self::MAINTENANCE_LOCK, maxExecutionTimeMs: 60000);
			echo 'started.' . "\n";
			echo 'Waiting for empty request pool...';
			sleep(3);
			echo "\n\n";

			echo 'Runtime mode' . "\n" . '============' . "\n\n";
			if (isset($_SERVER['argv'][2]) === true && $_SERVER['argv'][2] === '--') {
				self::$configurationMode = true;
			}
			if (self::isConfigurationMode() === true) {
				echo '️⚙️️  This is a advance configuration mode.' . "\n";
			} else {
				echo '️⚔️  This is a regular mode.' . "\n";
				echo '   If you want use advance configuration, please use command "composer dump --".' . "\n";
			}
			echo "\n";

			try {
				FileSystem::delete(dirname(__DIR__, 4) . '/temp/cache/baraja/packageDescriptor');
				FileSystem::delete(dirname(__DIR__, 4) . '/app/config/package.neon');
				$tempDir = dirname(__DIR__, 4) . '/temp';
				if (is_dir($tempDir)) {
					foreach (new \FilesystemIterator($tempDir) as $item) {
						$path = is_string($item) ? $item : (string) $item->getPathname();
						FileSystem::makeWritable($path);
						FileSystem::delete($path);
					}
				}
			} catch (\Throwable $e) {
				self::get();
				trigger_error($e->getMessage());
			}

			echo 'Init Composer autoload' . "\n" . '======================' . "\n";
			try {
				$composerFileAutoloadPath = __DIR__ . '/../../../composer/autoload_files.php';
				if (is_file($composerFileAutoloadPath)) {
					foreach (require $composerFileAutoloadPath as $file) {
						if (str_contains((string) file_get_contents($file), '--package-registrator-task--')) {
							require_once $file;
						}
					}
					ConsoleHelpers::terminalRenderSuccess('[OK] Successfully loaded.');
				} else {
					ConsoleHelpers::terminalRenderError('Can not load autoload files.');
				}
			} catch (\Throwable $e) {
				ConsoleHelpers::terminalRenderError($e->getMessage());
				ConsoleHelpers::terminalRenderCode($e->getFile(), $e->getLine());
				Debugger::log($e, 'critical');
				echo 'Error was logged to file.' . "\n\n";
			}
		} catch (\Throwable $e) {
			Lock::stopTransaction(self::MAINTENANCE_LOCK);
			Debugger::log($e, ILogger::CRITICAL);
		}
		Terminator::addHandler(self::get());

		try {
			(new InteractiveComposer)->run(TaskManager::get());
		} catch (\Throwable $e) {
			ConsoleHelpers::terminalRenderError($e->getMessage());
			ConsoleHelpers::terminalRenderCode($e->getFile(), $e->getLine());
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
			ConsoleHelpers::terminalRenderError($e->getMessage());
			ConsoleHelpers::terminalRenderCode($e->getFile(), $e->getLine());
			Debugger::log($e, 'critical');
			echo 'Error was logged to file.' . "\n\n";
			$ci = null;
		}

		echo($ci === null ? 'No detected.' : 'Detected 👍') . "\n";
		if ($ci !== null) {
			echo ' | CI name: ' . $ci->getCiName() . "\n";
			echo ' | is Pull request? ' . $ci->isPullRequest()->describe() . "\n";
			echo ' | Build number: ' . $ci->getBuildNumber() . "\n";
			echo ' | Build URL: ' . $ci->getBuildUrl() . "\n";
			echo ' | Git commit: ' . $ci->getGitCommit() . "\n";
			echo ' | Git branch: ' . $ci->getGitBranch() . "\n";
			echo ' | Repository name: ' . $ci->getRepositoryName() . "\n";
			echo ' | Repository URL: ' . $ci->getRepositoryUrl() . "\n";
		}
	}


	/**
	 * @throws PackageDescriptorException
	 */
	public static function getCiDetect(): ?CiInterface
	{
		/** @var CiInterface|null */
		static $cache;
		if ($cache === null) {
			$ciDetector = new CiDetector;
			if ($ciDetector->isCiDetected()) {
				$cache = $ciDetector->detect();
			}
		}

		return $cache;
	}


	public static function getPackageDescriptorEntityStatic(): PackageDescriptorEntityInterface
	{
		return self::$packageDescriptorEntity;
	}


	private static function setupInternalLock(?string $tempDir = null): void
	{
		if ($tempDir === null) {
			$tempDir = (new TempDirResolver(new RootDirResolver(new VendorResolver)))->get();
		}
		Lock::setTransactionProvider(new FileTransactionProvider($tempDir . '/cache/lock/package-manager'));
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
