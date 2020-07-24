<?php
namespace lecodeurdudimanche\PHPBluetooth;

class Device implements \JsonSerializable{

    private $mac;
    private $rssi;
    private $name, $alias;
    private $paired, $connected, $available;

    public function __construct(string $mac)
    {
        $this->mac = $mac;
    }

    //TODO: check mac is present, check data types
    public static function fromArray(array $data) : Device
    {
        $instance = new Device($data['mac']);
        foreach($data as $property => $value)
        {
            if (property_exists($instance, $property))
                $instance->$property = $value;
        }
        return $instance;
    }

    public function jsonSerialize() : array
    {
        return get_object_vars($this);
    }

    public function setConnected(bool $status = true) : void
    {
        $this->connected = $status;
    }

    public function setAvailable(bool $status = true) : void
    {
        $this->available = $status;
    }

    public function setPaired(bool $status = true) : void
    {
        $this->paired = $status;
    }

    public function setRSSI(int $rssi) : void
    {
        $this->rssi = $rssi;
    }

    public function setName(string $name, ?string $alias) : void
    {
        $this->name = $name;
        $this->alias = $alias;
    }

    public function __get(string $key)
    {
        if (\property_exists($this, $key))
            return $this->$key;
    }

    public function __toString() : string
    {
        $desc = $this->mac;
        if ($this->name)
        {
            $desc .= " ($this->name";
            if ($this->alias)
                $desc .= " / $this->alias";
            $desc .= ")";
        }
        if ($this->paired)
            $desc .= ", paired";
        if ($this->connected)
            $desc .= " and connected";
        if ($this->rssi)
            $desc .= ", RSSI = $this->rssi dBm";
        return $desc;
    }

}
