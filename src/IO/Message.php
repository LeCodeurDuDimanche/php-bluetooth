<?php

namespace lecodeurdudimanche\PHPBluetooth\IO;

use lecodeurdudimanche\PHPBluetooth\BluetoothInfo;

//TODO: should decouple this class from other classes (like data classes), but flemme
class Message {
    public const TYPE_COMMAND = 1, TYPE_CUSTOM_COMMAND = 2, TYPE_LOG = 3, TYPE_BTINFO = 4, TYPE_KILL = 5, TYPE_QUERY = 6;

    private $type, $data;

    public function __construct(int $type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public static function fromJSON(string $data) : ?Message
    {
        //TODO: check for incorrect properties
        $data = json_decode($data, true);

        if (!$data || !array_key_exists('type', $data) || ! array_key_exists('data', $data))
            return null;

        //echo("New message : type => $data[type]\n");
        if ($data['type'] === self::TYPE_BTINFO)
            $data['data'] = BluetoothInfo::fromArray($data['data']);

        return new Message($data['type'], $data['data']);
    }

    public function toJSON() : string
    {
        return json_encode(get_object_vars($this));
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function is(int $type) : bool
    {
        return $this->type === $type;
    }

    public function getData()
    {
        return $this->data;
    }

}
