<?php










namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\RuntimeException;










class PhpProcess extends Process
{







public function __construct($script, $cwd = null, array $env = null, $timeout = 60, array $options = array())
{
$executableFinder = new PhpExecutableFinder();
if (false === $php = $executableFinder->find()) {
$php = null;
}
if ('phpdbg' === \PHP_SAPI) {
$file = tempnam(sys_get_temp_dir(), 'dbg');
file_put_contents($file, $script);
register_shutdown_function('unlink', $file);
$php .= ' '.ProcessUtils::escapeArgument($file);
$script = null;
}
if ('\\' !== \DIRECTORY_SEPARATOR && null !== $php) {

 
 
 $php = 'exec '.$php;
}

parent::__construct($php, $cwd, $env, $script, $timeout, $options);
}




public function setPhpBinary($php)
{
$this->setCommandLine($php);
}




public function start($callback = null)
{
if (null === $this->getCommandLine()) {
throw new RuntimeException('Unable to find the PHP executable.');
}

parent::start($callback);
}
}
