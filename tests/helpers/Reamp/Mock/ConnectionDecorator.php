<?php

namespace Reamp\Mock;

use Amp\Promise;
use Reamp\AbstractConnectionDecorator;

class ConnectionDecorator extends AbstractConnectionDecorator {
    public $last = [
        'write' => '', 'end'   => false
    ];

    public function send($data): Promise {
        $this->last[__FUNCTION__] = $data;

        return $this->getConnection()->send($data);
    }

    public function close(): Promise {
        $this->last[__FUNCTION__] = true;

        return $this->getConnection()->close();
    }
}
