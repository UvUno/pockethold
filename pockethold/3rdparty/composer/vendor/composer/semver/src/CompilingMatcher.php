<?php










namespace Composer\Semver;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;




class CompilingMatcher
{
private static $compiledCheckerCache = array();
private static $enabled = null;




private static $transOpInt = array(
Constraint::OP_EQ => '==',
Constraint::OP_LT => '<',
Constraint::OP_LE => '<=',
Constraint::OP_GT => '>',
Constraint::OP_GE => '>=',
Constraint::OP_NE => '!=',
);











public static function match(ConstraintInterface $constraint, $operator, $version)
{
if (self::$enabled === null) {
self::$enabled = !\in_array('eval', explode(',', ini_get('disable_functions')), true);
}
if (!self::$enabled) {
return $constraint->matches(new Constraint(self::$transOpInt[$operator], $version));
}

$cacheKey = $operator.$constraint;
if (!isset(self::$compiledCheckerCache[$cacheKey])) {
$code = $constraint->compile($operator);
self::$compiledCheckerCache[$cacheKey] = $function = eval('return function($v, $b){return '.$code.';};');
} else {
$function = self::$compiledCheckerCache[$cacheKey];
}

return $function($version, $version[0] === 'd' && 'dev-' === substr($version, 0, 4));
}
}
