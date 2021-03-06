<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


use Baraja\PackageManager\Exception\PackageDescriptorException;

/**
 * @see https://github.com/OndraM/ci-detector
 */
final class CiDetector
{
	public const
		CI_APPVEYOR = 'AppVeyor',
		CI_BAMBOO = 'Bamboo',
		CI_BUDDY = 'Buddy',
		CI_CIRCLE = 'CircleCI',
		CI_CODESHIP = 'Codeship',
		CI_CONTINUOUSPHP = 'continuousphp',
		CI_DRONE = 'drone',
		CI_GITHUB_ACTIONS = 'GitHub Actions',
		CI_GITLAB = 'GitLab',
		CI_JENKINS = 'Jenkins',
		CI_TEAMCITY = 'TeamCity',
		CI_TRAVIS = 'Travis CI';

	private Environment $environment;


	public function __construct()
	{
		$this->environment = new Environment;
	}


	/**
	 * Is current environment an recognized CI server?
	 */
	public function isCiDetected(): bool
	{
		return $this->detectCurrentCiServer() !== null;
	}


	/**
	 * Detect current CI server and return instance of its settings
	 *
	 * @throws PackageDescriptorException
	 */
	public function detect(): CiInterface
	{
		if (($ciServer = $this->detectCurrentCiServer()) === null) {
			throw new PackageDescriptorException('No CI server detected in current environment');
		}

		return $ciServer;
	}


	/**
	 * @return string[]
	 */
	private function getCiServers(): array
	{
		return [
			AppVeyor::class,
			Bamboo::class,
			Buddy::class,
			Circle::class,
			Codeship::class,
			Continuousphp::class,
			Drone::class,
			GitHubActions::class,
			GitLab::class,
			Jenkins::class,
			TeamCity::class,
			Travis::class,
		];
	}


	private function detectCurrentCiServer(): ?CiInterface
	{
		foreach ($this->getCiServers() as $ciClass) {
			if (\class_exists($ciClass) === false) {
				throw new \RuntimeException('CI service "' . $ciClass . '" does not exist or is not autoloadable.');
			}
			/** @var CiInterface $ci */
			$ci = new $ciClass($this->environment);
			if ($ci->isDetected($this->environment)) {
				return $ci;
			}
		}

		return null;
	}
}
