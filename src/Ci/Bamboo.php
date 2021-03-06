<?php

declare(strict_types=1);

namespace Baraja\PackageManager;


class Bamboo extends AbstractCi
{
	public function isDetected(Environment $environment): bool
	{
		return $environment->get('bamboo_buildKey') !== false;
	}


	public function getCiName(): string
	{
		return CiDetector::CI_BAMBOO;
	}


	public function isPullRequest(): TrinaryLogic
	{
		return TrinaryLogic::createFromBoolean($this->env->get('bamboo_repository_pr_key') !== false);
	}


	public function getBuildNumber(): string
	{
		return $this->env->getString('bamboo_buildNumber');
	}


	public function getBuildUrl(): string
	{
		return $this->env->getString('bamboo_resultsUrl');
	}


	public function getGitCommit(): string
	{
		return $this->env->getString('bamboo_planRepository_revision');
	}


	public function getGitBranch(): string
	{
		return $this->env->getString('bamboo_planRepository_branch');
	}


	public function getRepositoryName(): string
	{
		return $this->env->getString('bamboo_planRepository_name');
	}


	public function getRepositoryUrl(): string
	{
		return $this->env->getString('bamboo_planRepository_repositoryUrl');
	}
}
