<?php











namespace Composer\DependencyResolver;

use Composer\DependencyResolver\Operation\MarkAliasUninstalledOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Package\AliasPackage;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Semver\Constraint\Constraint;





class LocalRepoTransaction extends Transaction
{
public function __construct(RepositoryInterface $lockedRepository, $localRepository)
{
parent::__construct(
$localRepository->getPackages(),
$lockedRepository->getPackages()
);
}
}
