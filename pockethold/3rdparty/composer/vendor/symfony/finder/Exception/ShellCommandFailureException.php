<?php










namespace Symfony\Component\Finder\Exception;

@trigger_error('The '.__NAMESPACE__.'\ShellCommandFailureException class is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Finder\Adapter\AdapterInterface;
use Symfony\Component\Finder\Shell\Command;






class ShellCommandFailureException extends AdapterFailureException
{
private $command;

public function __construct(AdapterInterface $adapter, Command $command, \Exception $previous = null)
{
$this->command = $command;
parent::__construct($adapter, 'Shell command failed: "'.$command->join().'".', $previous);
}




public function getCommand()
{
return $this->command;
}
}
