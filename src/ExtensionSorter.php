<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


final class ExtensionSorter
{
	public const TRY_SORT_TTL = 3;


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . static::class . ' is static and cannot be instantiated.');
	}


	/**
	 * @param array<string, string|\stdClass|mixed> $extensions
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
				throw new \InvalidArgumentException('Definition type must be string, object or array, but "' . get_debug_type($definition) . '" given.');
			}
			if (class_exists($type) === false) {
				throw new \RuntimeException(
					'Package manager: Extension "' . $type . '" does not exist. Did you use autoload correctly?' . "\n"
					. 'Hint: Try read article about autoloading: https://php.baraja.cz/autoloading-trid',
				);
			}

			/** @var array{value?: string, attributes?: array<int, string>}|string $typedDefinition */
			$typedDefinition = $definition;

			$items[] = [
				'key' => $key,
				'type' => $type,
				'definition' => $typedDefinition,
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
	 * @return array<int, string>|null
	 */
	private static function invokeStaticMethodSafe(string $class, string $method): ?array
	{
		if (\class_exists($class) === false) {
			throw new \RuntimeException('Extension class "' . $class . '" does not exist.');
		}
		$return = [];
		$ref = new \ReflectionClass($class);
		foreach ($ref->getAttributes(ExtensionsMeta::class) as $attribute) {
			$return[] = $attribute->getArguments()[$method] ?? [];
		}
		if (\method_exists($class, $method) === true) { // back compatibility
			/** @phpstan-ignore-next-line */
			$methodReturn = ((array) call_user_func($class . '::' . $method)) ?: null;
			if ($methodReturn !== null) {
				$return[] = $methodReturn;
			}
		}

		return array_merge([], ...$return) ?: null;
	}


	/**
	 * @param array<int, array{type: class-string, key: string, definition: string|array{value?: string, attributes?: array<int, string>}}> $candidates
	 * @return array<int, array{type: class-string, key: string, definition: string|array{value?: string, attributes?: array<int, string>}}>
	 */
	private static function sortCandidatesByConditions(array $candidates): array
	{
		$return = [];
		$registered = [];
		$castlingTtl = [];
		while ($candidates !== []) {
			$candidateKey = array_keys($candidates)[0] ?? null;
			if ($candidateKey === null) {
				break;
			}
			$candidate = $candidates[$candidateKey] ?? null;
			if ($candidate === null) {
				throw new \RuntimeException(sprintf('Candidate key "%s" is broken.', $candidateKey));
			}

			$position = null;
			if ($return === []) {
				$position = 0;
			} else {
				foreach (array_keys($return) as $returnPosition) {
					try {
						if (self::canBeItemAddedHere($candidate, $returnPosition, $return, $registered)) {
							$position = $returnPosition;
							break;
						}
					} catch (\InvalidArgumentException) { // move candidate item to end of candidates list
						if (isset($castlingTtl[$candidate['type']]) === false) {
							$castlingTtl[$candidate['type']] = 0;
						}
						if (($castlingTtl[$candidate['type']]++) > self::TRY_SORT_TTL) {
							throw new \RuntimeException(
								'Infinite recursion was detected while trying to sort the extension.' . "\n"
								. 'Possible solution: If you want to register extensions, simplify the conditions so that they do not refute each other.',
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
					. 'Successfully registered extensions: "' . implode('", "', $registered) . '".',
				);
			}
		}

		return $return;
	}


	/**
	 * @param array{mustBeDefinedBefore?: array<int, class-string>, mustBeDefinedAfter?: array<int, class-string>} $item
	 * @param array<int, array{type: class-string}> $items
	 * @param array<int, string> $registered
	 */
	private static function canBeItemAddedHere(array $item, int $position, array $items, array $registered): bool
	{
		$before = $item['mustBeDefinedBefore'] ?? [];
		$after = $item['mustBeDefinedAfter'] ?? [];
		if ($before === [] && $after === []) {
			return true;
		}
		foreach (\array_merge($before, $after) as $dependency) { // contains all dependencies?
			if (\in_array($dependency, $registered, true) === false) {
				throw new \InvalidArgumentException(sprintf('Dependency "%s" is not available now, skipped.', $dependency));
			}
		}
		if ($position === 0 && $before !== []) {
			return false;
		}
		if ($before !== []) { // all numbers must be bigger than $position
			foreach (self::getDependencyPositions($before, $items) as $dependencyBefore) {
				if ($dependencyBefore > $position) {
					return false;
				}
			}
		}
		if ($after !== []) {
			foreach (self::getDependencyPositions($after, $items) as $dependencyBefore) {
				if ($dependencyBefore < $position) {
					return false;
				}
			}
		}

		return true;
	}


	/**
	 * @param array<int, array{type: class-string, key: string, definition: string|array{value?: string, attributes?: array<int, string>}}> $finalArray
	 * @param array{type: class-string, key: string, definition: string|array{value?: string, attributes?: array<int, string>}} $inserted
	 * @return array<int, array{type: class-string, key: string, definition: string|array{value?: string, attributes?: array<int, string>}}>
	 */
	private static function insertBefore(array $finalArray, int $key, array $inserted): array
	{
		$hasInserted = false;
		$return = [];
		foreach ($finalArray as $finalKey => $finalValue) {
			if ($finalKey === $key) {
				$return[] = $inserted;
				$hasInserted = true;
			}
			$return[] = $finalValue;
		}
		if ($hasInserted === false) {
			$return[] = $inserted;
		}

		return $return;
	}


	/**
	 * @param array<int, string> $dependencies
	 * @param array<int, array{type: class-string}> $items
	 * @return array<int, int>
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
	 * @param string|array{value?: string, attributes?: array<int, string>} $definition
	 */
	private static function encodeExtensionDefinition(string|array $definition): string
	{
		if (is_string($definition)) {
			return $definition;
		}
		if (isset($definition['value'], $definition['attributes'])) {
			return $definition['value'] . '(' . implode(', ', $definition['attributes']) . ')';
		}

		throw new \InvalidArgumentException('Extension definition is not valid DIC definition.' . "\n\n" . json_encode($definition));
	}
}
