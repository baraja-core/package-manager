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
		$identity = $taskManager->getCompanyIdentity();
		if ($identity !== null) {
			echo $identity->getLogo() . "\n";
		}

		echo "\n" . 'Tasks' . "\n" . '=====' . "\n\n";
		foreach ($taskManager->getSortedTasks() as $task) {
			echo ConsoleHelpers::terminalRenderLabel('[' . $task->getPriority() . '] ' . $task->getTask()->getName()) . "\n";
			echo ConsoleHelpers::terminalRenderLabel(str_repeat('-', 3 + mb_strlen($task->getTask()->getName(), 'UTF-8') + strlen((string) $task->getPriority()))) . "\n\n";

			try {
				if ($task->getTask()->run() === true) {
					echo "\n\n\e[0;32;40m" . '[OK] Task was successful.' . "\e[0m\n\n";
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

		echo "\n\n";
		ConsoleHelpers::terminalRenderSuccess('All tasks has been completed successfully.');
		echo "\n\n";
	}
}
