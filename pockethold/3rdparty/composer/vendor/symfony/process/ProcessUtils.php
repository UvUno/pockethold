<?php










namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\InvalidArgumentException;








class ProcessUtils
{



private function __construct()
{
}








public static function escapeArgument($argument)
{

 
 
 
 if ('\\' === \DIRECTORY_SEPARATOR) {
if ('' === $argument) {
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













public static function validateInput($caller, $input)
{
if (null !== $input) {
if (\is_resource($input)) {
return $input;
}
if (\is_string($input)) {
return $input;
}
if (is_scalar($input)) {
return (string) $input;
}

 if (\is_object($input) && method_exists($input, '__toString')) {
@trigger_error('Passing an object as an input is deprecated since Symfony 2.5 and will be removed in 3.0.', E_USER_DEPRECATED);

return (string) $input;
}

throw new InvalidArgumentException(sprintf('%s only accepts strings or stream resources.', $caller));
}

return $input;
}

private static function isSurroundedBy($arg, $char)
{
return 2 < \strlen($arg) && $char === $arg[0] && $char === $arg[\strlen($arg) - 1];
}
}
