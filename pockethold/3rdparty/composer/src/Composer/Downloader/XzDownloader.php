<?php











namespace Composer\Downloader;

use Composer\Config;
use Composer\Cache;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Package\PackageInterface;
use Composer\Util\ProcessExecutor;
use Composer\Util\HttpDownloader;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;







class XzDownloader extends ArchiveDownloader
{
protected function extract(PackageInterface $package, $file, $path)
{
$command = 'tar -xJf ' . ProcessExecutor::escape($file) . ' -C ' . ProcessExecutor::escape($path);

if (0 === $this->process->execute($command, $ignoredOutput)) {
return;
}

$processError = 'Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput();

throw new \RuntimeException($processError);
}
}
