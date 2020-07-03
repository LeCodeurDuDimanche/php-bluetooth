<?php
namespace lecodeurdudimanche\PHPBluetooth\Exceptions;

class NoBluetoothAdapter extends \Exception {
    public function __construct()
    {
        parent::__construct("There is no bluetooth adapter available");
    }
}
