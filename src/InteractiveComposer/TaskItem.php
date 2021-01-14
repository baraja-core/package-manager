<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


final class TaskItem
{
	private ITask $task;

	private int $priority;


	public function __construct(ITask $task, int $priority)
	{
		$this->task = $task;
		$this->priority = $priority;
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
		return \get_class($this->task);
	}
}