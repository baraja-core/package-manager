<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Exception;


final class TaskException extends PackageDescriptorException
{

	/**
	 * @throws TaskException
	 */
	public static function canNotCopyFile(string $path): void
	{
		throw new self('Can not copy "' . $path . '".');
	}


	/**
	 * @throws TaskException
	 */
	public static function canNotCreateProjectDirectory(string $path): void
	{
		throw new self('Can not create directory "' . $path . '".');
	}


	/**
	 * @throws TaskException
	 */
	public static function canNotCopyProjectFile(string $from, string $to): void
	{
		$return = null;
		if (($lastError = error_get_last()) && isset($lastError['message']) === true) {
			$return = trim((string) preg_replace('/\s*\[<a[^>]+>[a-z0-9.\-_()]+<\/a>]\s*/i', ' ', (string) $lastError['message']));
		}

		throw new self(
			'Can not copy file "' . $from . '" => "' . $to . '"'
			. ($return !== null ? ': ' . $return : '')
		);
	}
}
