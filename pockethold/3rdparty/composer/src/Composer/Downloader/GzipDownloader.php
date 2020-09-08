<?php











namespace Composer\Downloader;

use Composer\Config;
use Composer\Cache;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Package\PackageInterface;
use Composer\Util\Platform;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;






class GzipDownloader extends ArchiveDownloader
{
protected function extract(PackageInterface $package, $file, $path)
{
$filename = pathinfo(parse_url($package->getDistUrl(), PHP_URL_PATH), PATHINFO_FILENAME);
$targetFilepath = $path . DIRECTORY_SEPARATOR . $filename;


 if (!Platform::isWindows()) {
$command = 'gzip -cd ' . ProcessExecutor::escape($file) . ' > ' . ProcessExecutor::escape($targetFilepath);

if (0 === $this->process->execute($command, $ignoredOutput)) {
return;
}

if (extension_loaded('zlib')) {

 $this->extractUsingExt($file, $targetFilepath);

return;
}

$processError = 'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();
throw new \RuntimeException($processError);
}


 $this->extractUsingExt($file, $targetFilepath);
}

private function extractUsingExt($file, $targetFilepath)
{
$archiveFile = gzopen($file, 'rb');
$targetFile = fopen($targetFilepath, 'wb');
while ($string = gzread($archiveFile, 4096)) {
fwrite($targetFile, $string, Platform::strlen($string));
}
gzclose($archiveFile);
fclose($targetFile);
}
}
