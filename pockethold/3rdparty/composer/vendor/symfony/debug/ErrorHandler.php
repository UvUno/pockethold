<?php










namespace Symfony\Component\Debug;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\Debug\FatalErrorHandler\ClassNotFoundFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\FatalErrorHandlerInterface;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedFunctionFatalErrorHandler;
use Symfony\Component\Debug\FatalErrorHandler\UndefinedMethodFatalErrorHandler;























class ErrorHandler
{



const TYPE_DEPRECATION = -100;

private $levels = array(
E_DEPRECATED => 'Deprecated',
E_USER_DEPRECATED => 'User Deprecated',
E_NOTICE => 'Notice',
E_USER_NOTICE => 'User Notice',
E_STRICT => 'Runtime Notice',
E_WARNING => 'Warning',
E_USER_WARNING => 'User Warning',
E_COMPILE_WARNING => 'Compile Warning',
E_CORE_WARNING => 'Core Warning',
E_USER_ERROR => 'User Error',
E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
E_COMPILE_ERROR => 'Compile Error',
E_PARSE => 'Parse Error',
E_ERROR => 'Error',
E_CORE_ERROR => 'Core Error',
);

private $loggers = array(
E_DEPRECATED => array(null, LogLevel::INFO),
E_USER_DEPRECATED => array(null, LogLevel::INFO),
E_NOTICE => array(null, LogLevel::WARNING),
E_USER_NOTICE => array(null, LogLevel::WARNING),
E_STRICT => array(null, LogLevel::WARNING),
E_WARNING => array(null, LogLevel::WARNING),
E_USER_WARNING => array(null, LogLevel::WARNING),
E_COMPILE_WARNING => array(null, LogLevel::WARNING),
E_CORE_WARNING => array(null, LogLevel::WARNING),
E_USER_ERROR => array(null, LogLevel::CRITICAL),
E_RECOVERABLE_ERROR => array(null, LogLevel::CRITICAL),
E_COMPILE_ERROR => array(null, LogLevel::CRITICAL),
E_PARSE => array(null, LogLevel::CRITICAL),
E_ERROR => array(null, LogLevel::CRITICAL),
E_CORE_ERROR => array(null, LogLevel::CRITICAL),
);

private $thrownErrors = 0x1FFF; 
 private $scopedErrors = 0x1FFF; 
 private $tracedErrors = 0x77FB; 
 private $screamedErrors = 0x55; 
 private $loggedErrors = 0;

private $loggedTraces = array();
private $isRecursive = 0;
private $isRoot = false;
private $exceptionHandler;
private $bootstrappingLogger;

private static $reservedMemory;
private static $stackedErrors = array();
private static $stackedErrorLevels = array();
private static $toStringException = null;
private static $exitCode = 0;






private $displayErrors = 0x1FFF;









public static function register($handler = null, $replace = true)
{
if (null === self::$reservedMemory) {
self::$reservedMemory = str_repeat('x', 10240);
register_shutdown_function(__CLASS__.'::handleFatalError');
}

$levels = -1;

if ($handlerIsNew = !$handler instanceof self) {

 if (null !== $handler) {
$levels = $replace ? $handler : 0;
$replace = true;
}
$handler = new static();
}

if (null === $prev = set_error_handler(array($handler, 'handleError'))) {
restore_error_handler();

 set_error_handler(array($handler, 'handleError'), $handler->thrownErrors | $handler->loggedErrors);
$handler->isRoot = true;
}

if ($handlerIsNew && \is_array($prev) && $prev[0] instanceof self) {
$handler = $prev[0];
$replace = false;
}
if (!$replace && $prev) {
restore_error_handler();
$handlerIsRegistered = \is_array($prev) && $handler === $prev[0];
} else {
$handlerIsRegistered = true;
}
if (\is_array($prev = set_exception_handler(array($handler, 'handleException'))) && $prev[0] instanceof self) {
restore_exception_handler();
if (!$handlerIsRegistered) {
$handler = $prev[0];
} elseif ($handler !== $prev[0] && $replace) {
set_exception_handler(array($handler, 'handleException'));
$p = $prev[0]->setExceptionHandler(null);
$handler->setExceptionHandler($p);
$prev[0]->setExceptionHandler($p);
}
} else {
$handler->setExceptionHandler($prev);
}

$handler->throwAt($levels & $handler->thrownErrors, true);

return $handler;
}

public function __construct(BufferingLogger $bootstrappingLogger = null)
{
if ($bootstrappingLogger) {
$this->bootstrappingLogger = $bootstrappingLogger;
$this->setDefaultLogger($bootstrappingLogger);
}
}








public function setDefaultLogger(LoggerInterface $logger, $levels = null, $replace = false)
{
$loggers = array();

if (\is_array($levels)) {
foreach ($levels as $type => $logLevel) {
if (empty($this->loggers[$type][0]) || $replace || $this->loggers[$type][0] === $this->bootstrappingLogger) {
$loggers[$type] = array($logger, $logLevel);
}
}
} else {
if (null === $levels) {
$levels = E_ALL | E_STRICT;
}
foreach ($this->loggers as $type => $log) {
if (($type & $levels) && (empty($log[0]) || $replace || $log[0] === $this->bootstrappingLogger)) {
$log[0] = $logger;
$loggers[$type] = $log;
}
}
}

$this->setLoggers($loggers);
}










public function setLoggers(array $loggers)
{
$prevLogged = $this->loggedErrors;
$prev = $this->loggers;
$flush = array();

foreach ($loggers as $type => $log) {
if (!isset($prev[$type])) {
throw new \InvalidArgumentException('Unknown error type: '.$type);
}
if (!\is_array($log)) {
$log = array($log);
} elseif (!array_key_exists(0, $log)) {
throw new \InvalidArgumentException('No logger provided');
}
if (null === $log[0]) {
$this->loggedErrors &= ~$type;
} elseif ($log[0] instanceof LoggerInterface) {
$this->loggedErrors |= $type;
} else {
throw new \InvalidArgumentException('Invalid logger provided');
}
$this->loggers[$type] = $log + $prev[$type];

if ($this->bootstrappingLogger && $prev[$type][0] === $this->bootstrappingLogger) {
$flush[$type] = $type;
}
}
$this->reRegister($prevLogged | $this->thrownErrors);

if ($flush) {
foreach ($this->bootstrappingLogger->cleanLogs() as $log) {
$type = $log[2]['type'];
if (!isset($flush[$type])) {
$this->bootstrappingLogger->log($log[0], $log[1], $log[2]);
} elseif ($this->loggers[$type][0]) {
$this->loggers[$type][0]->log($this->loggers[$type][1], $log[1], $log[2]);
}
}
}

return $prev;
}










public function setExceptionHandler($handler)
{
if (null !== $handler && !\is_callable($handler)) {
throw new \LogicException('The exception handler must be a valid PHP callable.');
}
$prev = $this->exceptionHandler;
$this->exceptionHandler = $handler;

return $prev;
}









public function throwAt($levels, $replace = false)
{
$prev = $this->thrownErrors;
$this->thrownErrors = ($levels | E_RECOVERABLE_ERROR | E_USER_ERROR) & ~E_USER_DEPRECATED & ~E_DEPRECATED;
if (!$replace) {
$this->thrownErrors |= $prev;
}
$this->reRegister($prev | $this->loggedErrors);


 $this->displayErrors = $this->thrownErrors;

return $prev;
}









public function scopeAt($levels, $replace = false)
{
$prev = $this->scopedErrors;
$this->scopedErrors = (int) $levels;
if (!$replace) {
$this->scopedErrors |= $prev;
}

return $prev;
}









public function traceAt($levels, $replace = false)
{
$prev = $this->tracedErrors;
$this->tracedErrors = (int) $levels;
if (!$replace) {
$this->tracedErrors |= $prev;
}

return $prev;
}









public function screamAt($levels, $replace = false)
{
$prev = $this->screamedErrors;
$this->screamedErrors = (int) $levels;
if (!$replace) {
$this->screamedErrors |= $prev;
}

return $prev;
}




private function reRegister($prev)
{
if ($prev !== $this->thrownErrors | $this->loggedErrors) {
$handler = set_error_handler('var_dump');
$handler = \is_array($handler) ? $handler[0] : null;
restore_error_handler();
if ($handler === $this) {
restore_error_handler();
if ($this->isRoot) {
set_error_handler(array($this, 'handleError'), $this->thrownErrors | $this->loggedErrors);
} else {
set_error_handler(array($this, 'handleError'));
}
}
}
}















public function handleError($type, $message, $file, $line)
{
$level = error_reporting();
$silenced = 0 === ($level & $type);
$level |= E_RECOVERABLE_ERROR | E_USER_ERROR | E_DEPRECATED | E_USER_DEPRECATED;
$log = $this->loggedErrors & $type;
$throw = $this->thrownErrors & $type & $level;
$type &= $level | $this->screamedErrors;

if (!$type || (!$log && !$throw)) {
return !$silenced && $type && $log;
}
$scope = $this->scopedErrors & $type;

if (4 < $numArgs = \func_num_args()) {
$context = $scope ? (func_get_arg(4) ?: array()) : array();
$backtrace = 5 < $numArgs ? func_get_arg(5) : null; 
 } else {
$context = array();
$backtrace = null;
}

if (isset($context['GLOBALS']) && $scope) {
$e = $context; 
 unset($e['GLOBALS'], $context); 
 $context = $e;
}

if (null !== $backtrace && $type & E_ERROR) {

 
 
 $this->handleFatalError(compact('type', 'message', 'file', 'line', 'backtrace'));

return true;
}

if ($throw) {
if (null !== self::$toStringException) {
$throw = self::$toStringException;
self::$toStringException = null;
} elseif ($scope && class_exists('Symfony\Component\Debug\Exception\ContextErrorException')) {

 $throw = new ContextErrorException($this->levels[$type].': '.$message, 0, $type, $file, $line, $context);
} else {
$throw = new \ErrorException($this->levels[$type].': '.$message, 0, $type, $file, $line);
}

if (\PHP_VERSION_ID <= 50407 && (\PHP_VERSION_ID >= 50400 || \PHP_VERSION_ID <= 50317)) {

 
 

$throw->errorHandlerCanary = new ErrorHandlerCanary();
}

if (E_USER_ERROR & $type) {
$backtrace = $backtrace ?: $throw->getTrace();

for ($i = 1; isset($backtrace[$i]); ++$i) {
if (isset($backtrace[$i]['function'], $backtrace[$i]['type'], $backtrace[$i - 1]['function'])
&& '__toString' === $backtrace[$i]['function']
&& '->' === $backtrace[$i]['type']
&& !isset($backtrace[$i - 1]['class'])
&& ('trigger_error' === $backtrace[$i - 1]['function'] || 'user_error' === $backtrace[$i - 1]['function'])
) {

 
 
 
 
 

foreach ($context as $e) {
if (($e instanceof \Exception || $e instanceof \Throwable) && $e->__toString() === $message) {
if (1 === $i) {

 $throw = $e;
break;
}
self::$toStringException = $e;

return true;
}
}

if (1 < $i) {

 $this->handleException($throw);


 return false;
}
}
}
}

throw $throw;
}


 $e = md5("{$type}/{$line}/{$file}\x00{$message}", true);
$trace = true;

if (!($this->tracedErrors & $type) || isset($this->loggedTraces[$e])) {
$trace = false;
} else {
$this->loggedTraces[$e] = 1;
}

$e = compact('type', 'file', 'line', 'level');

if ($type & $level) {
if ($scope) {
$e['scope_vars'] = $context;
if ($trace) {
$e['stack'] = $backtrace ?: debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
}
} elseif ($trace) {
if (null === $backtrace) {
$e['stack'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
} else {
foreach ($backtrace as &$frame) {
unset($frame['args'], $frame);
}
$e['stack'] = $backtrace;
}
}
}

if ($this->isRecursive) {
$log = 0;
} elseif (self::$stackedErrorLevels) {
self::$stackedErrors[] = array($this->loggers[$type][0], ($type & $level) ? $this->loggers[$type][1] : LogLevel::DEBUG, $message, $e);
} else {
try {
$this->isRecursive = true;
$this->loggers[$type][0]->log(($type & $level) ? $this->loggers[$type][1] : LogLevel::DEBUG, $message, $e);
$this->isRecursive = false;
} catch (\Exception $e) {
$this->isRecursive = false;

throw $e;
} catch (\Throwable $e) {
$this->isRecursive = false;

throw $e;
}
}

return !$silenced && $type && $log;
}









public function handleException($exception, array $error = null)
{
if (null === $error) {
self::$exitCode = 255;
}
if (!$exception instanceof \Exception) {
$exception = new FatalThrowableError($exception);
}
$type = $exception instanceof FatalErrorException ? $exception->getSeverity() : E_ERROR;
$handlerException = null;

if (($this->loggedErrors & $type) || $exception instanceof FatalThrowableError) {
$e = array(
'type' => $type,
'file' => $exception->getFile(),
'line' => $exception->getLine(),
'level' => error_reporting(),
'stack' => $exception->getTrace(),
);
if ($exception instanceof FatalErrorException) {
if ($exception instanceof FatalThrowableError) {
$error = array(
'type' => $type,
'message' => $message = $exception->getMessage(),
'file' => $e['file'],
'line' => $e['line'],
);
} else {
$message = 'Fatal '.$exception->getMessage();
}
} elseif ($exception instanceof \ErrorException) {
$message = 'Uncaught '.$exception->getMessage();
if ($exception instanceof ContextErrorException) {
$e['context'] = $exception->getContext();
}
} else {
$message = 'Uncaught Exception: '.$exception->getMessage();
}
}
if ($this->loggedErrors & $type) {
try {
$this->loggers[$type][0]->log($this->loggers[$type][1], $message, $e);
} catch (\Exception $handlerException) {
} catch (\Throwable $handlerException) {
}
}
if ($exception instanceof FatalErrorException && !$exception instanceof OutOfMemoryException && $error) {
foreach ($this->getFatalErrorHandlers() as $handler) {
if ($e = $handler->handleError($error, $exception)) {
$exception = $e;
break;
}
}
}
$exceptionHandler = $this->exceptionHandler;
$this->exceptionHandler = null;
try {
if (null !== $exceptionHandler) {
return \call_user_func($exceptionHandler, $exception);
}
$handlerException = $handlerException ?: $exception;
} catch (\Exception $handlerException) {
} catch (\Throwable $handlerException) {
}
if ($exception === $handlerException) {
self::$reservedMemory = null; 
 throw $exception; 
 }
$this->handleException($handlerException);
}








public static function handleFatalError(array $error = null)
{
if (null === self::$reservedMemory) {
return;
}

$handler = self::$reservedMemory = null;
$handlers = array();
$previousHandler = null;
$sameHandlerLimit = 10;

while (!\is_array($handler) || !$handler[0] instanceof self) {
$handler = set_exception_handler('var_dump');
restore_exception_handler();

if (!$handler) {
break;
}
restore_exception_handler();

if ($handler !== $previousHandler) {
array_unshift($handlers, $handler);
$previousHandler = $handler;
} elseif (0 === --$sameHandlerLimit) {
$handler = null;
break;
}
}
foreach ($handlers as $h) {
set_exception_handler($h);
}
if (!$handler) {
return;
}
if ($handler !== $h) {
$handler[0]->setExceptionHandler($h);
}
$handler = $handler[0];
$handlers = array();

if ($exit = null === $error) {
$error = error_get_last();
}

try {
while (self::$stackedErrorLevels) {
static::unstackErrors();
}
} catch (\Exception $exception) {

 } catch (\Throwable $exception) {

 }

if ($error && $error['type'] &= E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR) {

 $handler->throwAt(0, true);
$trace = isset($error['backtrace']) ? $error['backtrace'] : null;

if (0 === strpos($error['message'], 'Allowed memory') || 0 === strpos($error['message'], 'Out of memory')) {
$exception = new OutOfMemoryException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, false, $trace);
} else {
$exception = new FatalErrorException($handler->levels[$error['type']].': '.$error['message'], 0, $error['type'], $error['file'], $error['line'], 2, true, $trace);
}
}

try {
if (isset($exception)) {
self::$exitCode = 255;
$handler->handleException($exception, $error);
}
} catch (FatalErrorException $e) {

 }

if ($exit && self::$exitCode) {
$exitCode = self::$exitCode;
register_shutdown_function('register_shutdown_function', function () use ($exitCode) { exit($exitCode); });
}
}












public static function stackErrors()
{
self::$stackedErrorLevels[] = error_reporting(error_reporting() | E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);
}




public static function unstackErrors()
{
$level = array_pop(self::$stackedErrorLevels);

if (null !== $level) {
$e = error_reporting($level);
if ($e !== ($level | E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR)) {

 error_reporting($e);
}
}

if (empty(self::$stackedErrorLevels)) {
$errors = self::$stackedErrors;
self::$stackedErrors = array();

foreach ($errors as $e) {
$e[0]->log($e[1], $e[2], $e[3]);
}
}
}








protected function getFatalErrorHandlers()
{
return array(
new UndefinedFunctionFatalErrorHandler(),
new UndefinedMethodFatalErrorHandler(),
new ClassNotFoundFatalErrorHandler(),
);
}








public function setLevel($level)
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.6 and will be removed in 3.0. Use the throwAt() method instead.', E_USER_DEPRECATED);

$level = null === $level ? error_reporting() : $level;
$this->throwAt($level, true);
}








public function setDisplayErrors($displayErrors)
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.6 and will be removed in 3.0. Use the throwAt() method instead.', E_USER_DEPRECATED);

if ($displayErrors) {
$this->throwAt($this->displayErrors, true);
} else {
$displayErrors = $this->displayErrors;
$this->throwAt(0, true);
$this->displayErrors = $displayErrors;
}
}









public static function setLogger(LoggerInterface $logger, $channel = 'deprecation')
{
@trigger_error('The '.__METHOD__.' static method is deprecated since Symfony 2.6 and will be removed in 3.0. Use the setLoggers() or setDefaultLogger() methods instead.', E_USER_DEPRECATED);

$handler = set_error_handler('var_dump');
$handler = \is_array($handler) ? $handler[0] : null;
restore_error_handler();
if (!$handler instanceof self) {
return;
}
if ('deprecation' === $channel) {
$handler->setDefaultLogger($logger, E_DEPRECATED | E_USER_DEPRECATED, true);
$handler->screamAt(E_DEPRECATED | E_USER_DEPRECATED);
} elseif ('scream' === $channel) {
$handler->setDefaultLogger($logger, E_ALL | E_STRICT, false);
$handler->screamAt(E_ALL | E_STRICT);
} elseif ('emergency' === $channel) {
$handler->setDefaultLogger($logger, E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR, true);
$handler->screamAt(E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);
}
}




public function handle($level, $message, $file = 'unknown', $line = 0, $context = array())
{
$this->handleError(E_USER_DEPRECATED, 'The '.__METHOD__.' method is deprecated since Symfony 2.6 and will be removed in 3.0. Use the handleError() method instead.', __FILE__, __LINE__, array());

return $this->handleError($level, $message, $file, $line, (array) $context);
}






public function handleFatal()
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.6 and will be removed in 3.0. Use the handleFatalError() method instead.', E_USER_DEPRECATED);

static::handleFatalError();
}
}








class ErrorHandlerCanary
{
private static $displayErrors = null;

public function __construct()
{
if (null === self::$displayErrors) {
self::$displayErrors = ini_set('display_errors', 1);
}
}

public function __destruct()
{
if (null !== self::$displayErrors) {
ini_set('display_errors', self::$displayErrors);
self::$displayErrors = null;
}
}
}
