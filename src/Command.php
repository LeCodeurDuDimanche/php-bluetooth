<?php
namespace lecodeurdudimanche\PHPBluetooth;


class Command {

    private $stdin;
    private $stdout;
    private $stderr;
    private $handle;
    private $command;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function launch() : void
    {
        $streamDescriptors = [
            array("pipe", "r"),
            array("pipe", "w"),
            array("pipe", "w")
        ];
        $this->handle = proc_open($this->command, $streamDescriptors, $pipes, NULL, NULL, ["bypass_shell" => true]);
        list($this->stdin, $this->stdout, $this->stderr) = $pipes;


        if ($this->handle === false)
            throw new Exceptions\ProcessException($this->command, "Could not launch");

        //This is can avoid deadlock on some cases (when stderr buffer is filled up before writing to stdout and vice-versa)
        stream_set_blocking($this->stdout, 0);
        stream_set_blocking($this->stderr, 0);
    }

    public function getNextLine() : ?string
    {
        $string = fgets($this->stdout, 8192);
        return $string === false ? null : $string;
    }


    public function writeString(string $str) : bool
    {
        $str .= "\n";
        return $this->write($str, strlen($str));
    }

    public function isRunning(): bool
    {
        $procInfo = proc_get_status($this->handle);
        return $procInfo["running"];
    }


    public function execute(?array $initialData = null, ?callable $processData = null, int $refreshFrequency = 25): array
    {
        $this->launch();

        $running = true;
        $data = ["out" => "", "err" => ""];

        $sleepTime = 1000000 / $refreshFrequency;

        if ($initialData)
        {
            foreach ($initialData as $string)
                $this->writeString($string);
        }

        while ($running === true)
        {
            $line = fgets($this->stdout, 8192);
            if ($line && $processData)
            {
                $response = $processData($line);
                $this->writeString($response);
                echo $line;
            }

            $data["out"] .= $line;
            $data["err"] .= fread($this->stderr, 8192);

            $running = $this->isRunning();

            usleep($sleepTime);
        }

        $this->close();

        return $data;
    }

    public function close(): int
    {
        $this->closeStream($this->stdin);
        $this->closeStream($this->stdout);
        $this->closeStream($this->stderr);
        return proc_close($this->handle);
    }

    public function closeStdin(): void
    {
        $this->closeStream($this->stdin);
    }

    private function write($data, int $len) : bool
    {
        $total = 0;
        do
        {
            $res = fwrite($this->stdin, substr($data, $total));
        } while($res && $total += $res < $len);
        return $total === $len;
    }

    private function closeStream(&$stream) : void
    {
        if ($stream !== NULL)
        {
            fclose($stream);
            $stream = NULL;
        }
    }
}
