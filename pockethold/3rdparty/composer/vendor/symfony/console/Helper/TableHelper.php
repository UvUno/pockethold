<?php










namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;










class TableHelper extends Helper
{
const LAYOUT_DEFAULT = 0;
const LAYOUT_BORDERLESS = 1;
const LAYOUT_COMPACT = 2;

private $table;

public function __construct($triggerDeprecationError = true)
{
if ($triggerDeprecationError) {
@trigger_error('The '.__CLASS__.' class is deprecated since Symfony 2.5 and will be removed in 3.0. Use the Symfony\Component\Console\Helper\Table class instead.', E_USER_DEPRECATED);
}

$this->table = new Table(new NullOutput());
}










public function setLayout($layout)
{
switch ($layout) {
case self::LAYOUT_BORDERLESS:
$this->table->setStyle('borderless');
break;

case self::LAYOUT_COMPACT:
$this->table->setStyle('compact');
break;

case self::LAYOUT_DEFAULT:
$this->table->setStyle('default');
break;

default:
throw new InvalidArgumentException(sprintf('Invalid table layout "%s".', $layout));
}

return $this;
}

public function setHeaders(array $headers)
{
$this->table->setHeaders($headers);

return $this;
}

public function setRows(array $rows)
{
$this->table->setRows($rows);

return $this;
}

public function addRows(array $rows)
{
$this->table->addRows($rows);

return $this;
}

public function addRow(array $row)
{
$this->table->addRow($row);

return $this;
}

public function setRow($column, array $row)
{
$this->table->setRow($column, $row);

return $this;
}








public function setPaddingChar($paddingChar)
{
$this->table->getStyle()->setPaddingChar($paddingChar);

return $this;
}








public function setHorizontalBorderChar($horizontalBorderChar)
{
$this->table->getStyle()->setHorizontalBorderChar($horizontalBorderChar);

return $this;
}








public function setVerticalBorderChar($verticalBorderChar)
{
$this->table->getStyle()->setVerticalBorderChar($verticalBorderChar);

return $this;
}








public function setCrossingChar($crossingChar)
{
$this->table->getStyle()->setCrossingChar($crossingChar);

return $this;
}








public function setCellHeaderFormat($cellHeaderFormat)
{
$this->table->getStyle()->setCellHeaderFormat($cellHeaderFormat);

return $this;
}








public function setCellRowFormat($cellRowFormat)
{
$this->table->getStyle()->setCellHeaderFormat($cellRowFormat);

return $this;
}








public function setCellRowContentFormat($cellRowContentFormat)
{
$this->table->getStyle()->setCellRowContentFormat($cellRowContentFormat);

return $this;
}








public function setBorderFormat($borderFormat)
{
$this->table->getStyle()->setBorderFormat($borderFormat);

return $this;
}








public function setPadType($padType)
{
$this->table->getStyle()->setPadType($padType);

return $this;
}













public function render(OutputInterface $output)
{
$p = new \ReflectionProperty($this->table, 'output');
$p->setAccessible(true);
$p->setValue($this->table, $output);

$this->table->render();
}




public function getName()
{
return 'table';
}
}
