<?php











namespace Composer\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;







class PluginInstaller extends LibraryInstaller
{
private $installationManager;







public function __construct(IOInterface $io, Composer $composer, Filesystem $fs = null, BinaryInstaller $binaryInstaller = null)
{
parent::__construct($io, $composer, 'composer-plugin', $fs, $binaryInstaller);
$this->installationManager = $composer->getInstallationManager();
}




public function supports($packageType)
{
return $packageType === 'composer-plugin' || $packageType === 'composer-installer';
}




public function download(PackageInterface $package, PackageInterface $prevPackage = null)
{
$extra = $package->getExtra();
if (empty($extra['class'])) {
throw new \UnexpectedValueException('Error while installing '.$package->getPrettyName().', composer-plugin packages should have a class defined in their extra key to be usable.');
}

return parent::download($package, $prevPackage);
}




public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
{
$promise = parent::install($repo, $package);
if (!$promise instanceof PromiseInterface) {
$promise = \React\Promise\resolve();
}

$pluginManager = $this->composer->getPluginManager();
$self = $this;
return $promise->then(function () use ($self, $pluginManager, $package, $repo) {
try {
$pluginManager->registerPackage($package, true);
} catch (\Exception $e) {
$self->rollbackInstall($e, $repo, $package);
}
});
}




public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
{
$promise = parent::update($repo, $initial, $target);
if (!$promise instanceof PromiseInterface) {
$promise = \React\Promise\resolve();
}

$pluginManager = $this->composer->getPluginManager();
$self = $this;
return $promise->then(function () use ($self, $pluginManager, $initial, $target, $repo) {
try {
$pluginManager->deactivatePackage($initial, true);
$pluginManager->registerPackage($target, true);
} catch (\Exception $e) {
$self->rollbackInstall($e, $repo, $target);
}
});
}

public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
{
$this->composer->getPluginManager()->uninstallPackage($package, true);

return parent::uninstall($repo, $package);
}





public function rollbackInstall(\Exception $e, InstalledRepositoryInterface $repo, PackageInterface $package)
{
$this->io->writeError('Plugin initialization failed ('.$e->getMessage().'), uninstalling plugin');
parent::uninstall($repo, $package);
throw $e;
}
}
