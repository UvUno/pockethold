<?php











namespace Composer\Package\Version;

use Composer\Package\BasePackage;




class StabilityFilter
{











public static function isPackageAcceptable(array $acceptableStabilities, array $stabilityFlags, $names, $stability)
{
foreach ($names as $name) {

 if (isset($stabilityFlags[$name])) {
if (BasePackage::$stabilities[$stability] <= $stabilityFlags[$name]) {
return true;
}
} elseif (isset($acceptableStabilities[$stability])) {

 return true;
}
}

return false;
}
}
