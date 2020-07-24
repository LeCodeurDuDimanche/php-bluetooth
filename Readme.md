# PHP Bluetooth
## A package to manage bluetooth connexions on Linux using PHP
This package relies on the Bluez stack, using the `bluetoothctl` tool.

*This package is in early development stage*

## Requirements

This library relies on the BlueZ stack, so you need to install `bluez` and `bluez-tools` packages.
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
     - Add detection of discoverable and pairable status at startup
     - Add support to exclude unscanned paired devices from scanned devices list
     - Add detection of trusted and blocked devices
     - Add I/O exception handling

## License
MIT License
