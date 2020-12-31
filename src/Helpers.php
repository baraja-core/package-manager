<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


/**
 * @internal
 */
final class Helpers
{

	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * Merge right set to left set recursively.
	 *
	 * @param mixed[] $left
	 * @param mixed[] $right
	 * @return mixed[]
	 */
	public static function recursiveMerge(array &$left, array &$right): array
	{
		$return = $left;
		foreach ($right as $key => &$value) {
			if (\is_array($value) && isset($return[$key]) && \is_array($return[$key])) {
				$return[$key] = self::recursiveMerge($return[$key], $value);
			} elseif (\is_int($key)) {
				$return[] = $value;
			} else {
				$return[$key] = $value;
			}
		}

		return $return;
	}


	public static function functionIsAvailable(string $functionName): bool
	{
		static $disabled;
		if (\function_exists($functionName) === true) {
			if ($disabled === null && \is_string($disableFunctions = ini_get('disable_functions'))) {
				$disabled = explode(',', (string) $disableFunctions);
			}

			return \in_array($functionName, $disabled, true) === false;
		}

		return false;
	}


	/**
	 * Convert Nette SmartObject with private methods to Nette ArrayHash structure.
	 * While converting call getters, so you get only properties which you can get.
	 * Function supports recursive objects structure. Public properties will be included.
	 *
	 * @param mixed $input
	 * @return mixed
	 */
	public static function haystackToArray($input)
	{
		if (\is_object($input)) {
			try {
				$reflection = new \ReflectionClass($input);
			} catch (\ReflectionException $e) {
				return null;
			}

			$return = [];
			if ($input instanceof \stdClass) {
				$return = (array) json_decode((string) json_encode($input), true);
			} else {
				foreach ($reflection->getProperties() as $property) {
					$return[$property->getName()] = self::haystackToArray($property->getValue($input));
				}
				foreach ($reflection->getMethods() as $method) {
					if ($method->name !== 'getReflection' && preg_match('/^(get|is)(.+)$/', $method->name, $_method)) {
						$return[lcfirst($_method[2])] = self::haystackToArray($input->{$method->name}());
					}
				}
			}
		} elseif (\is_array($input)) {
			$return = [];
			foreach ($input as $k => $v) {
				$return[$k] = self::haystackToArray($v);
			}
		} else {
			$return = $input;
		}

		return $return;
	}


	/**
	 * Ask question in Terminal and return user answer (string or null if empty).
	 *
	 * Function will be asked since user give valid answer.
	 *
	 * @param string $question -> only display to user
	 * @param string[]|null $possibilities -> if empty, answer can be every valid string or null.
	 * @return string|null -> null if empty answer
	 */
	public static function terminalInteractiveAsk(string $question, ?array $possibilities = null): ?string
	{
		if (PHP_SAPI !== 'cli') {
			throw new \RuntimeException('Terminal: This method is available only in CLI mode.');
		}
		static $staticTtl = 0;
		echo "\n" . str_repeat('-', 100) . "\n";
		if ($possibilities !== [] && $possibilities !== null) {
			$renderPossibilities = static function (array $possibilities): string {
				$return = '';
				$containsNull = false;

				foreach ($possibilities as $possibility) {
					if ($possibility !== null) {
						$return .= ($return === '' ? '' : '", "') . $possibility;
					} elseif ($containsNull === false) {
						$containsNull = true;
					}
				}

				return 'Possible values: "' . $return . '"' . ($containsNull ? ' or press ENTER' : '') . '.';
			};

			echo $renderPossibilities($possibilities) . "\n";
		}

		echo 'Q: ' . trim($question) . "\n" . 'A: ';

		$fOpen = fopen('php://stdin', 'rb');
		if (\is_resource($fOpen) === false) {
			throw new \RuntimeException('Problem with opening "php://stdin".');
		}

		$input = ($input = trim((string) fgets($fOpen))) === '' ? null : $input;
		if ($possibilities !== [] && $possibilities !== null) {
			if (\in_array($input, $possibilities, true)) {
				return $input;
			}

			self::terminalRenderError('Invalid answer!');
			$staticTtl++;

			if ($staticTtl > 16) {
				throw new \RuntimeException('The maximum invalid response limit was exceeded. Current limit: ' . $staticTtl);
			}

			return self::terminalInteractiveAsk($question, $possibilities);
		}

		return $input;
	}


	/** Render red block with error message. */
	public static function terminalRenderError(string $message): void
	{
		\Baraja\Console\Helpers::terminalRenderError($message);
	}


	/**
	 * Returns number of characters (not bytes) in UTF-8 string.
	 * That is the number of Unicode code points which may differ from the number of graphemes.
	 */
	private static function length(string $s): int
	{
		return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode($s));
	}
}
