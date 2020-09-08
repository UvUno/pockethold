<?php











namespace Composer\Downloader;

use Composer\Package\PackageInterface;






class TarDownloader extends ArchiveDownloader
{



protected function extract(PackageInterface $package, $file, $path)
{

 $archive = new \PharData($file);
$archive->extractTo($path, null, true);
}
}
