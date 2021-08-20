<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


final class TaskItem
{
	public function __construct(
		private ITask $task,
		private int $priority,
	) {
	}


	public function getTask(): ITask
	{
		return $this->task;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function getClassName(): string
	{
		return $this->task::class;
	}
}
