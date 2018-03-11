<?php

namespace Reamp\Mock;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface {
    public $written;

    use LoggerTrait;

    public function log($level, $message, array $context = []) {
        $this->written[$level][] = $message;
    }
}
