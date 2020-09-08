<?php

















namespace Composer\Autoload;

use Symfony\Component\Finder\Finder;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;







class ClassMapGenerator
{






public static function dump($dirs, $file)
{
$maps = array();

foreach ($dirs as $dir) {
$maps = array_merge($maps, static::createMap($dir));
}

file_put_contents($file, sprintf('<?php return %s;', var_export($maps, true)));
}













public static function createMap($path, $excluded = null, IOInterface $io = null, $namespace = null, $autoloadType = null, &$scannedFiles = array())
{
$basePath = $path;
if (is_string($path)) {
if (is_file($path)) {
$path = array(new \SplFileInfo($path));
} elseif (is_dir($path) || strpos($path, '*') !== false) {
$path = Finder::create()->files()->followLinks()->name('/\.(php|inc|hh)$/')->in($path);
} else {
throw new \RuntimeException(
'Could not scan for classes inside "'.$path.
'" which does not appear to be a file nor a folder'
);
}
} elseif (null !== $autoloadType) {
throw new \RuntimeException('Path must be a string when specifying an autoload type');
}

$map = array();
$filesystem = new Filesystem();
$cwd = realpath(getcwd());

foreach ($path as $file) {
$filePath = $file->getPathname();
if (!in_array(pathinfo($filePath, PATHINFO_EXTENSION), array('php', 'inc', 'hh'))) {
continue;
}

if (!$filesystem->isAbsolutePath($filePath)) {
$filePath = $cwd . '/' . $filePath;
$filePath = $filesystem->normalizePath($filePath);
} else {
$filePath = preg_replace('{[\\\\/]{2,}}', '/', $filePath);
}

$realPath = realpath($filePath);


 
 if (isset($scannedFiles[$realPath])) {
continue;
}


 if ($excluded && preg_match($excluded, strtr($realPath, '\\', '/'))) {
continue;
}

 if ($excluded && preg_match($excluded, strtr($filePath, '\\', '/'))) {
continue;
}

$classes = self::findClasses($filePath);
if (null !== $autoloadType) {
$classes = self::filterByNamespace($classes, $filePath, $namespace, $autoloadType, $basePath, $io);


 if ($classes) {
$scannedFiles[$realPath] = true;
}
} else {

 $scannedFiles[$realPath] = true;
}

foreach ($classes as $class) {

 if (null === $autoloadType && null !== $namespace && '' !== $namespace && 0 !== strpos($class, $namespace)) {
continue;
}

if (!isset($map[$class])) {
$map[$class] = $filePath;
} elseif ($io && $map[$class] !== $filePath && !preg_match('{/(test|fixture|example|stub)s?/}i', strtr($map[$class].' '.$filePath, '\\', '/'))) {
$io->writeError(
'<warning>Warning: Ambiguous class resolution, "'.$class.'"'.
' was found in both "'.$map[$class].'" and "'.$filePath.'", the first will be used.</warning>'
);
}
}
}

return $map;
}












private static function filterByNamespace($classes, $filePath, $baseNamespace, $namespaceType, $basePath, $io)
{
$validClasses = array();
$rejectedClasses = array();

$realSubPath = substr($filePath, strlen($basePath) + 1);
$realSubPath = substr($realSubPath, 0, strrpos($realSubPath, '.'));

foreach ($classes as $class) {

 if ('' !== $baseNamespace && 0 !== strpos($class, $baseNamespace)) {
continue;
}

 if ('psr-0' === $namespaceType) {
$namespaceLength = strrpos($class, '\\');
if (false !== $namespaceLength) {
$namespace = substr($class, 0, $namespaceLength + 1);
$className = substr($class, $namespaceLength + 1);
$subPath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
. str_replace('_', DIRECTORY_SEPARATOR, $className);
}
else {
$subPath = str_replace('_', DIRECTORY_SEPARATOR, $class);
}
} elseif ('psr-4' === $namespaceType) {
$subNamespace = ('' !== $baseNamespace) ? substr($class, strlen($baseNamespace)) : $class;
$subPath = str_replace('\\', DIRECTORY_SEPARATOR, $subNamespace);
} else {
throw new \RuntimeException("namespaceType must be psr-0 or psr-4, $namespaceType given");
}
if ($subPath === $realSubPath) {
$validClasses[] = $class;
} else {
$rejectedClasses[] = $class;
}
}

 if (empty($validClasses)) {
foreach ($rejectedClasses as $class) {
if ($io) {
$io->writeError("<warning>Class $class located in ".preg_replace('{^'.preg_quote(getcwd()).'}', '.', $filePath, 1)." does not comply with $namespaceType autoloading standard. Skipping.</warning>");
}
}

return array();
}

return $validClasses;
}








private static function findClasses($path)
{
$extraTypes = PHP_VERSION_ID < 50400 ? '' : '|trait';
if (defined('HHVM_VERSION') && version_compare(HHVM_VERSION, '3.3', '>=')) {
$extraTypes .= '|enum';
}


 
 $contents = @php_strip_whitespace($path);
if (!$contents) {
if (!file_exists($path)) {
$message = 'File at "%s" does not exist, check your classmap definitions';
} elseif (!is_readable($path)) {
$message = 'File at "%s" is not readable, check its permissions';
} elseif ('' === trim(file_get_contents($path))) {

 return array();
} else {
$message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
}
$error = error_get_last();
if (isset($error['message'])) {
$message .= PHP_EOL . 'The following message may be helpful:' . PHP_EOL . $error['message'];
}
throw new \RuntimeException(sprintf($message, $path));
}


 if (!preg_match('{\b(?:class|interface'.$extraTypes.')\s}i', $contents)) {
return array();
}


 $contents = preg_replace('{<<<[ \t]*([\'"]?)(\w+)\\1(?:\r\n|\n|\r)(?:.*?)(?:\r\n|\n|\r)(?:\s*)\\2(?=\s+|[;,.)])}s', 'null', $contents);

 $contents = preg_replace('{"[^"\\\\]*+(\\\\.[^"\\\\]*+)*+"|\'[^\'\\\\]*+(\\\\.[^\'\\\\]*+)*+\'}s', 'null', $contents);

 if (substr($contents, 0, 2) !== '<?') {
$contents = preg_replace('{^.+?<\?}s', '<?', $contents, 1, $replacements);
if ($replacements === 0) {
return array();
}
}

 $contents = preg_replace('{\?>(?:[^<]++|<(?!\?))*+<\?}s', '?><?', $contents);

 $pos = strrpos($contents, '?>');
if (false !== $pos && false === strpos(substr($contents, $pos), '<?')) {
$contents = substr($contents, 0, $pos);
}

 if (preg_match('{(<\?)(?!(php|hh))}i', $contents)) {
$contents = preg_replace('{//.* | /\*(?:[^*]++|\*(?!/))*\*/}x', '', $contents);
}

preg_match_all('{
            (?:
                 \b(?<![\$:>])(?P<type>class|interface'.$extraTypes.') \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
               | \b(?<![\$:>])(?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
            )
        }ix', $contents, $matches);

$classes = array();
$namespace = '';

for ($i = 0, $len = count($matches['type']); $i < $len; $i++) {
if (!empty($matches['ns'][$i])) {
$namespace = str_replace(array(' ', "\t", "\r", "\n"), '', $matches['nsname'][$i]) . '\\';
} else {
$name = $matches['name'][$i];

 if ($name === 'extends' || $name === 'implements') {
continue;
}
if ($name[0] === ':') {

 $name = 'xhp'.substr(str_replace(array('-', ':'), array('_', '__'), $name), 1);
} elseif ($matches['type'][$i] === 'enum') {

 
 
 
 $name = rtrim($name, ':');
}
$classes[] = ltrim($namespace . $name, '\\');
}
}

return $classes;
}
}
