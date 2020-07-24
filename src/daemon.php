<?php
    namespace lecodeurdudimanche\PHPBluetooth;

    include(__DIR__ . "/../vendor/autoload.php");
    (new BluetoothCtlDaemon)->run();
