<?php











namespace Composer\Repository;

use Composer\IO\IOInterface;
use Composer\Semver\VersionParser as SemverVersionParser;
use Composer\Package\Version\VersionParser;
use Composer\Package\CompletePackage;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Package\Link;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\HttpDownloader;
use Composer\Config;
use Composer\Factory;












class PearRepository extends ArrayRepository
{
public function __construct()
{
throw new \RuntimeException('The PEAR repository has been removed from Composer 2.0');
}
}
