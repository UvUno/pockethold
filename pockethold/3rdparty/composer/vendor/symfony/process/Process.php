<?php










namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Pipes\PipesInterface;
use Symfony\Component\Process\Pipes\UnixPipes;
use Symfony\Component\Process\Pipes\WindowsPipes;








class Process
{
const ERR = 'err';
const OUT = 'out';

const STATUS_READY = 'ready';
const STATUS_STARTED = 'started';
const STATUS_TERMINATED = 'terminated';

const STDIN = 0;
const STDOUT = 1;
const STDERR = 2;


 const TIMEOUT_PRECISION = 0.2;

private $callback;
private $commandline;
private $cwd;
private $env;
private $input;
private $starttime;
private $lastOutputTime;
private $timeout;
private $idleTimeout;
private $options;
private $exitcode;
private $fallbackStatus = array();
private $processInformation;
private $outputDisabled = false;
private $stdout;
private $stderr;
private $enhanceWindowsCompatibility = true;
private $enhanceSigchildCompatibility;
private $process;
private $status = self::STATUS_READY;
private $incrementalOutputOffset = 0;
private $incrementalErrorOutputOffset = 0;
private $tty;
private $pty;

private $useFileHandles = false;

private $processPipes;

private $latestSignal;

private static $sigchild;






public static $exitCodes = array(
0 => 'OK',
1 => 'General error',
2 => 'Misuse of shell builtins',

126 => 'Invoked command cannot execute',
127 => 'Command not found',
128 => 'Invalid exit argument',


 129 => 'Hangup',
130 => 'Interrupt',
131 => 'Quit and dump core',
132 => 'Illegal instruction',
133 => 'Trace/breakpoint trap',
134 => 'Process aborted',
135 => 'Bus error: "access to undefined portion of memory object"',
136 => 'Floating point exception: "erroneous arithmetic operation"',
137 => 'Kill (terminate immediately)',
138 => 'User-defined 1',
139 => 'Segmentation violation',
140 => 'User-defined 2',
141 => 'Write to pipe with no one reading',
142 => 'Signal raised by alarm',
143 => 'Termination (request to terminate)',

 145 => 'Child process terminated, stopped (or continued*)',
146 => 'Continue if stopped',
147 => 'Stop executing temporarily',
148 => 'Terminal stop signal',
149 => 'Background process attempting to read from tty ("in")',
150 => 'Background process attempting to write to tty ("out")',
151 => 'Urgent data available on socket',
152 => 'CPU time limit exceeded',
153 => 'File size limit exceeded',
154 => 'Signal raised by timer counting virtual time: "virtual timer expired"',
155 => 'Profiling timer expired',

 157 => 'Pollable event',

 159 => 'Bad syscall',
);











public function __construct($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = array())
{
if (!\function_exists('proc_open')) {
throw new RuntimeException('The Process class relies on proc_open, which is not available on your PHP installation.');
}

$this->commandline = $commandline;
$this->cwd = $cwd;


 
 
 
 if (null === $this->cwd && (\defined('ZEND_THREAD_SAFE') || '\\' === \DIRECTORY_SEPARATOR)) {
$this->cwd = getcwd();
}
if (null !== $env) {
$this->setEnv($env);
}

$this->setInput($input);
$this->setTimeout($timeout);
$this->useFileHandles = '\\' === \DIRECTORY_SEPARATOR;
$this->pty = false;
$this->enhanceSigchildCompatibility = '\\' !== \DIRECTORY_SEPARATOR && $this->isSigchildEnabled();
$this->options = array_replace(array('suppress_errors' => true, 'binary_pipes' => true), $options);
}

public function __destruct()
{
$this->stop(0);
}

public function __clone()
{
$this->resetProcessData();
}




















public function run($callback = null)
{
$this->start($callback);

return $this->wait();
}














public function mustRun($callback = null)
{
if (!$this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
throw new RuntimeException('This PHP has been compiled with --enable-sigchild. You must use setEnhanceSigchildCompatibility() to use this method.');
}

if (0 !== $this->run($callback)) {
throw new ProcessFailedException($this);
}

return $this;
}




















public function start($callback = null)
{
if ($this->isRunning()) {
throw new RuntimeException('Process is already running');
}
if ($this->outputDisabled && null !== $callback) {
throw new LogicException('Output has been disabled, enable it to allow the use of a callback.');
}

$this->resetProcessData();
$this->starttime = $this->lastOutputTime = microtime(true);
$this->callback = $this->buildCallback($callback);
$descriptors = $this->getDescriptors();

$commandline = $this->commandline;

if ('\\' === \DIRECTORY_SEPARATOR && $this->enhanceWindowsCompatibility) {
$commandline = 'cmd /V:ON /E:ON /D /C "('.$commandline.')';
foreach ($this->processPipes->getFiles() as $offset => $filename) {
$commandline .= ' '.$offset.'>'.ProcessUtils::escapeArgument($filename);
}
$commandline .= '"';

if (!isset($this->options['bypass_shell'])) {
$this->options['bypass_shell'] = true;
}
} elseif (!$this->useFileHandles && $this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {

 $descriptors[3] = array('pipe', 'w');


 $commandline = '{ ('.$this->commandline.') <&3 3<&- 3>/dev/null & } 3<&0;';
$commandline .= 'pid=$!; echo $pid >&3; wait $pid; code=$?; echo $code >&3; exit $code';


 
 $ptsWorkaround = fopen(__FILE__, 'r');
}

$this->process = proc_open($commandline, $descriptors, $this->processPipes->pipes, $this->cwd, $this->env, $this->options);

if (!\is_resource($this->process)) {
throw new RuntimeException('Unable to launch a new process.');
}
$this->status = self::STATUS_STARTED;

if (isset($descriptors[3])) {
$this->fallbackStatus['pid'] = (int) fgets($this->processPipes->pipes[3]);
}

if ($this->tty) {
return;
}

$this->updateStatus(false);
$this->checkTimeout();
}
















public function restart($callback = null)
{
if ($this->isRunning()) {
throw new RuntimeException('Process is already running');
}

$process = clone $this;
$process->start($callback);

return $process;
}
















public function wait($callback = null)
{
$this->requireProcessIsStarted(__FUNCTION__);

$this->updateStatus(false);
if (null !== $callback) {
$this->callback = $this->buildCallback($callback);
}

do {
$this->checkTimeout();
$running = '\\' === \DIRECTORY_SEPARATOR ? $this->isRunning() : $this->processPipes->areOpen();
$this->readPipes($running, '\\' !== \DIRECTORY_SEPARATOR || !$running);
} while ($running);

while ($this->isRunning()) {
usleep(1000);
}

if ($this->processInformation['signaled'] && $this->processInformation['termsig'] !== $this->latestSignal) {
throw new RuntimeException(sprintf('The process has been signaled with signal "%s".', $this->processInformation['termsig']));
}

return $this->exitcode;
}






public function getPid()
{
return $this->isRunning() ? $this->processInformation['pid'] : null;
}












public function signal($signal)
{
$this->doSignal($signal, true);

return $this;
}









public function disableOutput()
{
if ($this->isRunning()) {
throw new RuntimeException('Disabling output while the process is running is not possible.');
}
if (null !== $this->idleTimeout) {
throw new LogicException('Output can not be disabled while an idle timeout is set.');
}

$this->outputDisabled = true;

return $this;
}








public function enableOutput()
{
if ($this->isRunning()) {
throw new RuntimeException('Enabling output while the process is running is not possible.');
}

$this->outputDisabled = false;

return $this;
}






public function isOutputDisabled()
{
return $this->outputDisabled;
}









public function getOutput()
{
$this->readPipesForOutput(__FUNCTION__);

if (false === $ret = stream_get_contents($this->stdout, -1, 0)) {
return '';
}

return $ret;
}












public function getIncrementalOutput()
{
$this->readPipesForOutput(__FUNCTION__);

$latest = stream_get_contents($this->stdout, -1, $this->incrementalOutputOffset);
$this->incrementalOutputOffset = ftell($this->stdout);

if (false === $latest) {
return '';
}

return $latest;
}






public function clearOutput()
{
ftruncate($this->stdout, 0);
fseek($this->stdout, 0);
$this->incrementalOutputOffset = 0;

return $this;
}









public function getErrorOutput()
{
$this->readPipesForOutput(__FUNCTION__);

if (false === $ret = stream_get_contents($this->stderr, -1, 0)) {
return '';
}

return $ret;
}













public function getIncrementalErrorOutput()
{
$this->readPipesForOutput(__FUNCTION__);

$latest = stream_get_contents($this->stderr, -1, $this->incrementalErrorOutputOffset);
$this->incrementalErrorOutputOffset = ftell($this->stderr);

if (false === $latest) {
return '';
}

return $latest;
}






public function clearErrorOutput()
{
ftruncate($this->stderr, 0);
fseek($this->stderr, 0);
$this->incrementalErrorOutputOffset = 0;

return $this;
}








public function getExitCode()
{
if (!$this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
throw new RuntimeException('This PHP has been compiled with --enable-sigchild. You must use setEnhanceSigchildCompatibility() to use this method.');
}

$this->updateStatus(false);

return $this->exitcode;
}












public function getExitCodeText()
{
if (null === $exitcode = $this->getExitCode()) {
return;
}

return isset(self::$exitCodes[$exitcode]) ? self::$exitCodes[$exitcode] : 'Unknown error';
}






public function isSuccessful()
{
return 0 === $this->getExitCode();
}











public function hasBeenSignaled()
{
$this->requireProcessIsTerminated(__FUNCTION__);

if (!$this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
throw new RuntimeException('This PHP has been compiled with --enable-sigchild. Term signal can not be retrieved.');
}

return $this->processInformation['signaled'];
}











public function getTermSignal()
{
$this->requireProcessIsTerminated(__FUNCTION__);

if ($this->isSigchildEnabled() && (!$this->enhanceSigchildCompatibility || -1 === $this->processInformation['termsig'])) {
throw new RuntimeException('This PHP has been compiled with --enable-sigchild. Term signal can not be retrieved.');
}

return $this->processInformation['termsig'];
}










public function hasBeenStopped()
{
$this->requireProcessIsTerminated(__FUNCTION__);

return $this->processInformation['stopped'];
}










public function getStopSignal()
{
$this->requireProcessIsTerminated(__FUNCTION__);

return $this->processInformation['stopsig'];
}






public function isRunning()
{
if (self::STATUS_STARTED !== $this->status) {
return false;
}

$this->updateStatus(false);

return $this->processInformation['running'];
}






public function isStarted()
{
return self::STATUS_READY != $this->status;
}






public function isTerminated()
{
$this->updateStatus(false);

return self::STATUS_TERMINATED == $this->status;
}








public function getStatus()
{
$this->updateStatus(false);

return $this->status;
}









public function stop($timeout = 10, $signal = null)
{
$timeoutMicro = microtime(true) + $timeout;
if ($this->isRunning()) {

 $this->doSignal(15, false);
do {
usleep(1000);
} while ($this->isRunning() && microtime(true) < $timeoutMicro);

if ($this->isRunning()) {

 
 $this->doSignal($signal ?: 9, false);
}
}

if ($this->isRunning()) {
if (isset($this->fallbackStatus['pid'])) {
unset($this->fallbackStatus['pid']);

return $this->stop(0, $signal);
}
$this->close();
}

return $this->exitcode;
}








public function addOutput($line)
{
$this->lastOutputTime = microtime(true);

fseek($this->stdout, 0, SEEK_END);
fwrite($this->stdout, $line);
fseek($this->stdout, $this->incrementalOutputOffset);
}








public function addErrorOutput($line)
{
$this->lastOutputTime = microtime(true);

fseek($this->stderr, 0, SEEK_END);
fwrite($this->stderr, $line);
fseek($this->stderr, $this->incrementalErrorOutputOffset);
}






public function getCommandLine()
{
return $this->commandline;
}








public function setCommandLine($commandline)
{
$this->commandline = $commandline;

return $this;
}






public function getTimeout()
{
return $this->timeout;
}






public function getIdleTimeout()
{
return $this->idleTimeout;
}












public function setTimeout($timeout)
{
$this->timeout = $this->validateTimeout($timeout);

return $this;
}













public function setIdleTimeout($timeout)
{
if (null !== $timeout && $this->outputDisabled) {
throw new LogicException('Idle timeout can not be set while the output is disabled.');
}

$this->idleTimeout = $this->validateTimeout($timeout);

return $this;
}










public function setTty($tty)
{
if ('\\' === \DIRECTORY_SEPARATOR && $tty) {
throw new RuntimeException('TTY mode is not supported on Windows platform.');
}
if ($tty) {
static $isTtySupported;

if (null === $isTtySupported) {
$isTtySupported = (bool) @proc_open('echo 1 >/dev/null', array(array('file', '/dev/tty', 'r'), array('file', '/dev/tty', 'w'), array('file', '/dev/tty', 'w')), $pipes);
}

if (!$isTtySupported) {
throw new RuntimeException('TTY mode requires /dev/tty to be read/writable.');
}
}

$this->tty = (bool) $tty;

return $this;
}






public function isTty()
{
return $this->tty;
}








public function setPty($bool)
{
$this->pty = (bool) $bool;

return $this;
}






public function isPty()
{
return $this->pty;
}






public function getWorkingDirectory()
{
if (null === $this->cwd) {

 
 return getcwd() ?: null;
}

return $this->cwd;
}








public function setWorkingDirectory($cwd)
{
$this->cwd = $cwd;

return $this;
}






public function getEnv()
{
return $this->env;
}














public function setEnv(array $env)
{

 $env = array_filter($env, function ($value) {
return !\is_array($value);
});

$this->env = array();
foreach ($env as $key => $value) {
$this->env[$key] = (string) $value;
}

return $this;
}










public function getStdin()
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.5 and will be removed in 3.0. Use the getInput() method instead.', E_USER_DEPRECATED);

return $this->getInput();
}






public function getInput()
{
return $this->input;
}














public function setStdin($stdin)
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.5 and will be removed in 3.0. Use the setInput() method instead.', E_USER_DEPRECATED);

return $this->setInput($stdin);
}














public function setInput($input)
{
if ($this->isRunning()) {
throw new LogicException('Input can not be set while the process is running.');
}

$this->input = ProcessUtils::validateInput(__METHOD__, $input);

return $this;
}






public function getOptions()
{
return $this->options;
}








public function setOptions(array $options)
{
$this->options = $options;

return $this;
}








public function getEnhanceWindowsCompatibility()
{
return $this->enhanceWindowsCompatibility;
}








public function setEnhanceWindowsCompatibility($enhance)
{
$this->enhanceWindowsCompatibility = (bool) $enhance;

return $this;
}






public function getEnhanceSigchildCompatibility()
{
return $this->enhanceSigchildCompatibility;
}












public function setEnhanceSigchildCompatibility($enhance)
{
$this->enhanceSigchildCompatibility = (bool) $enhance;

return $this;
}









public function checkTimeout()
{
if (self::STATUS_STARTED !== $this->status) {
return;
}

if (null !== $this->timeout && $this->timeout < microtime(true) - $this->starttime) {
$this->stop(0);

throw new ProcessTimedOutException($this, ProcessTimedOutException::TYPE_GENERAL);
}

if (null !== $this->idleTimeout && $this->idleTimeout < microtime(true) - $this->lastOutputTime) {
$this->stop(0);

throw new ProcessTimedOutException($this, ProcessTimedOutException::TYPE_IDLE);
}
}






public static function isPtySupported()
{
static $result;

if (null !== $result) {
return $result;
}

if ('\\' === \DIRECTORY_SEPARATOR) {
return $result = false;
}

return $result = (bool) @proc_open('echo 1 >/dev/null', array(array('pty'), array('pty'), array('pty')), $pipes);
}






private function getDescriptors()
{
if ('\\' === \DIRECTORY_SEPARATOR) {
$this->processPipes = WindowsPipes::create($this, $this->input);
} else {
$this->processPipes = UnixPipes::create($this, $this->input);
}

return $this->processPipes->getDescriptors();
}











protected function buildCallback($callback)
{
$that = $this;
$out = self::OUT;
$callback = function ($type, $data) use ($that, $callback, $out) {
if ($out == $type) {
$that->addOutput($data);
} else {
$that->addErrorOutput($data);
}

if (null !== $callback) {
\call_user_func($callback, $type, $data);
}
};

return $callback;
}






protected function updateStatus($blocking)
{
if (self::STATUS_STARTED !== $this->status) {
return;
}

$this->processInformation = proc_get_status($this->process);
$running = $this->processInformation['running'];

$this->readPipes($running && $blocking, '\\' !== \DIRECTORY_SEPARATOR || !$running);

if ($this->fallbackStatus && $this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
$this->processInformation = $this->fallbackStatus + $this->processInformation;
}

if (!$running) {
$this->close();
}
}






protected function isSigchildEnabled()
{
if (null !== self::$sigchild) {
return self::$sigchild;
}

if (!\function_exists('phpinfo') || \defined('HHVM_VERSION')) {
return self::$sigchild = false;
}

ob_start();
phpinfo(INFO_GENERAL);

return self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
}








private function readPipesForOutput($caller)
{
if ($this->outputDisabled) {
throw new LogicException('Output has been disabled.');
}

$this->requireProcessIsStarted($caller);

$this->updateStatus(false);
}










private function validateTimeout($timeout)
{
$timeout = (float) $timeout;

if (0.0 === $timeout) {
$timeout = null;
} elseif ($timeout < 0) {
throw new InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
}

return $timeout;
}







private function readPipes($blocking, $close)
{
$result = $this->processPipes->readAndWrite($blocking, $close);

$callback = $this->callback;
foreach ($result as $type => $data) {
if (3 !== $type) {
$callback(self::STDOUT === $type ? self::OUT : self::ERR, $data);
} elseif (!isset($this->fallbackStatus['signaled'])) {
$this->fallbackStatus['exitcode'] = (int) $data;
}
}
}






private function close()
{
$this->processPipes->close();
if (\is_resource($this->process)) {
proc_close($this->process);
}
$this->exitcode = $this->processInformation['exitcode'];
$this->status = self::STATUS_TERMINATED;

if (-1 === $this->exitcode) {
if ($this->processInformation['signaled'] && 0 < $this->processInformation['termsig']) {

 $this->exitcode = 128 + $this->processInformation['termsig'];
} elseif ($this->enhanceSigchildCompatibility && $this->isSigchildEnabled()) {
$this->processInformation['signaled'] = true;
$this->processInformation['termsig'] = -1;
}
}


 
 
 $this->callback = null;

return $this->exitcode;
}




private function resetProcessData()
{
$this->starttime = null;
$this->callback = null;
$this->exitcode = null;
$this->fallbackStatus = array();
$this->processInformation = null;
$this->stdout = fopen('php://temp/maxmemory:'.(1024 * 1024), 'w+b');
$this->stderr = fopen('php://temp/maxmemory:'.(1024 * 1024), 'w+b');
$this->process = null;
$this->latestSignal = null;
$this->status = self::STATUS_READY;
$this->incrementalOutputOffset = 0;
$this->incrementalErrorOutputOffset = 0;
}













private function doSignal($signal, $throwException)
{
if (null === $pid = $this->getPid()) {
if ($throwException) {
throw new LogicException('Can not send signal on a non running process.');
}

return false;
}

if ('\\' === \DIRECTORY_SEPARATOR) {
exec(sprintf('taskkill /F /T /PID %d 2>&1', $pid), $output, $exitCode);
if ($exitCode && $this->isRunning()) {
if ($throwException) {
throw new RuntimeException(sprintf('Unable to kill the process (%s).', implode(' ', $output)));
}

return false;
}
} else {
if (!$this->enhanceSigchildCompatibility || !$this->isSigchildEnabled()) {
$ok = @proc_terminate($this->process, $signal);
} elseif (\function_exists('posix_kill')) {
$ok = @posix_kill($pid, $signal);
} elseif ($ok = proc_open(sprintf('kill -%d %d', $signal, $pid), array(2 => array('pipe', 'w')), $pipes)) {
$ok = false === fgets($pipes[2]);
}
if (!$ok) {
if ($throwException) {
throw new RuntimeException(sprintf('Error while sending signal `%s`.', $signal));
}

return false;
}
}

$this->latestSignal = (int) $signal;
$this->fallbackStatus['signaled'] = true;
$this->fallbackStatus['exitcode'] = -1;
$this->fallbackStatus['termsig'] = $this->latestSignal;

return true;
}








private function requireProcessIsStarted($functionName)
{
if (!$this->isStarted()) {
throw new LogicException(sprintf('Process must be started before calling %s.', $functionName));
}
}








private function requireProcessIsTerminated($functionName)
{
if (!$this->isTerminated()) {
throw new LogicException(sprintf('Process must be terminated before calling %s.', $functionName));
}
}
}
