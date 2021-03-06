<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


class Jenkins extends AbstractCi
{
	public function isDetected(Environment $environment): bool
	{
		return $environment->get('JENKINS_URL') !== false;
	}


	public function getCiName(): string
	{
		return CiDetector::CI_JENKINS;
	}


	public function isPullRequest(): TrinaryLogic
	{
		return TrinaryLogic::createMaybe();
	}


	public function getBuildNumber(): string
	{
		return $this->env->getString('BUILD_NUMBER');
	}


	public function getBuildUrl(): string
	{
		return $this->env->getString('BUILD_URL');
	}


	public function getGitCommit(): string
	{
		return $this->env->getString('GIT_COMMIT');
	}


	public function getGitBranch(): string
	{
		return $this->env->getString('GIT_BRANCH');
	}


	public function getRepositoryName(): string
	{
		return ''; // unsupported
	}


	public function getRepositoryUrl(): string
	{
		return $this->env->getString('GIT_URL');
	}
}
