<?php

declare(strict_types=1);

namespace Baraja\Console;


use Nette\Application\Application as NetteApplication;
use Nette\DI\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Tracy\Debugger;

final class Console
{
	public function __construct(Application $consoleApplication, NetteApplication $netteApplication)
	{
		if ($this->consoleAlreadyHasCalled() === true) {
			return;
		}
		$netteApplication->onStartup[] = function (NetteApplication $application) use ($consoleApplication): void {
			$this->run($consoleApplication);
		};
	}


	/**
	 * The runtime method finds all compatible services that are valid
	 * Symfony command and registers them in the application.
	 * The search must be performed at runtime to detect any commands
	 * that insert any extensions or other services.
	 *
	 * @return Command[]
	 */
	public static function registerCommands(Container $container): array
	{
		return (static function (array $services, Container $container): array {
			$return = [];
			foreach ($services as $serviceName) {
				try {
					/** @var Command $command */
					$command = $container->getService($serviceName);
					if ($command->getName() === null) {
						$command->setName($serviceName);
					}
					$return[] = $command;
				} catch (\Throwable $e) {
					trigger_error('Command error: ' . $e->getMessage());
				}
			}

			return $return;
		})($container->findByType(Command::class), $container);
	}


	/**
	 * Simple console wrapper for call internal command by index.php.
	 */
	private function run(Application $consoleApplication): void
	{
		try {
			$consoleApplication->setAutoExit(false);
			$runCode = $consoleApplication->run();
			echo "\n" . 'Exit with code #' . $runCode;
			exit($runCode);
		} catch (\Throwable $e) {
			if (\class_exists(Debugger::class) === true) {
				Debugger::log($e, 'critical');
			}
			if (\is_file($logPath = \dirname(__DIR__, 4) . '/log/exception.log') === true) {
				$data = file($logPath);
				$logLine = trim((string) ($data === false ? '???' : $data[\count($data) - 1] ?? '???'));

				if (preg_match('/((?:debug|info|warning|error|exception|critical)--[\d-]+--[a-f\d]+\.html)/', $logLine, $logLineParser)) {
					Helpers::terminalRenderError('Logged to file: ' . $logLineParser[1]);
				}

				Helpers::terminalRenderError($logLine);
				Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			} else {
				Helpers::terminalRenderError($e->getMessage());
				Helpers::terminalRenderCode($e->getFile(), $e->getLine());
			}

			echo "\n" . 'Exit with code #' . ($exitCode = $e->getCode() ?: 1);
			exit($exitCode);
		}
	}


	private function consoleAlreadyHasCalled(): bool
	{
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $item) {
			if (isset($item['file']) && substr(str_replace('\\', '/', $item['file']), -31) === 'symfony/console/Application.php') {
				return true;
			}
		}

		return false;
	}
}
