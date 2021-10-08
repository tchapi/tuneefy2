<?php

declare(strict_types=1);

namespace Helper;

trait OutputAwareTrait
{

  public function __construct()
  {
    $this->output = new \Codeception\Lib\Console\Output([]);
  }

  private function write(string $text)
  {
    $this->output->writeln($text);
  }
}