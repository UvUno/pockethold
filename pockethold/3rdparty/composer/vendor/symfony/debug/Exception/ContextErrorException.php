<?php










namespace Symfony\Component\Debug\Exception;






class ContextErrorException extends \ErrorException
{
private $context = array();

public function __construct($message, $code, $severity, $filename, $lineno, $context = array())
{
parent::__construct($message, $code, $severity, $filename, $lineno);
$this->context = $context;
}




public function getContext()
{
return $this->context;
}
}
