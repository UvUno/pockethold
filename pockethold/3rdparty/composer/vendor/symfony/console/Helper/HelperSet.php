<?php










namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;






class HelperSet implements \IteratorAggregate
{



private $helpers = array();
private $command;




public function __construct(array $helpers = array())
{
foreach ($helpers as $alias => $helper) {
$this->set($helper, \is_int($alias) ? null : $alias);
}
}







public function set(HelperInterface $helper, $alias = null)
{
$this->helpers[$helper->getName()] = $helper;
if (null !== $alias) {
$this->helpers[$alias] = $helper;
}

$helper->setHelperSet($this);
}








public function has($name)
{
return isset($this->helpers[$name]);
}










public function get($name)
{
if (!$this->has($name)) {
throw new InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
}

if ('dialog' === $name && $this->helpers[$name] instanceof DialogHelper) {
@trigger_error('"Symfony\Component\Console\Helper\DialogHelper" is deprecated since Symfony 2.5 and will be removed in 3.0. Use "Symfony\Component\Console\Helper\QuestionHelper" instead.', E_USER_DEPRECATED);
} elseif ('progress' === $name && $this->helpers[$name] instanceof ProgressHelper) {
@trigger_error('"Symfony\Component\Console\Helper\ProgressHelper" is deprecated since Symfony 2.5 and will be removed in 3.0. Use "Symfony\Component\Console\Helper\ProgressBar" instead.', E_USER_DEPRECATED);
} elseif ('table' === $name && $this->helpers[$name] instanceof TableHelper) {
@trigger_error('"Symfony\Component\Console\Helper\TableHelper" is deprecated since Symfony 2.5 and will be removed in 3.0. Use "Symfony\Component\Console\Helper\Table" instead.', E_USER_DEPRECATED);
}

return $this->helpers[$name];
}

public function setCommand(Command $command = null)
{
$this->command = $command;
}






public function getCommand()
{
return $this->command;
}




public function getIterator()
{
return new \ArrayIterator($this->helpers);
}
}
