<?php



$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
'Symfony\\Polyfill\\Mbstring\\' => array($vendorDir . '/symfony/polyfill-mbstring'),
'Symfony\\Polyfill\\Ctype\\' => array($vendorDir . '/symfony/polyfill-ctype'),
'Symfony\\Component\\Process\\' => array($vendorDir . '/symfony/process'),
'Symfony\\Component\\Finder\\' => array($vendorDir . '/symfony/finder'),
'Symfony\\Component\\Filesystem\\' => array($vendorDir . '/symfony/filesystem'),
'Symfony\\Component\\Debug\\' => array($vendorDir . '/symfony/debug'),
'Symfony\\Component\\Console\\' => array($vendorDir . '/symfony/console'),
'Seld\\PharUtils\\' => array($vendorDir . '/seld/phar-utils/src'),
'Seld\\JsonLint\\' => array($vendorDir . '/seld/jsonlint/src/Seld/JsonLint'),
'Psr\\Log\\' => array($vendorDir . '/psr/log/Psr/Log'),
'JsonSchema\\' => array($vendorDir . '/justinrainbow/json-schema/src/JsonSchema'),
'Composer\\XdebugHandler\\' => array($vendorDir . '/composer/xdebug-handler/src'),
'Composer\\Spdx\\' => array($vendorDir . '/composer/spdx-licenses/src'),
'Composer\\Semver\\' => array($vendorDir . '/composer/semver/src'),
'Composer\\CaBundle\\' => array($vendorDir . '/composer/ca-bundle/src'),
'Composer\\' => array($baseDir . '/src/Composer'),
);
