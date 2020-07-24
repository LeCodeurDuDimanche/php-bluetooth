<?php
    namespace lecodeurdudimanche\PHPBluetooth;

    $autoloadLocations = ["/../vendor/autoload.php", "vendor/autoload.php", "../../../autoload.php"];
    foreach($autoloadLocations as $potentialAutoload)
    {
        $potentialAutoload = __DIR__ . "/" . $potentialAutoload;
        if (\file_exists($potentialAutoload))
        {
            include($potentialAutoload);
            break;
        }
    }
    (new BluetoothCtlDaemon)->run();
