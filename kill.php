<?php
    use lecodeurdudimanche\PHPBluetooth\Manager;

    require("vendor/autoload.php");

    $manager = new Manager(true, true);

    echo "Trying to shut down the daemon\n";
    $maxTries = 3;
    for ($i = 1; $i <= $maxTries && $manager->fetchDaemonPID(); $i++)
    {
        echo "\rSending kill command, try $i/$maxTries...   ";
        $manager->killDaemon();
        sleep(1);
    }
    echo "\n";

    if ($manager->fetchDaemonPID()) {
        echo "Force killing daemon...\n";
        $manager->killDaemon();
    }
    echo "Done\n";
