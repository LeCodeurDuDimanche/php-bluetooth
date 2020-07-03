<?php
namespace lecodeurdudimanche\PHPBluetooth;

class BluetoothInfo {
    private $pairable, $discoverable;
    private $devices = [];

    // internal pour l'instant
    private $listeningToCommands = false;
    private $updated = false;


    public function setPairable(bool $status = true) : void
    {
        $this->changeProperty('pairable', $status);
    }

    public function setDiscoverable(bool $status = true) : void
    {
        $this->changeProperty('discoverable', $status);
    }

    public function setListening(bool $status = true) : void
    {
        $this->changeProperty('listeningToCommands', $status);
    }

    public function isPairable() : bool
    {
        return $this->pairable;
    }

    public function isDiscoverable() : bool
    {
        return $this->discoverable;
    }

    public function isListening() : bool
    {
        return $this->listeningToCommands;
    }

    public function needResend() : bool
    {
        if ($this->updated) {
            $this->updated = false;
            return true;
        }
        return false;
    }

    public function clearDevices() : void
    {
        $this->devices = [];
    }

    public function removeDevice(string $mac) : void
    {
        $this->devices = array_filter($this->devices, function($device) use ($mac) { return $device->mac != $mac; });
    }

    public function resetAvailableStatus() : void
    {
        $this->devices = array_filter($this->devices, function($device) { return $device->paired;});
        foreach($this->devices as &$device)
            $device->setAvailable(false);
    }

    public function getOrAddDevice(string $mac) : Device
    {
        foreach($this->devices as $device)
        {
            if ($device->mac == $mac)
                return $device;
        }

        return $this->addDevice($mac);
    }

    public function addDevice(string $mac) : Device
    {
        return $this->devices[] = new Device($mac);
    }

    public function  __toString(): string
    {
        $string = "Bluetooth controller. Pairable : " . ($this->pairable ? "yes" : "no") . ", Discoverable : " . ($this->discoverable ? "yes" : "no") . "\n";
        $string .= "Devices :\n";
        foreach($this->devices as $device)
            $string .= "\t" . $device . "\n";
        return $string;
    }

    private function changeProperty(string $property, $value)
    {
        if ($this->$property !== $value)
        {
            $this->updated = true;
            $this->$property = $value;
        }
    }
}
