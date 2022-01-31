<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


use Baraja\PackageManager\Helpers;
use Nette\Utils\FileSystem;

/**
 * Priority: 100
 */
final class ClearCacheTask extends BaseTask
{
	public function run(): bool
	{
		if (Helpers::functionIsAvailable('opcache_reset')) {
			@opcache_reset();
		}

		$tempPath = \dirname(__DIR__, 6) . '/temp';
		echo 'Path: ' . $tempPath;
		try {
			FileSystem::makeWritable($tempPath);
		} catch (\Throwable) {
			// Silence is golden.
		}
		FileSystem::delete($tempPath);
		FileSystem::createDir($tempPath);
		FileSystem::makeWritable($tempPath);

		return true;
	}


	public function getName(): string
	{
		return 'Clear cache';
	}
}
