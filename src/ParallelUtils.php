<?php
namespace lecodeurdudimanche\PHPBluetooth;

use parallel\{Channel, Events};
use parallel\Events\Event\Type;

class ParallelUtils {

    public static function getLastEvent(Channel $channel)
    {
        $events = new Events;
        $events->addChannel($channel);
        $events->setBlocking(false);

        $data = null;

        while($event = $events->poll())
        {
            if ($event->type == Type::Error)
                throw $event->value;
            else if ($event->type == Type::Read || $event->type == Type::Write)
                $data = $event->value;
            else
                break;
        }
        return $data;
    }
}
