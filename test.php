<?php
    use lecodeurdudimanche\PHPBluetooth\Manager;

    require("vendor/autoload.php");

    //Create new Bluetooth manager, set discoverable and pairable to true
    $manager = new Manager(true, true);

    //Initiate device scan
    $manager->scanDevices();

    //Get paired devices, refresh btInfo until we get at least one
    do {
        $manager->updateBluetoothInfo();
        $info = $manager->getBluetoothInfo();
    } while(!$info->getPairedDevices());

    $device = $info->getPairedDevices()[0];
    /*echo "Connecting to paired device $device\n";
    $manager->connect($device);
    usleep(10000000);
    echo "Disconnecting from device\n";
    $manager->connect($device, false);
    usleep(5000000);
    echo "Blocking device\n";
    $manager->blockDevice($device);
    usleep(5000000);
    echo "Unblocking device\n";
    $manager->blockDevice($device, false);*/

    while (true)
    {
        /*$data = $manager->consumeLogOutput();
        foreach($data as $line) echo($line);*/
        $manager->updateBluetoothInfo();
        echo("\n####### DATA #######\n" . $manager->getBluetoothInfo() . "#############\n\n");

        sleep(3);
    }
