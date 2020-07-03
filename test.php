<?php
    use lecodeurdudimanche\PHPBluetooth\Manager;

    require("vendor/autoload.php");


    $manager = new Manager(true, true);

    $manager->scanDevices();

    while (true)
    {
        $data = $manager->consumeLogOutput();
        foreach($data as $line) echo($line);

        $updated = $manager->updateBluetoothInfo();
        if ($updated) echo("\n####### DATA #######\n" . $manager->getBluetoothInfo() . "#############\n\n");

        usleep(1000);
    }
