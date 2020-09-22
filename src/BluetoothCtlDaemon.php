<?php
namespace lecodeurdudimanche\PHPBluetooth;

use lecodeurdudimanche\UnixStream\IOException;
use lecodeurdudimanche\UnixStream\{Message, UnixStream, UnixStreamServer};

class BluetoothCtlDaemon {

    private $listeningSocket, $streams;

    public function __construct()
    {
        $this->listeningSocket = new UnixStreamServer(self::getSocketFile(), new BTMessageSerializer);
        $this->streams = [];
    }

    public static function getSocketFile() : string
    {
        return "/tmp/php-bluetooth/socket";
    }

    public function run()
    {
        $btInfo = new BluetoothInfo;
        $command = new Command("bluetoothctl");

        $command->launch();
        $continue = true;
        //Do stream connection and reconnection

        while ($command->isRunning() && $continue)
        {
            $this->addNewConnections();

            while ($line = $command->getNextLine()) {
                //echo "New line : $line\n";
                $response = $this->interactWithBtCtl($line, $btInfo);
                // Write $line as log

                if ($response)
                    $command->writeString($response);
            }

            while ($btInfo->isListening() && $data = $this->getNextCommand())
            {
                $message = $data['message'];
                //echo "new command : " . $message->getType() . "\n";
                switch($message->getType())
                {
                case MessageType::QUERY:
                    $data['stream']->write(new Message(MessageType::BTINFO, $btInfo));
                    break;
                case MessageType::COMMAND:
                    $command->writeString($message->getData());
                    break;
                case MessageType::CUSTOM_COMMAND:
                    $this->doCommand($command, $btInfo, $message->getData());
                    break;
                case MessageType::KILL:
                    $continue = false;
                    break;
                }
            }

            usleep(50000);
        }

        $command->close();

        foreach ($this->streams as $stream)
            $stream->close();
        $this->listeningSocket->close();

        unlink(self::getSocketFile());
    }

    // Inter process communciation
    private function addNewConnections() : void
    {
        while ($stream = $this->listeningSocket->accept(false))
        {
            //echo "New connection !\n";
            $this->streams[] = $stream;
        }
    }

    private function getNextCommand() : ?array
    {
        foreach($this->streams as $key => $stream)
        {
            try {
                $message = $stream->readNext([MessageType::COMMAND, MessageType::CUSTOM_COMMAND, MessageType::KILL, MessageType::QUERY], false);
                if ($message)
                    return ["message" => $message, "stream" => &$stream];

            } catch(IOException $e)
            {
                //echo "Dropped connection\n";
                $stream->close();
                unset($this->streams[$key]);
            }
        }
        return null;
    }

    // Bluetoothctl communication handling
    private function doCommand(Command $command, BluetoothInfo &$btInfo, string $cmd) : void
    {
        $MACRegex = "(?:[A-Z0-9]{2}:?){6}";
        $deviceRegex = "Device ($MACRegex) (.*)";

        switch($cmd) {
        case "clear-devices":
            $btInfo->clearDevices();
            break;
        case 'refresh-devices':
            $command->writeString("devices");
            $btInfo->setListening(false);

            $devicesMAC =  [];

            //TODO: fctorise this code
            //Gather MAC adress of all devices
            while (! $btInfo->isListening())
            {
                while (! $line = $command->getNextLine()) usleep(1000);
            //    echo $line;

                if (preg_match("/\[.*\].*# $/", $line))
                    $btInfo->setListening(true);
                else if (preg_match("/$deviceRegex/", $line, $matches)) {
                    $devicesMAC[] = $matches[1];
                }
            }

            //Gather info about each device
            $props = ["Name", /*"Alias",*/ "Class", /*"Icon",*/ "Paired", "Trusted", "Blocked", "Connected", "RSSI"];
            foreach ($devicesMAC as $mac)
            {
                $btInfo->setListening(false);
                $command->writeString("info $mac");
                $device = $btInfo->getOrAddDevice($mac);

                // Reset device
                $device->setAvailable(false);

                while (! $btInfo->isListening())
                {
                    while (! $line = $command->getNextLine()) usleep(1000);
                    //echo $line;

                    if (preg_match("/\[.*\].*# $/", $line))
                        $btInfo->setListening(true);
                    else {
                        foreach ($props as $prop)
                        {
                            if (preg_match("/^\t$prop: (.*)/", $line, $matches)) {
                                $value = $matches[1];
                                if ($prop == "Paired" || $prop == "Connected" || $prop == "Trusted" || $prop == "Blocked")
                                    $value = $value == "yes";
                                else if ($prop == "RSSI")
                                    $value = intval($value);
                                else if ($prop == "Class")
                                    $value = intval($value, 16);

                            //    echo "$prop : $value";

                                $device->{"set$prop"}($value);
                                //Si on a le RSSI alors c'est que le periph est en range
                                if ($prop == "RSSI")
                                    $device->setAvailable(true);
                            }
                        }
                    }
                }
            }
            break;
        }
    }

    private function interactWithBtCtl(string $message, BluetoothInfo &$btInfo) : string
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
}
