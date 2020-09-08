<?php











namespace Composer\Package\Version;

use Composer\DependencyResolver\Pool;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Composer;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Repository\RepositorySet;
use Composer\Repository\PlatformRepository;
use Composer\Semver\Constraint\Constraint;







class VersionSelector
{
private $repositorySet;

private $platformConstraints = array();

private $parser;




public function __construct(RepositorySet $repositorySet, PlatformRepository $platformRepo = null)
{
$this->repositorySet = $repositorySet;
if ($platformRepo) {
foreach ($platformRepo->getPackages() as $package) {
$this->platformConstraints[$package->getName()][] = new Constraint('==', $package->getVersion());
}
}
}











public function findBestCandidate($packageName, $targetPackageVersion = null, $preferredStability = 'stable', $ignorePlatformReqs = false)
{
if (!isset(BasePackage::$stabilities[$preferredStability])) {

 throw new \UnexpectedValueException('Expected a valid stability name as 3rd argument, got '.$preferredStability);
}

$constraint = $targetPackageVersion ? $this->getParser()->parseConstraints($targetPackageVersion) : null;
$candidates = $this->repositorySet->findPackages(strtolower($packageName), $constraint);

if ($this->platformConstraints && true !== $ignorePlatformReqs) {
$platformConstraints = $this->platformConstraints;
$ignorePlatformReqs = $ignorePlatformReqs ?: array();
$candidates = array_filter($candidates, function ($pkg) use ($platformConstraints, $ignorePlatformReqs) {
$reqs = $pkg->getRequires();

foreach ($reqs as $name => $link) {
if (!in_array($name, $ignorePlatformReqs, true) && isset($platformConstraints[$name])) {
foreach ($platformConstraints[$name] as $constraint) {
if ($link->getConstraint()->matches($constraint)) {
continue 2;
}
}

return false;
}
}

return true;
});
}

if (!$candidates) {
return false;
}


 $package = reset($candidates);
$minPriority = BasePackage::$stabilities[$preferredStability];
foreach ($candidates as $candidate) {
$candidatePriority = $candidate->getStabilityPriority();
$currentPriority = $package->getStabilityPriority();


 
 if ($minPriority < $candidatePriority && $currentPriority < $candidatePriority) {
continue;
}


 
 if ($minPriority < $candidatePriority && $candidatePriority < $currentPriority) {
$package = $candidate;
continue;
}


 
 if ($minPriority >= $candidatePriority && $minPriority < $currentPriority) {
$package = $candidate;
continue;
}


 if (version_compare($package->getVersion(), $candidate->getVersion(), '<')) {
$package = $candidate;
}
}

return $package;
}
















public function findRecommendedRequireVersion(PackageInterface $package)
{
$version = $package->getVersion();
if (!$package->isDev()) {
return $this->transformVersion($version, $package->getPrettyVersion(), $package->getStability());
}

$loader = new ArrayLoader($this->getParser());
$dumper = new ArrayDumper();
$extra = $loader->getBranchAlias($dumper->dump($package));
if ($extra) {
$extra = preg_replace('{^(\d+\.\d+\.\d+)(\.9999999)-dev$}', '$1.0', $extra, -1, $count);
if ($count) {
$extra = str_replace('.9999999', '.0', $extra);

return $this->transformVersion($extra, $extra, 'dev');
}
}

return $package->getPrettyVersion();
}

private function transformVersion($version, $prettyVersion, $stability)
{

 
 $semanticVersionParts = explode('.', $version);


 if (count($semanticVersionParts) == 4 && preg_match('{^0\D?}', $semanticVersionParts[3])) {

 if ($semanticVersionParts[0] === '0') {
unset($semanticVersionParts[3]);
} else {
unset($semanticVersionParts[2], $semanticVersionParts[3]);
}
$version = implode('.', $semanticVersionParts);
} else {
return $prettyVersion;
}


 if ($stability != 'stable') {
$version .= '@'.$stability;
}


 return '^' . $version;
}

private function getParser()
{
if ($this->parser === null) {
$this->parser = new VersionParser();
}

return $this->parser;
}
}
