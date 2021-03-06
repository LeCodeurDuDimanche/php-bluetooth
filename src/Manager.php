<?php
namespace lecodeurdudimanche\PHPBluetooth;

use lecodeurdudimanche\UnixStream\{UnixStream, Message};
use lecodeurdudimanche\Processes\Command;

class Manager {

    protected $stream;
    protected $btInfo;
    protected $daemonPID;


    //Bootstrap functions
    public function __construct(bool $discoverable, bool $pairable)
    {
        $this->btInfo = new BluetoothInfo;
        $this->btInfo->setDiscoverable($discoverable);
        $this->btInfo->setPairable($pairable);

    //    echo "Starting btctl daemon...\n";
        $this->ensureBluetoothdIsRunning();
        //echo "Starting control daemon...\n";
        $this->ensureCtlDaemonIsRunning();

    //    echo "Init daemon communication...\n";
        $this->initDaemonCommunication();


    //    echo "Sending sartup routine instructions...\n";
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
            // echo "\rStarting bluetoothd service (try $i/$maxTries)...";
            (new Command("systemctl start bluetooth"))->execute();
            sleep(1);
        }
        if ($i == $maxTries)
            throw new Exceptions\ProcessException("bluetoothd", "Cannot lauch bluetooth service");
    }

    private function ensureCtlDaemonIsRunning() : void
    {
        $daemonFile = __DIR__ . "/daemon.php";
        while (! $this->daemonPID = $this->fetchDaemonPID())
        {
             //echo "Starting control daemon...\n";

            if (!is_dir("/tmp/php-bluetooth"))
                mkdir("/tmp/php-bluetooth");

            $output = (new Command("nohup php $daemonFile 1> /tmp/php-bluetooth/daemon-out 2> /tmp/php-bluetooth/daemon-err &"))->execute();
            if ($output['err'])
                throw new \Exception("Failed to start daemon : $output[err]");
            sleep(1);
        }
        // echo "Daemon PID is $this->daemonPID\n";
    }

    private function initDaemonCommunication() : void
    {
        // echo "Connecting....";
        $this->stream = new UnixStream(BluetoothCtlDaemon::getSocketFile(), new BTMessageSerializer);
        // echo "\rConnected !    \n";
    }

    public function checkBluetoothdStatus(): bool
    {
        $status = (new Command("systemctl is-active bluetooth"))->execute()["out"];
        return strpos($status, "active") === 0;

    }

    //API Functions
    public function fetchDaemonPID() : int
    {
        $command = new Command("ps ax|grep -E \"php .*php-bluetooth/src/daemon.php\"|grep -v 'grep'");
        $result = $command->execute();
        return intval($result["out"]);
    }

    public function killDaemon(bool $force = false) : void
    {
        if ($force)
            (new Command("kill -9 $this->daemonPID"))->execute();
        else
            $this->stream->write(new Message(MessageType::KILL, ""));
    }

    public function updateBluetoothInfo() : void
    {
        $this->stream->write(new Message(MessageType::QUERY, ""));
        //echo "Sent Query (" . MessageType::QUERY . ")\n";
        $message = $this->stream->readNext([MessageType::BTINFO]);
        //echo "Received response\n";
        $this->btInfo = $message->getData();
    }

    public function getBluetoothInfo() : BluetoothInfo
    {
        return $this->btInfo;
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
        $this->send("refresh-devices", true);
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

    public function send(string $data, bool $isCustomMessage = false) : void
    {
        $message = new Message($isCustomMessage ? MessageType::CUSTOM_COMMAND : MessageType::COMMAND, $data);
        $this->stream->write($message);
        //echo "Message sent (" . ($isCustomMessage ? MessageType::CUSTOM_COMMAND : MessageType::COMMAND) . ")!\n";
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

    //Utility
    private function toOnOff(bool $value): string
    {
        return $value ? "on" : "off";
    }

}
