<?php
namespace lecodeurdudimanche\PHPBluetooth;

use parallel\{Channel, Runtime, Events, Events\Event\Type};

class Manager {

    protected $parallel;
    protected $channelData, $channelCommand;
    protected $btInfo;


    //Bootstrap functions
    public function __construct(bool $discoverable, bool $pairable)
    {
        $this->btInfo = new BluetoothInfo;
        $this->btInfo->setDiscoverable($discoverable);
        $this->btInfo->setPairable($pairable);

        $this->ensureBluetoothdIsRunning();
        $this->launchParallelThread($discoverable, $pairable);

        $this->refreshDevicesList();

        $this->powerOn();
        $this->setDefaultAgent();

        $this->setDiscoverable($discoverable);
        $this->setPairable($pairable);

    }

    public function ensureBluetoothdIsRunning(): void
    {
        $maxTries = 3;
        for ($i = 1; $i <= $maxTries && !$this->checkBluetoothdStatus(); $i++)
        {
            echo "Starting bluetoothd service (try $i/$maxTries)...";
            (new Command("systemctl start bluetooth"))->execute();
            sleep(1);
        }
        if ($i == $maxTries)
            throw new Exceptions\ProcessException("bluetoothd", "Cannot lauch bluetooth service");
    }

    private function getAutoloader() : string
    {
        $possiblePaths = ["vendor/autoload.php", "../autoload.php"];
        foreach($possiblePaths as $autoloader)
        {
            if (is_file($autoloader))
                return $autoloader;
        }
        return false;
    }

    private function launchParallelThread() : void
    {
        $this->channelData = new Channel(512);
        $this->channelCommand = new Channel(512);
        $this->channelLog = new Channel(512);

        $runtime = new Runtime($this->getAutoloader());
        $this->parallel = $runtime->run(function(Channel $logChannel, Channel $dataChannel, Channel $commands) {

            $messagesEvents = new Events;
            $messagesEvents->addChannel($commands);
            $messagesEvents->setBlocking(false);

            $btInfo = $dataChannel->recv();

            $command = new Command("bluetoothctl");

            $command->launch();
            $continue = true;

            while ($command->isRunning() && $continue)
            {
                while ($line = $command->getNextLine()) {
                    $response = Manager::interactWithBtCtl($line, $btInfo);
                    $logChannel->send($line);

                    if ($response)
                        $command->writeString($response);
                }

                if ($btInfo->needResend())
                    $dataChannel->send($btInfo);

                while ($btInfo->isListening() && $event = $messagesEvents->poll())
                {
                    $messagesEvents->addChannel($event->object);
                    if($event->type == Type::Read)
                    {
                        $cmd = $event->value["cmd"];
                        if ($event->value["isCustom"])
                            Manager::doCommand($command, $btInfo, $cmd);
                        else
                            $command->writeString($cmd);
                    }
                    else if($event->type == Type::Close)
                        $continue = false;
                }

                usleep(50000);
            }

            $command->close();

            return $data;

        }, [$this->channelLog, $this->channelData, $this->channelCommand]);
        $this->channelData->send($this->btInfo);
        //TODO: Do a proper sync
        usleep(100000);
    }

    public function checkBluetoothdStatus(): bool
    {
        $status = (new Command("systemctl is-active bluetooth"))->execute()["out"];
        return strpos($status, "active") === 0;

    }

    //API Functions
    public function updateBluetoothInfo() : bool
    {
        $changed = false;
        $new = ParallelUtils::getLastEvent($this->channelData);
        if ($new)
            $this->btInfo = $new;
        return $new !== null;
    }

    public function getBluetoothInfo() : BluetoothInfo
    {
        return $this->btInfo;
    }

    public function consumeLogOutput() : array
    {
        $data = [];
        $events = new Events;
        $events->setBlocking(false);
        $events->addChannel($this->channelLog);
        $events->addFuture("bluetoothctl", $this->parallel);

        while ($event = $events->poll())
        {
            if ($event->type == Type::Close || $event->type == Type::Kill || $event->type == Type::Cancel)
                break;
            else if ($event->type == Type::Error)
                throw $event->value;
            else {
                $events->addChannel($this->channelLog);
                $data[] = $event->value;
            }
        }
        return $data;
    }

    public function setDiscoverable(bool $discoverable = true) : void
    {
        $this->send("discoverable " . $this->toOnOff($discoverable));
    }

    public function scanDevices($status = true) : void
    {
        $this->send("scan " . $this->toOnOff($status));
    }

    public function refreshDevicesList(): void
    {
        $this->send("clear-devices", true);
        $this->send("add-connected-devices", true);
        $this->send("add-paired-devices", true);
        $this->send("add-scanned-devices", true);
    }

    public function pairDevice(Device $device) : void
    {
        $this->send("pair $device->mac");
    }

    public function unpairDevice(Device $device) : void
    {
        $this->send("remove $device->mac");
    }

    public function blockDevice(Device $device, bool $block = true)
    {
        $this->send(($block ? "block" : "unblock") . " $device->mac");
    }

    public function trustDevice(Device $device, bool $trust = true)
    {
        $this->send(($trust ? "trust" : "untrust") . " $device->mac");
    }

    public function connect(Device $device, bool $connect = true)
    {
        $this->send(($connect ? "connect" : "disconnect") . " $device->mac");
    }

    public function setPairable(bool $pairable = true) : void
    {
        $this->send("pairable " . $this->toOnOff($pairable));
    }

    public function send(string $message, bool $isCustomMessage = false) : void
    {
        $this->channelCommand->send(["isCustom" => $isCustomMessage, "cmd" => $message]);
    }

    public function powerOn() : void
    {
        $this->send("power on");
    }

    public function setDefaultAgent() : void
    {
        $this->send("agent on");
        $this->send("default-agent");
    }


    // Communication handling
    public static function doCommand(Command $command, BluetoothInfo &$btInfo, string $cmd) : void
    {
        $MACRegex = "(?:[A-Z0-9]{2}:?){6}";
        $deviceRegex = "Device ($MACRegex) (.*)";

        switch($cmd) {
        case "clear-devices":
            $btInfo->clearDevices();
            break;
        case "add-scanned-devices":
            $btInfo->resetAvailableStatus();
        default:
            $btCommands = [
                "add-connected-devices" => "info",
                "add-paired-devices" => "paired-devices",
                "add-scanned-devices" => "devices"
            ];

            $command->writeString($btCommands[$cmd]);
            $btInfo->setListening(false);

            while (! $btInfo->isListening())
            {
                while (! $line = $command->getNextLine()) usleep(1000);
            //    echo $line;

                if (preg_match("/\[.*\].*# $/", $line))
                    $btInfo->setListening(true);
                else if (preg_match("/$deviceRegex/", $line, $matches)) {
                    $device = $btInfo->getOrAddDevice($matches[1]);
                    if ($cmd == "add-connected-devices")
                        $device->setConnected(true);
                    else if ($cmd == "add-paired-devices")
                        $device->setPaired(true);
                }
            }
            break;
        }
    }

    public static function interactWithBtCtl(string $message, BluetoothInfo &$btInfo) : string
    {
        $response = "";
        $btInfo->setListening(false);

        $MACRegex = "(?:[A-Z0-9]{2}:?){6}";
        $deviceRegex = "Device ((?:[A-Z0-9]{2}:?){6}) (.*)";

        //echo "ON A LE MESSAGE\n $message\n";

        if (preg_match("/\[agent\] Authorize service/", $message))
            $response = "yes";
        elseif (preg_match("/Confirm passkey ([0-9]+)/", $message, $matches))
        {
            $passkey = intval($matches[1]);
            echo "Accepting pairing with passkey $passkey\n";
            $response = "yes";
        }
        elseif (preg_match("/Controller ($MACRegex) ((?:Pairable)|(?:Discoverable))\: ([a-z]+)/", $message, $matches))
        {
            // 1 MAC, 2 property, 3 status
            // pairable or discoverable set ?
            $btInfo->{"set$matches[2]"}($matches[3] == "yes");
        }
        elseif (preg_match("/Device ($MACRegex) Connected: ([a-z]+)/", $message, $matches))
        {
            $btInfo->getOrAddDevice($matches[1])->setConnected($matches[2] == "yes");
        }
        else if (preg_match("/\[.*\].*# $/", $message))
        {
            $btInfo->setListening(true);
        }
        else if (preg_match("/\[.*NEW.*\] $deviceRegex/", $message, $matches))
        {
            $btInfo->addDevice($matches[1]);
        }
        else if (preg_match("/\[.*DEL.*\] $deviceRegex/", $message, $matches))
        {
            $btInfo->removeDevice($matches[1]);
        }
        else if (preg_match("/Device ($MACRegex) RSSI: (-?[0-9]+)/", $message, $matches))
        {
            $btInfo->getOrAddDevice($matches[1])->setRSSI(intval($matches[2]));
        }
        else if (preg_match("/Failed to start discovery\:.*NotReady/", $message))
        {
            usleep(1000);
            $response = "scan on";
        }
        else if (preg_match("/No default controller available/", $message))
        {
            throw new Exceptions\NoBluetoothAdapter();
        }
        else {
            //var_dump($message);
            //echo "$message HAS BEEN INGORED\n";
        }
        return $response;
    }


    //Utility
    private function toOnOff(bool $value): string
    {
        return $value ? "on" : "off";
    }

}
