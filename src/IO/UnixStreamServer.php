<?php

namespace lecodeurdudimanche\PHPBluetooth\IO;

use lecodeurdudimanche\PHPBluetooth\Exceptions\IOException;

class UnixStreamServer {

    protected $socket;

    public function __construct(string $file)
    {
        //TODO: check errors
        if (! is_dir(dirname($file)))
            mkdir(dirname($file), 0777, true);

        if (file_exists($file))
            unlink($file);

        $this->socket = @stream_socket_server("unix://$file", $errstr, $errno);
        if (! $this->socket)
            throw new IOException("Cannot create a listening socket on file $file : $errstr ($errno)");

    }

    public function accept(bool $wait = true) : ?UnixStream
    {
        $socket = @stream_socket_accept($this->socket, $wait ? -1 : 0);
        if ($socket === false)
            return null;
        return UnixStream::fromExistingSocket($socket);
    }

    public function close() : void
    {
        fclose($this->socket);
        $this->socket = null;
    }


}
