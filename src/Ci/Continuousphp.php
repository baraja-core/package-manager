<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


class Continuousphp extends AbstractCi
{
	public function isDetected(Environment $environment): bool
	{
		return $environment->get('CONTINUOUSPHP') === 'continuousphp';
	}


	public function getCiName(): string
	{
		return CiDetector::CI_CONTINUOUSPHP;
	}


	public function isPullRequest(): TrinaryLogic
	{
		return TrinaryLogic::createFromBoolean($this->env->getString('CPHP_PR_ID') !== '');
	}


	public function getBuildNumber(): string
	{
		return $this->env->getString('CPHP_BUILD_ID');
	}


	public function getBuildUrl(): string
	{
		return $this->env->getString('');
	}


	public function getGitCommit(): string
	{
		return $this->env->getString('CPHP_GIT_COMMIT');
	}


	public function getGitBranch(): string
	{
		return (string) preg_replace('~^refs/heads/~', '', $this->env->getString('CPHP_GIT_REF'));
	}


	public function getRepositoryName(): string
	{
		return ''; // unsupported
	}


	public function getRepositoryUrl(): string
	{
		return $this->env->getString('');
	}
}
