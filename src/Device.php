<?php
namespace lecodeurdudimanche\PHPBluetooth;

class Device implements \JsonSerializable{

    private $mac;
    private $rssi;
    private $name, $alias;
    private $class;
    private $blocked, $trusted;
    private $paired, $connected, $available;

    public const TYPE_SMARTPHONE = 0x20C, TYPE_PHONE = 0x200, TYPE_PERIPHERAL = 0x500, TYPE_SPEAKER = 0x404, TYPE_COMPUTER = 0x100, TYPE_TV = 0x430 ;

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
        $correspondances = [
            self::TYPE_SMARTPHONE => "smartphone",
            self::TYPE_PHONE => "phone",
            self::TYPE_SPEAKER => "speaker",
            self::TYPE_PERIPHERAL => "peripheral",
            self::TYPE_COMPUTER => "computer",
            self::TYPE_TV => 'tv',
        ];
        $data = get_object_vars($this);
        $data['classname'] = 'other';
        foreach($correspondances as $type => $name)
        {
            if ($this->is($type))
            {
                $data['classname'] = $name;
                break;
            }
        }
        return $data;
    }

    public function setConnected(bool $status = true) : void
    {
        $this->connected = $status;
    }

    public function setAvailable(bool $status = true) : void
    {
        if (! $status) $this->rssi = null;
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

    public function setName(string $name, ?string $alias = null) : void
    {
        $this->name = $name;
        $this->alias = $alias;
    }

    public function setTrusted(bool $trusted = true) : void
    {
        $this->trusted = $trusted;
    }

    public function setBlocked(bool $blocked = true) : void
    {
        $this->blocked = $blocked;
    }

    public function setClass(int $class) : void
    {
        $this->class = $class;
    }

    public function is(int $bitfield) : bool
    {
        return ($this->class & $bitfield) == $bitfield;
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
        if ($this->blocked)
            $desc .= ", blocked";
        if ($this->trusted)
            $desc .= ", trusted";
        if ($this->paired)
            $desc .= ", paired";
        if ($this->connected)
            $desc .= " and connected";
        if ($this->rssi)
            $desc .= ", RSSI = $this->rssi dBm";
        return $desc;
    }

}
