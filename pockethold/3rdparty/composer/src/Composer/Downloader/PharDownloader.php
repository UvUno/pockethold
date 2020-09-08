<?php











namespace Composer\Downloader;

use Composer\Package\PackageInterface;






class PharDownloader extends ArchiveDownloader
{



protected function extract(PackageInterface $package, $file, $path)
{

 $archive = new \Phar($file);
$archive->extractTo($path, null, true);





}
}
