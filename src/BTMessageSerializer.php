<?php

namespace lecodeurdudimanche\PHPBluetooth;

use lecodeurdudimanche\UnixStream\{Message, JSONMessageSerializer};

class BTMessageSerializer extends JSONMessageSerializer {
    public const TYPE_COMMAND = 1, TYPE_CUSTOM_COMMAND = 2, TYPE_LOG = 3, TYPE_BTINFO = 4, TYPE_KILL = 5, TYPE_QUERY = 6;

    public function fromJSON(string $data) : ?Message
    {
        $message = parent::fromJSON($data);
        
        if ($message && $message->getType() === self::TYPE_BTINFO)
            $message = new Message(self::TYPE_BTINFO, BluetoothInfo::fromArray($message->getData()));

        return $message;
    }
}
