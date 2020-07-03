# PHP Bluetooth
## A package to manage bluetooth connexions on Linux using PHP
This package relies on the Bluez stack, using the `bluetoothctl` tool.

*This package is in early development stage*

## Requirements

This library relies on the BlueZ stack, so you need to install `bluez` and `bluez-tools` packages.
This library uses `Parallel` library for multithreading, so it needs a php version with the ZTS extension built-in with paraller extension enabled. More info in the [PHP Manual](https://www.php.net/manual/en/book.parallel.php)
PHP version >= 7.2

## Installation

```
    composer require lecodeurdudimanche\php-bluetooth
```

## Basic usage

The `Manager` class is the entry point of this library.
To launch the `Manager` and set the bluetooth adapter in a discoverable state and to accept paring :
```php
    use lecodeurdudimanche\PHPBluetooth\Manager;

    $manager = new Manager($discoverable, $pairable);
```

Next you can manage the bluetooth connextion through the `Manager`, keep in mind that all calls are asynchronous :
```php
    use lecodeurdudimanche\PHPBluetooth\Device;

    $manager->scanDevices(); //Initiate device scan
    $manager->setPairable(false);
    $manager->pairDevice(new Device($mac));

    $isUpdated = $manager->updateBluetoothInfo();
    $data = $manager->getBluetoothInfo();

    foreach($data->getAvailableDevices() as $device)
    {
        if (! $device->paired)
            $manager->blockDevice($device);
    }

    // Other methods are available, see Manager class
```

## TODO
     - Add support to exclude unscanned paired devices from scanned devices list
     - Resolve hacky workaround for btInfo sync in thread launching
     - Add detection of trusted and blocked devices

## License
MIT License
