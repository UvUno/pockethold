<?php

namespace Composer;

use Composer\Semver\VersionParser;






class InstalledVersions
{
private static $installed = array (
'root' => 
array (
'pretty_version' => '2.0.0-alpha3',
'version' => '2.0.0.0-alpha3',
'aliases' => 
array (
),
'reference' => '686d84ae1cb5b808bcce0ee41ab5c4e33c1c2c24',
'name' => 'composer/composer',
),
'versions' => 
array (
'composer/ca-bundle' => 
array (
'pretty_version' => '1.2.7',
'version' => '1.2.7.0',
'aliases' => 
array (
),
'reference' => '95c63ab2117a72f48f5a55da9740a3273d45b7fd',
),
'composer/composer' => 
array (
'pretty_version' => '2.0.0-alpha3',
'version' => '2.0.0.0-alpha3',
'aliases' => 
array (
),
'reference' => '686d84ae1cb5b808bcce0ee41ab5c4e33c1c2c24',
),
'composer/semver' => 
array (
'pretty_version' => '3.0.0',
'version' => '3.0.0.0',
'aliases' => 
array (
),
'reference' => '3426bd5efa8a12d230824536c42a8a4ad30b7940',
),
'composer/spdx-licenses' => 
array (
'pretty_version' => '1.5.4',
'version' => '1.5.4.0',
'aliases' => 
array (
),
'reference' => '6946f785871e2314c60b4524851f3702ea4f2223',
),
'composer/xdebug-handler' => 
array (
'pretty_version' => '1.4.2',
'version' => '1.4.2.0',
'aliases' => 
array (
),
'reference' => 'fa2aaf99e2087f013a14f7432c1cd2dd7d8f1f51',
),
'justinrainbow/json-schema' => 
array (
'pretty_version' => '5.2.10',
'version' => '5.2.10.0',
'aliases' => 
array (
),
'reference' => '2ba9c8c862ecd5510ed16c6340aa9f6eadb4f31b',
),
'psr/log' => 
array (
'pretty_version' => '1.1.3',
'version' => '1.1.3.0',
'aliases' => 
array (
),
'reference' => '0f73288fd15629204f9d42b7055f72dacbe811fc',
),
'react/promise' => 
array (
'pretty_version' => 'v1.2.1',
'version' => '1.2.1.0',
'aliases' => 
array (
),
'reference' => 'eefff597e67ff66b719f8171480add3c91474a1e',
),
'seld/jsonlint' => 
array (
'pretty_version' => '1.8.0',
'version' => '1.8.0.0',
'aliases' => 
array (
),
'reference' => 'ff2aa5420bfbc296cf6a0bc785fa5b35736de7c1',
),
'seld/phar-utils' => 
array (
'pretty_version' => '1.1.1',
'version' => '1.1.1.0',
'aliases' => 
array (
),
'reference' => '8674b1d84ffb47cc59a101f5d5a3b61e87d23796',
),
'symfony/console' => 
array (
'pretty_version' => 'v2.8.52',
'version' => '2.8.52.0',
'aliases' => 
array (
),
'reference' => 'cbcf4b5e233af15cd2bbd50dee1ccc9b7927dc12',
),
'symfony/debug' => 
array (
'pretty_version' => 'v2.8.52',
'version' => '2.8.52.0',
'aliases' => 
array (
),
'reference' => '74251c8d50dd3be7c4ce0c7b862497cdc641a5d0',
),
'symfony/filesystem' => 
array (
'pretty_version' => 'v2.8.52',
'version' => '2.8.52.0',
'aliases' => 
array (
),
'reference' => '7ae46872dad09dffb7fe1e93a0937097339d0080',
),
'symfony/finder' => 
array (
'pretty_version' => 'v2.8.52',
'version' => '2.8.52.0',
'aliases' => 
array (
),
'reference' => '1444eac52273e345d9b95129bf914639305a9ba4',
),
'symfony/polyfill-ctype' => 
array (
'pretty_version' => 'v1.18.0',
'version' => '1.18.0.0',
'aliases' => 
array (
),
'reference' => '1c302646f6efc070cd46856e600e5e0684d6b454',
),
'symfony/polyfill-mbstring' => 
array (
'pretty_version' => 'v1.18.0',
'version' => '1.18.0.0',
'aliases' => 
array (
),
'reference' => 'a6977d63bf9a0ad4c65cd352709e230876f9904a',
),
'symfony/process' => 
array (
'pretty_version' => 'v2.8.52',
'version' => '2.8.52.0',
'aliases' => 
array (
),
'reference' => 'c3591a09c78639822b0b290d44edb69bf9f05dc8',
),
),
);







public static function getInstalledPackages()
{
return array_keys(self::$installed['versions']);
}









public static function isInstalled($packageName)
{
return isset(self::$installed['versions'][$packageName]);
}














public static function satisfies(VersionParser $parser, $packageName, $constraint)
{
$constraint = $parser->parseConstraints($constraint);
$provided = $parser->parseConstraints(self::getVersionRanges($packageName));

return $provided->matches($constraint);
}










public static function getVersionRanges($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

$ranges = array();
if (isset(self::$installed['versions'][$packageName]['pretty_version'])) {
$ranges[] = self::$installed['versions'][$packageName]['pretty_version'];
}
if (array_key_exists('aliases', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['aliases']);
}
if (array_key_exists('replaced', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['replaced']);
}
if (array_key_exists('provided', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['provided']);
}

return implode(' || ', $ranges);
}





public static function getVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['version'])) {
return null;
}

return self::$installed['versions'][$packageName]['version'];
}





public static function getPrettyVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['pretty_version'])) {
return null;
}

return self::$installed['versions'][$packageName]['pretty_version'];
}





public static function getReference($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['reference'])) {
return null;
}

return self::$installed['versions'][$packageName]['reference'];
}





public static function getRootPackage()
{
return self::$installed['root'];
}







public static function getRawData()
{
return self::$installed;
}



















public static function reload($data)
{
self::$installed = $data;
}
}
