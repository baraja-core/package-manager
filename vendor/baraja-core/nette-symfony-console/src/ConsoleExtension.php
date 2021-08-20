<?php

declare(strict_types=1);

namespace Baraja\Console;


use Baraja\Doctrine\DBAL\DI\DbalConsoleExtension;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Schema\ValidationException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;

final class ConsoleExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		if (class_exists('\Baraja\Doctrine\DBAL\DI\DbalConsoleExtension')) {
			return [DbalConsoleExtension::class];
		}

		return [];
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'url' => Expect::anyOf(Expect::string(), Expect::null()),
			'name' => Expect::string('Baraja sandbox'),
			'version' => Expect::anyOf(Expect::string(), Expect::int(), Expect::float())->default('3.0'),
			'catchExceptions' => Expect::bool(true),
			'autoExit' => Expect::bool(true),
			'helperSet' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))
				->assert(static function ($helperSet) {
					if ($helperSet === null) {
						throw new ValidationException('helperSet cannot be null');
					}

					return true;
				}),
			'helpers' => Expect::arrayOf(
				Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class))
			),
			'lazy' => Expect::bool(false),
		])->castTo('array');
	}


	public function loadConfiguration(): void
	{
		if (PHP_SAPI !== 'cli') { // Skip if isn't CLI
			return;
		}

		$builder = $this->getContainerBuilder();
		/** @var mixed[] $config */
		$config = $this->getConfig();
		$definitionHelper = new ExtensionDefinitionsHelper($this->compiler);

		// Register Symfony Console Application
		$applicationDef = $builder->addDefinition($this->prefix('application'))
			->setFactory(Application::class);

		if (($config['name'] ?? null) !== null) { // Setup console name
			$applicationDef->addSetup('setName', [$config['name']]);
		}
		if (($config['version'] ?? null) !== null) { // Setup console version
			$applicationDef->addSetup('setVersion', [(string) $config['version']]);
		}
		if (($config['catchExceptions'] ?? null) !== null) { // Catch or populate exceptions
			$applicationDef->addSetup('setCatchExceptions', [$config['catchExceptions']]);
		}
		if (($config['autoExit'] ?? null) !== null) { // Call die() or not
			$applicationDef->addSetup('setAutoExit', [$config['autoExit']]);
		}
		if (($config['helperSet'] ?? null) !== null) { // Register given or default HelperSet
			$applicationDef->addSetup('setHelperSet', [
				$definitionHelper->getDefinitionFromConfig($config['helperSet'], $this->prefix('helperSet')),
			]);
		}
		foreach ($config['helpers'] ?? [] as $helperName => $helperConfig) { // Register extra helpers
			$helperPrefix = $this->prefix('helper.' . $helperName);
			$helperDef = $definitionHelper->getDefinitionFromConfig($helperConfig, $helperPrefix);

			if ($helperDef instanceof Definition) {
				$helperDef->setAutowired(false);
			}

			$applicationDef->addSetup('?->getHelperSet()->set(?)', ['@self', $helperDef]);
		}
		if ($config['lazy'] ?? false) { // Commands lazy loading
			$builder->addDefinition($this->prefix('commandLoader'))
				->setType(CommandLoaderInterface::class)
				->setFactory(ContainerCommandLoader::class);

			$applicationDef->addSetup('setCommandLoader', ['@' . $this->prefix('commandLoader')]);
		}
		$applicationDef->addSetup('?->addCommands(' . Console::class . '::registerCommands($this))', ['@self']);
	}


	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			$class->getMethod('initialize')->addBody(
				'// Console.' . "\n"
				. '(function () {' . "\n"
				. "\t" . 'if (isset($_SERVER[\'NETTE_TESTER_RUNNER\']) === true) { return; }' . "\n"
				. "\t" . 'new ' . Console::class . '($this->getByType(?), $this->getByType(?));' . "\n"
				. '})();' . "\n",
				[Application::class, \Nette\Application\Application::class]
			);
		}
	}
}
