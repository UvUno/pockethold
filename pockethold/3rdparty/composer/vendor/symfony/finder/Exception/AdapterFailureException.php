<?php










namespace Symfony\Component\Finder\Exception;

@trigger_error('The '.__NAMESPACE__.'\AdapterFailureException class is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Finder\Adapter\AdapterInterface;








class AdapterFailureException extends \RuntimeException implements ExceptionInterface
{
private $adapter;






public function __construct(AdapterInterface $adapter, $message = null, \Exception $previous = null)
{
$this->adapter = $adapter;
parent::__construct($message ?: 'Search failed with "'.$adapter->getName().'" adapter.', $previous);
}




public function getAdapter()
{
return $this->adapter;
}
}
