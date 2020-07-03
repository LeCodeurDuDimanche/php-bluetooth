<?php
namespace lecodeurdudimanche\PHPBluetooth\Exceptions;

class ProcessException extends \Exception {

    public function __construct(string $cmdline, string $message)
    {
        parent::__construct("$message $cmdline");
    }
}
