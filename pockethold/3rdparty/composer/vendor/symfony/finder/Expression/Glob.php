<?php










namespace Symfony\Component\Finder\Expression;

@trigger_error('The '.__NAMESPACE__.'\Glob class is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Finder\Glob as FinderGlob;




class Glob implements ValueInterface
{
private $pattern;




public function __construct($pattern)
{
$this->pattern = $pattern;
}




public function render()
{
return $this->pattern;
}




public function renderPattern()
{
return $this->pattern;
}




public function getType()
{
return Expression::TYPE_GLOB;
}




public function isCaseSensitive()
{
return true;
}




public function prepend($expr)
{
$this->pattern = $expr.$this->pattern;

return $this;
}




public function append($expr)
{
$this->pattern .= $expr;

return $this;
}






public function isExpandable()
{
return false !== strpos($this->pattern, '{')
&& false !== strpos($this->pattern, '}');
}







public function toRegex($strictLeadingDot = true, $strictWildcardSlash = true)
{
$regex = FinderGlob::toRegex($this->pattern, $strictLeadingDot, $strictWildcardSlash, '');

return new Regex($regex);
}
}
