<?php











namespace Composer\DependencyResolver;

use Composer\Package\PackageInterface;




interface PolicyInterface
{
public function versionCompare(PackageInterface $a, PackageInterface $b, $operator);
public function selectPreferredPackages(Pool $pool, array $literals, $requiredPackage = null);
}
