<?php











namespace Composer\Downloader;

use Composer\Package\PackageInterface;
use React\Promise\PromiseInterface;







interface DownloaderInterface
{





public function getInstallationSource();






public function download(PackageInterface $package, $path, PackageInterface $prevPackage = null);















public function prepare($type, PackageInterface $package, $path, PackageInterface $prevPackage = null);







public function install(PackageInterface $package, $path);








public function update(PackageInterface $initial, PackageInterface $target, $path);







public function remove(PackageInterface $package, $path);














public function cleanup($type, PackageInterface $package, $path, PackageInterface $prevPackage = null);
}
