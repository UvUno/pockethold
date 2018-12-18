<?php










namespace Symfony\Component\Finder\Expression;

@trigger_error('The '.__NAMESPACE__.'\ValueInterface interface is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);




interface ValueInterface
{





public function render();






public function renderPattern();






public function isCaseSensitive();






public function getType();






public function prepend($expr);






public function append($expr);
}
