<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


final class ExtensionSorter
{
	public const TRY_SORT_TTL = 3;


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . \get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * @param string[]|\stdClass[]|mixed[] $extensions
	 */
	public static function serializeExtensionList(array $extensions): string
	{
		$items = [];
		foreach ($extensions as $key => $definition) {
			if (\is_string($definition)) {
				$type = $definition;
			} elseif (\is_object($definition) && isset($definition->value)) {
				$type = (string) $definition->value;
			} elseif (\is_array($definition) && isset($definition['value'])) {
				$type = (string) $definition['value'];
			} else {
				throw new \InvalidArgumentException('Definition type must be string, object or array, but "' . \gettype($definition) . '" given.');
			}
			if (class_exists($type) === false) {
				throw new \RuntimeException(
					'Package manager: Extension "' . $type . '" does not exist. Did you use autoload correctly?' . "\n"
					. 'Hint: Try read article about autoloading: https://php.baraja.cz/autoloading-trid'
				);
			}

			$items[] = [
				'key' => $key,
				'type' => $type,
				'definition' => $definition,
				'mustBeDefinedBefore' => self::invokeStaticMethodSafe($type, 'mustBeDefinedBefore'),
				'mustBeDefinedAfter' => self::invokeStaticMethodSafe($type, 'mustBeDefinedAfter'),
			];
		}

		$return = '';
		foreach (self::sortCandidatesByConditions($items) as $item) {
			$return .= "\t" . $item['key'] . ': ' . self::encodeExtensionDefinition($item['definition']) . "\n";
		}

		return 'extensions:' . "\n" . $return;
	}


	/**
	 * @return string[]|null
	 */
	private static function invokeStaticMethodSafe(string $class, string $method): ?array
	{
		if (\class_exists($class) === false) {
			throw new \RuntimeException('Extension class "' . $class . '" does not exist.');
		}
		if (\method_exists($class, $method) === false) {
			return null;
		}

		/** @phpstan-ignore-next-line */
		return ((array) call_user_func($class . '::' . $method)) ?: null;
	}


	/**
	 * @param mixed[][] $candidates
	 * @return mixed[][]
	 */
	private static function sortCandidatesByConditions(array $candidates): array
	{
		$return = [];
		$registered = [];
		$castlingTtl = [];
		while ($candidates !== []) {
			if (($candidateKey = array_keys($candidates)[0] ?? null) === null) {
				break;
			}
			if (($candidate = $candidates[$candidateKey] ?? null) === null) {
				throw new \RuntimeException('Candidate key "' . $candidateKey . '" is broken.');
			}

			$position = null;
			if ($return === []) {
				$position = 0;
			} else {
				foreach ($return as $returnPosition => $returnItem) {
					try {
						if (self::canBeItemAddedHere($candidate, $returnPosition, $return, $registered)) {
							$position = $returnPosition;
							break;
						}
					} catch (\InvalidArgumentException $e) { // move candidate item to end of candidatest list
						if (isset($castlingTtl[$candidate['type']]) === false) {
							$castlingTtl[$candidate['type']] = 0;
						}
						if (($castlingTtl[$candidate['type']]++) > self::TRY_SORT_TTL) {
							throw new \RuntimeException(
								'Infinite recursion was detected while trying to sort the extension.' . "\n"
								. 'Possible solution: If you want to register extensions, simplify the conditions so that they do not refute each other.'
							);
						}
						if (\count($candidates) > 1) {
							unset($candidates[$candidateKey]);
							$candidates[] = $candidate;
							$position = -1;
						}
						break;
					}
				}
			}
			if ($position === -1) { // candidate castling
				continue;
			}
			if ($position !== null) {
				unset($candidates[$candidateKey]);
				$registered[] = $candidate['type'];
				$return = self::insertBefore($return, ((int) $position) - 1, $candidate);
			} else {
				throw new \RuntimeException(
					'Internal conflict in dependencies: Item "' . $candidate['type'] . '" requires conditions that conflict with another extension.' . "\n"
					. 'To solve this issue: Please check your items configuration and use tree dependencies only.' . "\n"
					. 'Sucessfully registered extensions: "' . implode('", "', $registered) . '".'
				);
			}
		}

		return $return;
	}


	/**
	 * @param mixed[] $item
	 * @param mixed[][] $items
	 * @param string[] $registered
	 */
	private static function canBeItemAddedHere(array $item, int $position, array $items, array $registered): bool
	{
		$before = $item['mustBeDefinedBefore'] ?? null;
		$after = $item['mustBeDefinedAfter'] ?? null;
		if ($before === null && $after === null) {
			return true;
		}
		foreach (\array_merge($before ?? [], $after ?? []) as $dependency) { // contains all dependencies?
			if (\in_array($dependency, $registered, true) === false) {
				throw new \InvalidArgumentException('Dependency "' . $dependency . '" is not available now, skipped.');
			}
		}
		if ($position === 0 && $before !== null) {
			return false;
		}
		if ($before !== null) { // all numbers must be bigger than $position
			foreach (self::getDependencyPositions($before ?? [], $items) as $dependencyBefore) {
				if ($dependencyBefore > $position) {
					return false;
				}
			}
		}
		if ($after !== null) {
			foreach (self::getDependencyPositions($after ?? [], $items) as $dependencyBefore) {
				if ($dependencyBefore < $position) {
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * @param mixed[][] $finalArray
	 * @param mixed[] $inserted
	 * @return mixed[][]
	 */
	private static function insertBefore(array $finalArray, int $key, array $inserted): array
	{
		$wasInserted = false;
		$return = [];
		foreach ($finalArray as $finalKey => $finalValue) {
			if ($finalKey === $key) {
				$return[] = $inserted;
				$wasInserted = true;
			}
			$return[] = $finalValue;
		}
		if ($wasInserted === false) {
			$return[] = $inserted;
		}

		return $return;
	}


	/**
	 * @param string[] $dependencies
	 * @param mixed[][] $items
	 * @return int[]
	 */
	private static function getDependencyPositions(array $dependencies, array $items): array
	{
		$return = [];
		foreach ($items as $key => $value) {
			if (\in_array($value['type'], $dependencies, true) === true) {
				$return[] = $key;
			}
		}

		return $return;
	}


	/**
	 * @param string|mixed[]|\stdClass[] $definition
	 */
	private static function encodeExtensionDefinition($definition): string
	{
		if (\is_string($definition)) {
			return $definition;
		}
		$definition = (array) $definition;
		if (isset($definition['value'], $definition['attributes'])) {
			return $definition['value'] . '(' . implode(', ', $definition['attributes']) . ')';
		}

		throw new \InvalidArgumentException('Extension definition is not valid DIC definition.' . "\n\n" . json_encode($definition));
	}
}
