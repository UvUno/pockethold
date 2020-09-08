<?php










namespace Symfony\Component\Console\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;








class ConsoleLogger extends AbstractLogger
{
const INFO = 'info';
const ERROR = 'error';

private $output;
private $verbosityLevelMap = array(
LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
);
private $formatLevelMap = array(
LogLevel::EMERGENCY => self::ERROR,
LogLevel::ALERT => self::ERROR,
LogLevel::CRITICAL => self::ERROR,
LogLevel::ERROR => self::ERROR,
LogLevel::WARNING => self::INFO,
LogLevel::NOTICE => self::INFO,
LogLevel::INFO => self::INFO,
LogLevel::DEBUG => self::INFO,
);

public function __construct(OutputInterface $output, array $verbosityLevelMap = array(), array $formatLevelMap = array())
{
$this->output = $output;
$this->verbosityLevelMap = $verbosityLevelMap + $this->verbosityLevelMap;
$this->formatLevelMap = $formatLevelMap + $this->formatLevelMap;
}




public function log($level, $message, array $context = array())
{
if (!isset($this->verbosityLevelMap[$level])) {
throw new InvalidArgumentException(sprintf('The log level "%s" does not exist.', $level));
}


 if (self::ERROR === $this->formatLevelMap[$level] && $this->output instanceof ConsoleOutputInterface) {
$output = $this->output->getErrorOutput();
} else {
$output = $this->output;
}

if ($output->getVerbosity() >= $this->verbosityLevelMap[$level]) {
$output->writeln(sprintf('<%1$s>[%2$s] %3$s</%1$s>', $this->formatLevelMap[$level], $level, $this->interpolate($message, $context)));
}
}











private function interpolate($message, array $context)
{

 $replace = array();
foreach ($context as $key => $val) {
if (!\is_array($val) && (!\is_object($val) || method_exists($val, '__toString'))) {
$replace[sprintf('{%s}', $key)] = $val;
}
}


 return strtr($message, $replace);
}
}
