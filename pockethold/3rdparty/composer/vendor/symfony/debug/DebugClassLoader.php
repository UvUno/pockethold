<?php










namespace Symfony\Component\Debug;












class DebugClassLoader
{
private $classLoader;
private $isFinder;
private $loaded = array();
private $wasFinder;
private static $caseCheck;
private static $deprecated = array();
private static $php7Reserved = array('int', 'float', 'bool', 'string', 'true', 'false', 'null');
private static $darwinCache = array('/' => array('/', array()));




public function __construct($classLoader)
{
$this->wasFinder = \is_object($classLoader) && method_exists($classLoader, 'findFile');

if ($this->wasFinder) {
@trigger_error('The '.__METHOD__.' method will no longer support receiving an object into its $classLoader argument in 3.0.', E_USER_DEPRECATED);
$this->classLoader = array($classLoader, 'loadClass');
$this->isFinder = true;
} else {
$this->classLoader = $classLoader;
$this->isFinder = \is_array($classLoader) && method_exists($classLoader[0], 'findFile');
}

if (!isset(self::$caseCheck)) {
$file = file_exists(__FILE__) ? __FILE__ : rtrim(realpath('.'), \DIRECTORY_SEPARATOR);
$i = strrpos($file, \DIRECTORY_SEPARATOR);
$dir = substr($file, 0, 1 + $i);
$file = substr($file, 1 + $i);
$test = strtoupper($file) === $file ? strtolower($file) : strtoupper($file);
$test = realpath($dir.$test);

if (false === $test || false === $i) {

 self::$caseCheck = 0;
} elseif (substr($test, -\strlen($file)) === $file) {

 self::$caseCheck = 1;
} elseif (false !== stripos(PHP_OS, 'darwin')) {

 self::$caseCheck = 2;
} else {

 self::$caseCheck = 0;
}
}
}






public function getClassLoader()
{
return $this->wasFinder ? $this->classLoader[0] : $this->classLoader;
}




public static function enable()
{

 class_exists('Symfony\Component\Debug\ErrorHandler');
class_exists('Psr\Log\LogLevel');

if (!\is_array($functions = spl_autoload_functions())) {
return;
}

foreach ($functions as $function) {
spl_autoload_unregister($function);
}

foreach ($functions as $function) {
if (!\is_array($function) || !$function[0] instanceof self) {
$function = array(new static($function), 'loadClass');
}

spl_autoload_register($function);
}
}




public static function disable()
{
if (!\is_array($functions = spl_autoload_functions())) {
return;
}

foreach ($functions as $function) {
spl_autoload_unregister($function);
}

foreach ($functions as $function) {
if (\is_array($function) && $function[0] instanceof self) {
$function = $function[0]->getClassLoader();
}

spl_autoload_register($function);
}
}










public function findFile($class)
{
@trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

if ($this->wasFinder) {
return $this->classLoader[0]->findFile($class);
}
}










public function loadClass($class)
{
ErrorHandler::stackErrors();

try {
if ($this->isFinder && !isset($this->loaded[$class])) {
$this->loaded[$class] = true;
if ($file = $this->classLoader[0]->findFile($class)) {
require $file;
}
} else {
\call_user_func($this->classLoader, $class);
$file = false;
}
} catch (\Exception $e) {
ErrorHandler::unstackErrors();

throw $e;
} catch (\Throwable $e) {
ErrorHandler::unstackErrors();

throw $e;
}

ErrorHandler::unstackErrors();

$exists = class_exists($class, false) || interface_exists($class, false) || (\function_exists('trait_exists') && trait_exists($class, false));

if ($class && '\\' === $class[0]) {
$class = substr($class, 1);
}

if ($exists) {
$refl = new \ReflectionClass($class);
$name = $refl->getName();

if ($name !== $class && 0 === strcasecmp($name, $class)) {
throw new \RuntimeException(sprintf('Case mismatch between loaded and declared class names: %s vs %s', $class, $name));
}

if (\in_array(strtolower($refl->getShortName()), self::$php7Reserved)) {
@trigger_error(sprintf('%s uses a reserved class name (%s) that will break on PHP 7 and higher', $name, $refl->getShortName()), E_USER_DEPRECATED);
} elseif (preg_match('#\n \* @deprecated (.*?)\r?\n \*(?: @|/$)#s', $refl->getDocComment(), $notice)) {
self::$deprecated[$name] = preg_replace('#\s*\r?\n \* +#', ' ', $notice[1]);
} else {
if (2 > $len = 1 + (strpos($name, '\\') ?: strpos($name, '_'))) {
$len = 0;
$ns = '';
} else {
$ns = substr($name, 0, $len);
}
$parent = get_parent_class($class);

if (!$parent || strncmp($ns, $parent, $len)) {
if ($parent && isset(self::$deprecated[$parent]) && strncmp($ns, $parent, $len)) {
@trigger_error(sprintf('The %s class extends %s that is deprecated %s', $name, $parent, self::$deprecated[$parent]), E_USER_DEPRECATED);
}

$parentInterfaces = array();
$deprecatedInterfaces = array();
if ($parent) {
foreach (class_implements($parent) as $interface) {
$parentInterfaces[$interface] = 1;
}
}

foreach ($refl->getInterfaceNames() as $interface) {
if (isset(self::$deprecated[$interface]) && strncmp($ns, $interface, $len)) {
$deprecatedInterfaces[] = $interface;
}
foreach (class_implements($interface) as $interface) {
$parentInterfaces[$interface] = 1;
}
}

foreach ($deprecatedInterfaces as $interface) {
if (!isset($parentInterfaces[$interface])) {
@trigger_error(sprintf('The %s %s %s that is deprecated %s', $name, $refl->isInterface() ? 'interface extends' : 'class implements', $interface, self::$deprecated[$interface]), E_USER_DEPRECATED);
}
}
}
}
}

if ($file) {
if (!$exists) {
if (false !== strpos($class, '/')) {
throw new \RuntimeException(sprintf('Trying to autoload a class with an invalid name "%s". Be careful that the namespace separator is "\" in PHP, not "/".', $class));
}

throw new \RuntimeException(sprintf('The autoloader expected class "%s" to be defined in file "%s". The file was found but the class was not in it, the class name or namespace probably has a typo.', $class, $file));
}
if (self::$caseCheck) {
$real = explode('\\', $class.strrchr($file, '.'));
$tail = explode(\DIRECTORY_SEPARATOR, str_replace('/', \DIRECTORY_SEPARATOR, $file));

$i = \count($tail) - 1;
$j = \count($real) - 1;

while (isset($tail[$i], $real[$j]) && $tail[$i] === $real[$j]) {
--$i;
--$j;
}

array_splice($tail, 0, $i + 1);
}
if (self::$caseCheck && $tail) {
$tail = \DIRECTORY_SEPARATOR.implode(\DIRECTORY_SEPARATOR, $tail);
$tailLen = \strlen($tail);
$real = $refl->getFileName();

if (2 === self::$caseCheck) {


$i = 1 + strrpos($real, '/');
$file = substr($real, $i);
$real = substr($real, 0, $i);

if (isset(self::$darwinCache[$real])) {
$kDir = $real;
} else {
$kDir = strtolower($real);

if (isset(self::$darwinCache[$kDir])) {
$real = self::$darwinCache[$kDir][0];
} else {
$dir = getcwd();
chdir($real);
$real = getcwd().'/';
chdir($dir);

$dir = $real;
$k = $kDir;
$i = \strlen($dir) - 1;
while (!isset(self::$darwinCache[$k])) {
self::$darwinCache[$k] = array($dir, array());
self::$darwinCache[$dir] = &self::$darwinCache[$k];

while ('/' !== $dir[--$i]) {
}
$k = substr($k, 0, ++$i);
$dir = substr($dir, 0, $i--);
}
}
}

$dirFiles = self::$darwinCache[$kDir][1];

if (isset($dirFiles[$file])) {
$kFile = $file;
} else {
$kFile = strtolower($file);

if (!isset($dirFiles[$kFile])) {
foreach (scandir($real, 2) as $f) {
if ('.' !== $f[0]) {
$dirFiles[$f] = $f;
if ($f === $file) {
$kFile = $k = $file;
} elseif ($f !== $k = strtolower($f)) {
$dirFiles[$k] = $f;
}
}
}
self::$darwinCache[$kDir][1] = $dirFiles;
}
}

$real .= $dirFiles[$kFile];
}

if (0 === substr_compare($real, $tail, -$tailLen, $tailLen, true)
&& 0 !== substr_compare($real, $tail, -$tailLen, $tailLen, false)
) {
throw new \RuntimeException(sprintf('Case mismatch between class and real file names: %s vs %s in %s', substr($tail, -$tailLen + 1), substr($real, -$tailLen + 1), substr($real, 0, -$tailLen + 1)));
}
}

return true;
}
}
}
