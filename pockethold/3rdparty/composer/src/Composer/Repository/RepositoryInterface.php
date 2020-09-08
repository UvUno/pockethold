<?php











namespace Composer\Repository;

use Composer\Package\PackageInterface;
use Composer\Package\BasePackage;
use Composer\Semver\Constraint\ConstraintInterface;








interface RepositoryInterface extends \Countable
{
const SEARCH_FULLTEXT = 0;
const SEARCH_NAME = 1;








public function hasPackage(PackageInterface $package);









public function findPackage($name, $constraint);









public function findPackages($name, $constraint = null);






public function getPackages();
















public function loadPackages(array $packageNameMap, array $acceptableStabilities, array $stabilityFlags);











public function search($query, $mode = 0, $type = null);











public function getProviders($packageName);








public function getRepoName();
}
