<?php











namespace Composer\Util;

use Composer\IO\IOInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;
use Symfony\Component\Process\Exception\RuntimeException;
use React\Promise\Promise;





class ProcessExecutor
{
const STATUS_QUEUED = 1;
const STATUS_STARTED = 2;
const STATUS_COMPLETED = 3;
const STATUS_FAILED = 4;
const STATUS_ABORTED = 5;

protected static $timeout = 300;

protected $captureOutput;
protected $errorOutput;
protected $io;

private $jobs = array();
private $runningJobs = 0;
private $maxJobs = 10;
private $idGen = 0;
private $allowAsync = false;

public function __construct(IOInterface $io = null)
{
$this->io = $io;
}










public function execute($command, &$output = null, $cwd = null)
{
if (func_num_args() > 1) {
return $this->doExecute($command, $cwd, false, $output);
}

return $this->doExecute($command, $cwd, false);
}








public function executeTty($command, $cwd = null)
{
return $this->doExecute($command, $cwd, true);
}

private function doExecute($command, $cwd, $tty, &$output = null)
{
if ($this->io && $this->io->isDebug()) {
$safeCommand = preg_replace_callback('{://(?P<user>[^:/\s]+):(?P<password>[^@\s/]+)@}i', function ($m) {
if (preg_match('{^[a-f0-9]{12,}$}', $m['user'])) {
return '://***:***@';
}

return '://'.$m['user'].':***@';
}, $command);
$safeCommand = preg_replace("{--password (.*[^\\\\]\') }", '--password \'***\' ', $safeCommand);
$this->io->writeError('Executing command ('.($cwd ?: 'CWD').'): '.$safeCommand);
}


 
 if (null === $cwd && Platform::isWindows() && false !== strpos($command, 'git') && getcwd()) {
$cwd = realpath(getcwd());
}

$this->captureOutput = func_num_args() > 3;
$this->errorOutput = null;


 if (method_exists('Symfony\Component\Process\Process', 'fromShellCommandline')) {
$process = Process::fromShellCommandline($command, $cwd, null, null, static::getTimeout());
} else {
$process = new Process($command, $cwd, null, null, static::getTimeout());
}
if (!Platform::isWindows() && $tty) {
try {
$process->setTty(true);
} catch (RuntimeException $e) {

 }
}

$callback = is_callable($output) ? $output : array($this, 'outputHandler');
$process->run($callback);

if ($this->captureOutput && !is_callable($output)) {
$output = $process->getOutput();
}

$this->errorOutput = $process->getErrorOutput();

return $process->getExitCode();
}










public function executeAsync($command, $cwd = null)
{
if (!$this->allowAsync) {
throw new \LogicException('You must use the ProcessExecutor instance which is part of a Composer\Loop instance to be able to run async processes');
}

$job = array(
'id' => $this->idGen++,
'status' => self::STATUS_QUEUED,
'command' => $command,
'cwd' => $cwd,
);

$resolver = function ($resolve, $reject) use (&$job) {
$job['status'] = ProcessExecutor::STATUS_QUEUED;
$job['resolve'] = $resolve;
$job['reject'] = $reject;
};

$self = $this;
$io = $this->io;

$canceler = function () use (&$job) {
if ($job['status'] === self::STATUS_QUEUED) {
$job['status'] = self::STATUS_ABORTED;
}
if ($job['status'] !== self::STATUS_STARTED) {
return;
}
$job['status'] = self::STATUS_ABORTED;
try {
if (defined('SIGINT')) {
$job['process']->signal(SIGINT);
}
} catch (\Exception $e) {

 }
$job['process']->stop(1);
};

$promise = new Promise($resolver, $canceler);
$promise = $promise->then(function () use (&$job, $self) {
if ($job['process']->isSuccessful()) {
$job['status'] = ProcessExecutor::STATUS_COMPLETED;
} else {
$job['status'] = ProcessExecutor::STATUS_FAILED;
}


 $self->markJobDone();

return $job['process'];
}, function ($e) use (&$job, $self) {
$job['status'] = ProcessExecutor::STATUS_FAILED;

$self->markJobDone();

throw $e;
});
$this->jobs[$job['id']] =& $job;

if ($this->runningJobs < $this->maxJobs) {
$this->startJob($job['id']);
}

return $promise;
}

private function startJob($id)
{
$job =& $this->jobs[$id];
if ($job['status'] !== self::STATUS_QUEUED) {
return;
}


 $job['status'] = self::STATUS_STARTED;
$this->runningJobs++;

$command = $job['command'];
$cwd = $job['cwd'];

if ($this->io && $this->io->isDebug()) {
$safeCommand = preg_replace_callback('{://(?P<user>[^:/\s]+):(?P<password>[^@\s/]+)@}i', function ($m) {
if (preg_match('{^[a-f0-9]{12,}$}', $m['user'])) {
return '://***:***@';
}

return '://'.$m['user'].':***@';
}, $command);
$safeCommand = preg_replace("{--password (.*[^\\\\]\') }", '--password \'***\' ', $safeCommand);
$this->io->writeError('Executing async command ('.($cwd ?: 'CWD').'): '.$safeCommand);
}


 
 if (null === $cwd && Platform::isWindows() && false !== strpos($command, 'git') && getcwd()) {
$cwd = realpath(getcwd());
}


 if (method_exists('Symfony\Component\Process\Process', 'fromShellCommandline')) {
$process = Process::fromShellCommandline($command, $cwd, null, null, static::getTimeout());
} else {
$process = new Process($command, $cwd, null, null, static::getTimeout());
}

$job['process'] = $process;

$process->start();
}

public function wait($index = null)
{
while (true) {
if (!$this->countActiveJobs($index)) {
return;
}

usleep(1000);
}
}




public function enableAsync()
{
$this->allowAsync = true;
}






public function countActiveJobs($index = null)
{

 foreach ($this->jobs as $job) {
if ($job['status'] === self::STATUS_STARTED) {
if (!$job['process']->isRunning()) {
call_user_func($job['resolve'], $job['process']);
}
}

if ($this->runningJobs < $this->maxJobs) {
if ($job['status'] === self::STATUS_QUEUED) {
$this->startJob($job['id']);
}
}
}

if (null !== $index) {
return $this->jobs[$index]['status'] < self::STATUS_COMPLETED ? 1 : 0;
}

$active = 0;
foreach ($this->jobs as $job) {
if ($job['status'] < self::STATUS_COMPLETED) {
$active++;
} else {
unset($this->jobs[$job['id']]);
}
}

return $active;
}




public function markJobDone()
{
$this->runningJobs--;
}

public function splitLines($output)
{
$output = trim($output);

return ((string) $output === '') ? array() : preg_split('{\r?\n}', $output);
}






public function getErrorOutput()
{
return $this->errorOutput;
}

public function outputHandler($type, $buffer)
{
if ($this->captureOutput) {
return;
}

if (null === $this->io) {
echo $buffer;

return;
}

if (Process::ERR === $type) {
$this->io->writeErrorRaw($buffer, false);
} else {
$this->io->writeRaw($buffer, false);
}
}

public static function getTimeout()
{
return static::$timeout;
}

public static function setTimeout($timeout)
{
static::$timeout = $timeout;
}








public static function escape($argument)
{
return self::escapeArgument($argument);
}








private static function escapeArgument($argument)
{

 
 
 
 if ('\\' === DIRECTORY_SEPARATOR) {
if ((string) $argument === '') {
return escapeshellarg($argument);
}

$escapedArgument = '';
$quote = false;
foreach (preg_split('/(")/', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
if ('"' === $part) {
$escapedArgument .= '\\"';
} elseif (self::isSurroundedBy($part, '%')) {

 $escapedArgument .= '^%"'.substr($part, 1, -1).'"^%';
} else {

 if ('\\' === substr($part, -1)) {
$part .= '\\';
}
$quote = true;
$escapedArgument .= $part;
}
}
if ($quote) {
$escapedArgument = '"'.$escapedArgument.'"';
}

return $escapedArgument;
}

return "'".str_replace("'", "'\\''", $argument)."'";
}

private static function isSurroundedBy($arg, $char)
{
return 2 < strlen($arg) && $char === $arg[0] && $char === $arg[strlen($arg) - 1];
}
}
