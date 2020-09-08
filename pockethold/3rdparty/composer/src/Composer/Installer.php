<?php











namespace Composer;

use Composer\Autoload\AutoloadGenerator;
use Composer\DependencyResolver\DefaultPolicy;
use Composer\DependencyResolver\LocalRepoTransaction;
use Composer\DependencyResolver\LockTransaction;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\MarkAliasUninstalledOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\PolicyInterface;
use Composer\DependencyResolver\Pool;
use Composer\DependencyResolver\Request;
use Composer\DependencyResolver\Rule;
use Composer\DependencyResolver\Solver;
use Composer\DependencyResolver\SolverProblemsException;
use Composer\Downloader\DownloadManager;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\Installer\InstallerEvents;
use Composer\Installer\NoopInstaller;
use Composer\Installer\SuggestedPackagesReporter;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\RootAliasPackage;
use Composer\Package\BasePackage;
use Composer\Package\CompletePackage;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\Version\VersionParser;
use Composer\Package\Package;
use Composer\Repository\ArrayRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\Constraint\Constraint;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\CompositeRepository;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepository;
use Composer\Repository\RootPackageRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\ScriptEvents;







class Installer
{



protected $io;




protected $config;




protected $package;


 


protected $fixedRootPackage;




protected $downloadManager;




protected $repositoryManager;




protected $locker;




protected $installationManager;




protected $eventDispatcher;




protected $autoloadGenerator;

protected $preferSource = false;
protected $preferDist = false;
protected $optimizeAutoloader = false;
protected $classMapAuthoritative = false;
protected $apcuAutoloader = false;
protected $devMode = false;
protected $dryRun = false;
protected $verbose = false;
protected $update = false;
protected $install = true;
protected $dumpAutoloader = true;
protected $runScripts = true;
protected $ignorePlatformReqs = false;
protected $preferStable = false;
protected $preferLowest = false;
protected $writeLock;
protected $executeOperations = true;






protected $updateMirrors = false;
protected $updateAllowList = null;
protected $updateAllowTransitiveDependencies = Request::UPDATE_ONLY_LISTED;




protected $suggestedPackagesReporter;




protected $additionalFixedRepository;














public function __construct(IOInterface $io, Config $config, RootPackageInterface $package, DownloadManager $downloadManager, RepositoryManager $repositoryManager, Locker $locker, InstallationManager $installationManager, EventDispatcher $eventDispatcher, AutoloadGenerator $autoloadGenerator)
{
$this->io = $io;
$this->config = $config;
$this->package = $package;
$this->downloadManager = $downloadManager;
$this->repositoryManager = $repositoryManager;
$this->locker = $locker;
$this->installationManager = $installationManager;
$this->eventDispatcher = $eventDispatcher;
$this->autoloadGenerator = $autoloadGenerator;

$this->writeLock = $config->get('lock');
}







public function run()
{

 
 
 
 gc_collect_cycles();
gc_disable();

if ($this->updateAllowList && $this->updateMirrors) {
throw new \RuntimeException("The installer options updateMirrors and updateAllowList are mutually exclusive.");
}

$isFreshInstall = $this->repositoryManager->getLocalRepository()->isFresh();


 if (!$this->update && !$this->locker->isLocked()) {
$this->io->writeError('<warning>No lock file found. Updating dependencies instead of installing from lock file. Use composer update over composer install if you do not have a lock file.</warning>');
$this->update = true;
}

if ($this->dryRun) {
$this->verbose = true;
$this->runScripts = false;
$this->executeOperations = false;
$this->writeLock = false;
$this->dumpAutoloader = false;
$this->mockLocalRepositories($this->repositoryManager);
}

if ($this->update && !$this->install) {
$this->dumpAutoloader = false;
}

if ($this->runScripts) {
$_SERVER['COMPOSER_DEV_MODE'] = $this->devMode ? '1' : '0';
putenv('COMPOSER_DEV_MODE='.$_SERVER['COMPOSER_DEV_MODE']);


 
 $eventName = $this->update ? ScriptEvents::PRE_UPDATE_CMD : ScriptEvents::PRE_INSTALL_CMD;
$this->eventDispatcher->dispatchScript($eventName, $this->devMode);
}

$this->downloadManager->setPreferSource($this->preferSource);
$this->downloadManager->setPreferDist($this->preferDist);

$localRepo = $this->repositoryManager->getLocalRepository();

if (!$this->suggestedPackagesReporter) {
$this->suggestedPackagesReporter = new SuggestedPackagesReporter($this->io);
}

try {
if ($this->update) {
$res = $this->doUpdate($localRepo, $this->install);
} else {
$res = $this->doInstall($localRepo);
}
if ($res !== 0) {
return $res;
}
} catch (\Exception $e) {
if ($this->executeOperations && $this->install && $this->config->get('notify-on-install')) {
$this->installationManager->notifyInstalls($this->io);
}

throw $e;
}
if ($this->executeOperations && $this->install && $this->config->get('notify-on-install')) {
$this->installationManager->notifyInstalls($this->io);
}

if ($this->update) {
$installedRepo = new InstalledRepository(array(
$this->locker->getLockedRepository($this->devMode),
$this->createPlatformRepo(false),
new RootPackageRepository(clone $this->package),
));
if ($isFreshInstall) {
$this->suggestedPackagesReporter->addSuggestionsFromPackage($this->package);
}
$this->suggestedPackagesReporter->outputMinimalistic($installedRepo);
}


 $lockedRepository = $this->locker->getLockedRepository(true);
foreach ($lockedRepository->getPackages() as $package) {
if (!$package instanceof CompletePackage || !$package->isAbandoned()) {
continue;
}

$replacement = is_string($package->getReplacementPackage())
? 'Use ' . $package->getReplacementPackage() . ' instead'
: 'No replacement was suggested';

$this->io->writeError(
sprintf(
"<warning>Package %s is abandoned, you should avoid using it. %s.</warning>",
$package->getPrettyName(),
$replacement
)
);
}

if ($this->dumpAutoloader) {

 if ($this->optimizeAutoloader) {
$this->io->writeError('<info>Generating optimized autoload files</info>');
} else {
$this->io->writeError('<info>Generating autoload files</info>');
}

$this->autoloadGenerator->setDevMode($this->devMode);
$this->autoloadGenerator->setClassMapAuthoritative($this->classMapAuthoritative);
$this->autoloadGenerator->setApcu($this->apcuAutoloader);
$this->autoloadGenerator->setRunScripts($this->runScripts);
$this->autoloadGenerator->setIgnorePlatformRequirements($this->ignorePlatformReqs);
$this->autoloadGenerator->dump($this->config, $localRepo, $this->package, $this->installationManager, 'composer', $this->optimizeAutoloader);
}

if ($this->install && $this->executeOperations) {

 foreach ($localRepo->getPackages() as $package) {
$this->installationManager->ensureBinariesPresence($package);
}
}

$fundingCount = 0;
foreach ($localRepo->getPackages() as $package) {
if ($package instanceof CompletePackageInterface && !$package instanceof AliasPackage && $package->getFunding()) {
$fundingCount++;
}
}
if ($fundingCount) {
$this->io->writeError(array(
sprintf(
"<info>%d package%s you are using %s looking for funding.</info>",
$fundingCount,
1 === $fundingCount ? '' : 's',
1 === $fundingCount ? 'is' : 'are'
),
'<info>Use the `composer fund` command to find out more!</info>',
));
}

if ($this->runScripts) {

 $eventName = $this->update ? ScriptEvents::POST_UPDATE_CMD : ScriptEvents::POST_INSTALL_CMD;
$this->eventDispatcher->dispatchScript($eventName, $this->devMode);
}


 if (!defined('HHVM_VERSION')) {
gc_enable();
}

return 0;
}

protected function doUpdate(RepositoryInterface $localRepo, $doInstall)
{
$platformRepo = $this->createPlatformRepo(true);
$aliases = $this->getRootAliases(true);

$lockedRepository = null;

if ($this->locker->isLocked()) {
$lockedRepository = $this->locker->getLockedRepository(true);
}

if ($this->updateAllowList) {
if (!$lockedRepository) {
$this->io->writeError('<error>Cannot update only a partial set of packages without a lock file present.</error>', true, IOInterface::QUIET);
return 1;
}
}

$this->io->writeError('<info>Loading composer repositories with package information</info>');


 $policy = $this->createPolicy(true);
$repositorySet = $this->createRepositorySet(true, $platformRepo, $aliases);
$repositories = $this->repositoryManager->getRepositories();
foreach ($repositories as $repository) {
$repositorySet->addRepository($repository);
}
if ($lockedRepository) {
$repositorySet->addRepository($lockedRepository);
}

$request = $this->createRequest($this->fixedRootPackage, $platformRepo, $lockedRepository);

$this->io->writeError('<info>Updating dependencies</info>');


 if ($this->updateMirrors) {
foreach ($lockedRepository->getPackages() as $lockedPackage) {
$request->requireName($lockedPackage->getName(), new Constraint('==', $lockedPackage->getVersion()));
}
} else {
$links = array_merge($this->package->getRequires(), $this->package->getDevRequires());
foreach ($links as $link) {
$request->requireName($link->getTarget(), $link->getConstraint());
}
}


 if ($this->updateAllowList) {
$request->setUpdateAllowList($this->updateAllowList, $this->updateAllowTransitiveDependencies);
}

$pool = $repositorySet->createPool($request, $this->io, $this->eventDispatcher);


 $solver = new Solver($policy, $pool, $this->io);
try {
$lockTransaction = $solver->solve($request, $this->ignorePlatformReqs);
$ruleSetSize = $solver->getRuleSetSize();
$solver = null;
} catch (SolverProblemsException $e) {
$this->io->writeError('<error>Your requirements could not be resolved to an installable set of packages.</error>', true, IOInterface::QUIET);
$this->io->writeError($e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose()));
if (!$this->devMode) {
$this->io->writeError('<warning>Running update with --no-dev does not mean require-dev is ignored, it just means the packages will not be installed. If dev requirements are blocking the update you have to resolve those problems.</warning>', true, IOInterface::QUIET);
}

return max(1, $e->getCode());
}

$this->io->writeError("Analyzed ".count($pool)." packages to resolve dependencies", true, IOInterface::VERBOSE);
$this->io->writeError("Analyzed ".$ruleSetSize." rules to resolve dependencies", true, IOInterface::VERBOSE);

if (!$lockTransaction->getOperations()) {
$this->io->writeError('Nothing to modify in lock file');
}

$exitCode = $this->extractDevPackages($lockTransaction, $platformRepo, $aliases, $policy);
if ($exitCode !== 0) {
return $exitCode;
}


 $platformReqs = $this->extractPlatformRequirements($this->package->getRequires());
$platformDevReqs = $this->extractPlatformRequirements($this->package->getDevRequires());

$installsUpdates = $uninstalls = array();
if ($lockTransaction->getOperations()) {
$installNames = $updateNames = $uninstallNames = array();
foreach ($lockTransaction->getOperations() as $operation) {
if ($operation instanceof InstallOperation) {
$installsUpdates[] = $operation;
$installNames[] = $operation->getPackage()->getPrettyName().':'.$operation->getPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UpdateOperation) {
$installsUpdates[] = $operation;
$updateNames[] = $operation->getTargetPackage()->getPrettyName().':'.$operation->getTargetPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UninstallOperation) {
$uninstalls[] = $operation;
$uninstallNames[] = $operation->getPackage()->getPrettyName();
}
}

$this->io->writeError(sprintf(
"<info>Lock file operations: %d install%s, %d update%s, %d removal%s</info>",
count($installNames),
1 === count($installNames) ? '' : 's',
count($updateNames),
1 === count($updateNames) ? '' : 's',
count($uninstalls),
1 === count($uninstalls) ? '' : 's'
));
if ($installNames) {
$this->io->writeError("Installs: ".implode(', ', $installNames), true, IOInterface::VERBOSE);
}
if ($updateNames) {
$this->io->writeError("Updates: ".implode(', ', $updateNames), true, IOInterface::VERBOSE);
}
if ($uninstalls) {
$this->io->writeError("Removals: ".implode(', ', $uninstallNames), true, IOInterface::VERBOSE);
}
}

$sortByName = function ($a, $b) {
if ($a instanceof UpdateOperation) {
$a = $a->getTargetPackage()->getName();
} else {
$a = $a->getPackage()->getName();
}
if ($b instanceof UpdateOperation) {
$b = $b->getTargetPackage()->getName();
} else {
$b = $b->getPackage()->getName();
}

return strcmp($a, $b);
};
usort($uninstalls, $sortByName);
usort($installsUpdates, $sortByName);

foreach (array_merge($uninstalls, $installsUpdates) as $operation) {

 if ($operation instanceof InstallOperation) {
$this->suggestedPackagesReporter->addSuggestionsFromPackage($operation->getPackage());
}


 if (false === strpos($operation->getOperationType(), 'Alias') || $this->io->isDebug()) {
$this->io->writeError('  - ' . $operation->show(true));
}
}

$updatedLock = $this->locker->setLockData(
$lockTransaction->getNewLockPackages(false, $this->updateMirrors),
$lockTransaction->getNewLockPackages(true, $this->updateMirrors),
$platformReqs,
$platformDevReqs,
$lockTransaction->getAliases($aliases),
$this->package->getMinimumStability(),
$this->package->getStabilityFlags(),
$this->preferStable || $this->package->getPreferStable(),
$this->preferLowest,
$this->config->get('platform') ?: array(),
$this->writeLock && $this->executeOperations
);
if ($updatedLock && $this->writeLock && $this->executeOperations) {
$this->io->writeError('<info>Writing lock file</info>');
}


 if ($this->executeOperations && count($lockTransaction->getOperations()) > 0) {
$vendorDir = $this->config->get('vendor-dir');
if (is_dir($vendorDir)) {

 
 @touch($vendorDir);
}
}

if ($doInstall) {

 return $this->doInstall($localRepo, true);
}

return 0;
}





protected function extractDevPackages(LockTransaction $lockTransaction, $platformRepo, $aliases, $policy)
{
if (!$this->package->getDevRequires()) {
return 0;
}

$resultRepo = new ArrayRepository(array());
$loader = new ArrayLoader(null, true);
$dumper = new ArrayDumper();
foreach ($lockTransaction->getNewLockPackages(false) as $pkg) {
$resultRepo->addPackage($loader->load($dumper->dump($pkg)));
}

$repositorySet = $this->createRepositorySet(true, $platformRepo, $aliases);
$repositorySet->addRepository($resultRepo);

$request = $this->createRequest($this->fixedRootPackage, $platformRepo, null);

$links = $this->package->getRequires();
foreach ($links as $link) {
$request->requireName($link->getTarget(), $link->getConstraint());
}

$pool = $repositorySet->createPoolWithAllPackages();

$solver = new Solver($policy, $pool, $this->io);
try {
$nonDevLockTransaction = $solver->solve($request, $this->ignorePlatformReqs);
$solver = null;
} catch (SolverProblemsException $e) {
$this->io->writeError('<error>Unable to find a compatible set of packages based on your non-dev requirements alone.</error>', true, IOInterface::QUIET);
$this->io->writeError('Your requirements can be resolved successfully when require-dev packages are present.');
$this->io->writeError('You may need to move packages from require-dev or some of their dependencies to require.');
$this->io->writeError($e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose(), true));

return max(1, $e->getCode());
}

$lockTransaction->setNonDevPackages($nonDevLockTransaction);

return 0;
}






protected function doInstall(RepositoryInterface $localRepo, $alreadySolved = false)
{
$this->io->writeError('<info>Installing dependencies from lock file'.($this->devMode ? ' (including require-dev)' : '').'</info>');

$lockedRepository = $this->locker->getLockedRepository($this->devMode);


 
 if (!$alreadySolved) {
$this->io->writeError('<info>Verifying lock file contents can be installed on current platform.</info>');

$platformRepo = $this->createPlatformRepo(false);

 $policy = $this->createPolicy(false);

 $repositorySet = $this->createRepositorySet(false, $platformRepo, array(), $lockedRepository);
$repositorySet->addRepository($lockedRepository);


 $request = $this->createRequest($this->fixedRootPackage, $platformRepo, $lockedRepository);

if (!$this->locker->isFresh()) {
$this->io->writeError('<warning>Warning: The lock file is not up to date with the latest changes in composer.json. You may be getting outdated dependencies. It is recommended that you run `composer update` or `composer update <package name>`.</warning>', true, IOInterface::QUIET);
}

foreach ($lockedRepository->getPackages() as $package) {
$request->fixPackage($package);
}

foreach ($this->locker->getPlatformRequirements($this->devMode) as $link) {
$request->requireName($link->getTarget(), $link->getConstraint());
}

$pool = $repositorySet->createPool($request, $this->io, $this->eventDispatcher);


 $solver = new Solver($policy, $pool, $this->io);
try {
$lockTransaction = $solver->solve($request, $this->ignorePlatformReqs);
$solver = null;


 if (0 !== count($lockTransaction->getOperations())) {
$this->io->writeError('<error>Your lock file cannot be installed on this system without changes. Please run composer update.</error>', true, IOInterface::QUIET);

 return 1;
}
} catch (SolverProblemsException $e) {
$this->io->writeError('<error>Your lock file does not contain a compatible set of packages. Please run composer update.</error>', true, IOInterface::QUIET);
$this->io->writeError($e->getPrettyString($repositorySet, $request, $pool, $this->io->isVerbose()));

return max(1, $e->getCode());
}
}


 $localRepoTransaction = new LocalRepoTransaction($lockedRepository, $localRepo);
$this->eventDispatcher->dispatchInstallerEvent(InstallerEvents::PRE_OPERATIONS_EXEC, $this->devMode, $this->executeOperations, $localRepoTransaction);

if (!$localRepoTransaction->getOperations()) {
$this->io->writeError('Nothing to install, update or remove');
}

if ($localRepoTransaction->getOperations()) {
$installs = $updates = $uninstalls = array();
foreach ($localRepoTransaction->getOperations() as $operation) {
if ($operation instanceof InstallOperation) {
$installs[] = $operation->getPackage()->getPrettyName().':'.$operation->getPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UpdateOperation) {
$updates[] = $operation->getTargetPackage()->getPrettyName().':'.$operation->getTargetPackage()->getFullPrettyVersion();
} elseif ($operation instanceof UninstallOperation) {
$uninstalls[] = $operation->getPackage()->getPrettyName();
}
}

$this->io->writeError(sprintf(
"<info>Package operations: %d install%s, %d update%s, %d removal%s</info>",
count($installs),
1 === count($installs) ? '' : 's',
count($updates),
1 === count($updates) ? '' : 's',
count($uninstalls),
1 === count($uninstalls) ? '' : 's'
));
if ($installs) {
$this->io->writeError("Installs: ".implode(', ', $installs), true, IOInterface::VERBOSE);
}
if ($updates) {
$this->io->writeError("Updates: ".implode(', ', $updates), true, IOInterface::VERBOSE);
}
if ($uninstalls) {
$this->io->writeError("Removals: ".implode(', ', $uninstalls), true, IOInterface::VERBOSE);
}
}

if ($this->executeOperations) {
$this->installationManager->execute($localRepo, $localRepoTransaction->getOperations(), $this->devMode, $this->runScripts);
} else {
foreach ($localRepoTransaction->getOperations() as $operation) {

 if (false === strpos($operation->getOperationType(), 'Alias') || $this->io->isDebug()) {
$this->io->writeError('  - ' . $operation->show(false));
}
}
}

return 0;
}

private function createPlatformRepo($forUpdate)
{
if ($forUpdate) {
$platformOverrides = $this->config->get('platform') ?: array();
} else {
$platformOverrides = $this->locker->getPlatformOverrides();
}

return new PlatformRepository(array(), $platformOverrides);
}








private function createRepositorySet($forUpdate, PlatformRepository $platformRepo, array $rootAliases = array(), $lockedRepository = null)
{
if ($forUpdate) {
$minimumStability = $this->package->getMinimumStability();
$stabilityFlags = $this->package->getStabilityFlags();

$requires = array_merge($this->package->getRequires(), $this->package->getDevRequires());
} else {
$minimumStability = $this->locker->getMinimumStability();
$stabilityFlags = $this->locker->getStabilityFlags();

$requires = array();
foreach ($lockedRepository->getPackages() as $package) {
$constraint = new Constraint('=', $package->getVersion());
$constraint->setPrettyString($package->getPrettyVersion());
$requires[$package->getName()] = $constraint;
}
}

$rootRequires = array();
foreach ($requires as $req => $constraint) {

 if ((true === $this->ignorePlatformReqs || (is_array($this->ignorePlatformReqs) && in_array($req, $this->ignorePlatformReqs, true))) && preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $req)) {
continue;
}
if ($constraint instanceof Link) {
$rootRequires[$req] = $constraint->getConstraint();
} else {
$rootRequires[$req] = $constraint;
}
}

$this->fixedRootPackage = clone $this->package;
$this->fixedRootPackage->setRequires(array());
$this->fixedRootPackage->setDevRequires(array());

$stabilityFlags[$this->package->getName()] = BasePackage::$stabilities[VersionParser::parseStability($this->package->getVersion())];

$repositorySet = new RepositorySet($minimumStability, $stabilityFlags, $rootAliases, $this->package->getReferences(), $rootRequires);
$repositorySet->addRepository(new RootPackageRepository($this->fixedRootPackage));
$repositorySet->addRepository($platformRepo);
if ($this->additionalFixedRepository) {
$repositorySet->addRepository($this->additionalFixedRepository);
}

return $repositorySet;
}




private function createPolicy($forUpdate)
{
$preferStable = null;
$preferLowest = null;
if (!$forUpdate) {
$preferStable = $this->locker->getPreferStable();
$preferLowest = $this->locker->getPreferLowest();
}

 
 if (null === $preferStable) {
$preferStable = $this->preferStable || $this->package->getPreferStable();
}
if (null === $preferLowest) {
$preferLowest = $this->preferLowest;
}

return new DefaultPolicy($preferStable, $preferLowest);
}







private function createRequest(RootPackageInterface $rootPackage, PlatformRepository $platformRepo, $lockedRepository = null)
{
$request = new Request($lockedRepository);

$request->fixPackage($rootPackage, false);
if ($rootPackage instanceof RootAliasPackage) {
$request->fixPackage($rootPackage->getAliasOf(), false);
}

$fixedPackages = $platformRepo->getPackages();
if ($this->additionalFixedRepository) {
$fixedPackages = array_merge($fixedPackages, $this->additionalFixedRepository->getPackages());
}


 
 
 $provided = $rootPackage->getProvides();
foreach ($fixedPackages as $package) {

 if ($package->getRepository() !== $platformRepo
|| !isset($provided[$package->getName()])
|| !$provided[$package->getName()]->getConstraint()->matches(new Constraint('=', $package->getVersion()))
) {
$request->fixPackage($package, false);
}
}

return $request;
}





private function getRootAliases($forUpdate)
{
if ($forUpdate) {
$aliases = $this->package->getAliases();
} else {
$aliases = $this->locker->getAliases();
}

return $aliases;
}





private function extractPlatformRequirements($links)
{
$platformReqs = array();
foreach ($links as $link) {
if (preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $link->getTarget())) {
$platformReqs[$link->getTarget()] = $link->getPrettyConstraint();
}
}

return $platformReqs;
}








private function mockLocalRepositories(RepositoryManager $rm)
{
$packages = array();
foreach ($rm->getLocalRepository()->getPackages() as $package) {
$packages[(string) $package] = clone $package;
}
foreach ($packages as $key => $package) {
if ($package instanceof AliasPackage) {
$alias = (string) $package->getAliasOf();
$packages[$key] = new AliasPackage($packages[$alias], $package->getVersion(), $package->getPrettyVersion());
}
}
$rm->setLocalRepository(
new InstalledArrayRepository($packages)
);
}








public static function create(IOInterface $io, Composer $composer)
{
return new static(
$io,
$composer->getConfig(),
$composer->getPackage(),
$composer->getDownloadManager(),
$composer->getRepositoryManager(),
$composer->getLocker(),
$composer->getInstallationManager(),
$composer->getEventDispatcher(),
$composer->getAutoloadGenerator()
);
}





public function setAdditionalFixedRepository(RepositoryInterface $additionalFixedRepository)
{
$this->additionalFixedRepository = $additionalFixedRepository;

return $this;
}







public function setDryRun($dryRun = true)
{
$this->dryRun = (bool) $dryRun;

return $this;
}






public function isDryRun()
{
return $this->dryRun;
}







public function setPreferSource($preferSource = true)
{
$this->preferSource = (bool) $preferSource;

return $this;
}







public function setPreferDist($preferDist = true)
{
$this->preferDist = (bool) $preferDist;

return $this;
}







public function setOptimizeAutoloader($optimizeAutoloader = false)
{
$this->optimizeAutoloader = (bool) $optimizeAutoloader;
if (!$this->optimizeAutoloader) {

 
 $this->setClassMapAuthoritative(false);
}

return $this;
}








public function setClassMapAuthoritative($classMapAuthoritative = false)
{
$this->classMapAuthoritative = (bool) $classMapAuthoritative;
if ($this->classMapAuthoritative) {

 $this->setOptimizeAutoloader(true);
}

return $this;
}







public function setApcuAutoloader($apcuAutoloader = false)
{
$this->apcuAutoloader = (bool) $apcuAutoloader;

return $this;
}







public function setUpdate($update = true)
{
$this->update = (bool) $update;

return $this;
}







public function setInstall($install = true)
{
$this->install = (bool) $install;

return $this;
}







public function setDevMode($devMode = true)
{
$this->devMode = (bool) $devMode;

return $this;
}









public function setDumpAutoloader($dumpAutoloader = true)
{
$this->dumpAutoloader = (bool) $dumpAutoloader;

return $this;
}









public function setRunScripts($runScripts = true)
{
$this->runScripts = (bool) $runScripts;

return $this;
}







public function setConfig(Config $config)
{
$this->config = $config;

return $this;
}







public function setVerbose($verbose = true)
{
$this->verbose = (bool) $verbose;

return $this;
}






public function isVerbose()
{
return $this->verbose;
}











public function setIgnorePlatformRequirements($ignorePlatformReqs = false)
{
if (is_array($ignorePlatformReqs)) {
$this->ignorePlatformReqs = array_filter($ignorePlatformReqs, function ($req) {
return (bool) preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $req);
});
} else {
$this->ignorePlatformReqs = (bool) $ignorePlatformReqs;
}

return $this;
}







public function setUpdateMirrors($updateMirrors)
{
$this->updateMirrors = $updateMirrors;

return $this;
}








public function setUpdateAllowList(array $packages)
{
$this->updateAllowList = array_flip(array_map('strtolower', $packages));

return $this;
}










public function setUpdateAllowTransitiveDependencies($updateAllowTransitiveDependencies)
{
if (!in_array($updateAllowTransitiveDependencies, array(Request::UPDATE_ONLY_LISTED, Request::UPDATE_LISTED_WITH_TRANSITIVE_DEPS_NO_ROOT_REQUIRE, Request::UPDATE_LISTED_WITH_TRANSITIVE_DEPS), true)) {
throw new \RuntimeException("Invalid value for updateAllowTransitiveDependencies supplied");
}

$this->updateAllowTransitiveDependencies = $updateAllowTransitiveDependencies;

return $this;
}







public function setPreferStable($preferStable = true)
{
$this->preferStable = (bool) $preferStable;

return $this;
}







public function setPreferLowest($preferLowest = true)
{
$this->preferLowest = (bool) $preferLowest;

return $this;
}









public function setWriteLock($writeLock = true)
{
$this->writeLock = (bool) $writeLock;

return $this;
}









public function setExecuteOperations($executeOperations = true)
{
$this->executeOperations = (bool) $executeOperations;

return $this;
}










public function disablePlugins()
{
$this->installationManager->disablePlugins();

return $this;
}





public function setSuggestedPackagesReporter(SuggestedPackagesReporter $suggestedPackagesReporter)
{
$this->suggestedPackagesReporter = $suggestedPackagesReporter;

return $this;
}
}
