<?php










namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;






class ProcessHelper extends Helper
{












public function run(OutputInterface $output, $cmd, $error = null, $callback = null, $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE)
{
if ($output instanceof ConsoleOutputInterface) {
$output = $output->getErrorOutput();
}

$formatter = $this->getHelperSet()->get('debug_formatter');

if (is_array($cmd)) {
$process = ProcessBuilder::create($cmd)->getProcess();
} elseif ($cmd instanceof Process) {
$process = $cmd;
} else {
$process = new Process($cmd);
}

if ($verbosity <= $output->getVerbosity()) {
$output->write($formatter->start(spl_object_hash($process), $this->escapeString($process->getCommandLine())));
}

if ($output->isDebug()) {
$callback = $this->wrapCallback($output, $process, $callback);
}

$process->run($callback);

if ($verbosity <= $output->getVerbosity()) {
$message = $process->isSuccessful() ? 'Command ran successfully' : sprintf('%s Command did not run successfully', $process->getExitCode());
$output->write($formatter->stop(spl_object_hash($process), $message, $process->isSuccessful()));
}

if (!$process->isSuccessful() && null !== $error) {
$output->writeln(sprintf('<error>%s</error>', $this->escapeString($error)));
}

return $process;
}



















public function mustRun(OutputInterface $output, $cmd, $error = null, $callback = null)
{
$process = $this->run($output, $cmd, $error, $callback);

if (!$process->isSuccessful()) {
throw new ProcessFailedException($process);
}

return $process;
}










public function wrapCallback(OutputInterface $output, Process $process, $callback = null)
{
if ($output instanceof ConsoleOutputInterface) {
$output = $output->getErrorOutput();
}

$formatter = $this->getHelperSet()->get('debug_formatter');

$that = $this;

return function ($type, $buffer) use ($output, $process, $callback, $formatter, $that) {
$output->write($formatter->progress(spl_object_hash($process), $that->escapeString($buffer), Process::ERR === $type));

if (null !== $callback) {
call_user_func($callback, $type, $buffer);
}
};
}






public function escapeString($str)
{
return str_replace('<', '\\<', $str);
}




public function getName()
{
return 'process';
}
}
