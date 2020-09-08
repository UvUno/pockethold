<?php











namespace Composer\Plugin;

use Composer\Composer;
use Composer\IO\IOInterface;






interface PluginInterface
{










const PLUGIN_API_VERSION = '2.0.0';







public function activate(Composer $composer, IOInterface $io);











public function deactivate(Composer $composer, IOInterface $io);









public function uninstall(Composer $composer, IOInterface $io);
}
