<?php

declare(strict_types=1);

namespace Baraja\PackageManager\Composer;


use Baraja\PackageManager\PackageRegistrator;

final class TaskManager
{
	/** @var ITask[] */
	private array $tasks = [];

	private ?CompanyIdentity $companyIdentity = null;


	public static function get(): self
	{
		static $taskManager;
		if ($taskManager === null) {
			$packageRegistrator = PackageRegistrator::get();
			$taskManager = new self;
			$taskManager->addTask(new ConfigLocalNeonTask($packageRegistrator));
			$taskManager->addTask(new AssetsFromPackageTask($packageRegistrator));
			$taskManager->addTask(new ClearCacheTask($packageRegistrator));
			$taskManager->addTask(new ComposerJsonTask($packageRegistrator));
		}

		return $taskManager;
	}


	public function getCompanyIdentity(): ?CompanyIdentity
	{
		return $this->companyIdentity;
	}


	public function setCompanyIdentity(?CompanyIdentity $companyIdentity): void
	{
		$this->companyIdentity = $companyIdentity;
	}


	public function addTask(ITask $task): void
	{
		$this->tasks[] = $task;
	}


	/**
	 * @return ITask[]
	 */
	public function getTasks(): array
	{
		return $this->tasks;
	}


	/**
	 * @return TaskItem[]
	 */
	public function getSortedTasks(): array
	{
		$return = [];
		foreach ($this->tasks as $task) {
			try {
				$priority = ($doc = (new \ReflectionClass($task))->getDocComment()) !== false
				&& preg_match('/Priority:\s*(\d+)/', $doc, $docParser)
					? (int) $docParser[1]
					: 10;

				$return[\get_class($task)] = new TaskItem($task, $priority);
			} catch (\ReflectionException $e) {
			}
		}

		usort($return, fn (TaskItem $a, TaskItem $b): int => $a->getPriority() < $b->getPriority() ? 1 : -1);

		return $return;
	}
}
