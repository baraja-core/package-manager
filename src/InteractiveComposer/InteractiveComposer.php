<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\Console\Helpers as ConsoleHelpers;
use Baraja\PackageManager\Composer\TaskManager;
use Tracy\Debugger;

final class InteractiveComposer
{
	public function run(TaskManager $taskManager): void
	{
		if (($identity = $taskManager->getCompanyIdentity()) !== null) {
			echo $identity->getLogo() . "\n";
		}
		foreach ($taskManager->getSortedTasks() as $task) {
			echo "\n" . str_repeat('-', 100) . "\n";
			echo "\e[0;32;40m" . '🍏 [' . $task->getPriority() . ']: ' . $task->getTask()->getName() . "\e[0m\n";

			try {
				if ($task->getTask()->run() === true) {
					echo "\n\n" . '👍 ' . "\e[1;33;40m" . 'Task was successful. 👍' . "\e[0m";
				} else {
					echo "\n\n";
					ConsoleHelpers::terminalRenderError('Task "' . $task->getClassName() . '" failed!');
					echo "\n\n";
					die;
				}
			} catch (\RuntimeException $e) {
				echo "\n\n";
				ConsoleHelpers::terminalRenderError('Task "' . $task->getClassName() . '" failed!' . "\n\n" . $e->getMessage());
				if (\class_exists(Debugger::class) === true) {
					Debugger::log($e, 'critical');
					echo "\n\n" . 'Error was logged by Tracy.';
				} else {
					echo "\n\n" . 'Can not log error, because Tracy is not available.';
				}
				echo "\n\n";
				die;
			}
		}

		echo "\n" . str_repeat('-', 100) . "\n\n\n" . 'All tasks completed successfully.' . "\n\n\n";
	}
}
