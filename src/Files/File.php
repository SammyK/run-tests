<?php

namespace RunTests\Files;

use Generator;

class File
{
    private $path;
    private $mode;
    private $fd;

    public function __construct(string $path, string $mode = 'r')
    {
        $this->path = $path;
        $this->mode = $mode;
    }

    public function open(): void
    {
        $this->fd = fopen($this->path, $this->mode);
    }

    public function line(): Generator
    {
        while (false !== $line = fgets($this->fd)) {
            yield $line;
        }
    }

    public function close(): bool
    {
        $successful = fclose($this->fd);
        $this->fd = null;
        return $successful;
    }

    public function __destruct()
    {
        if ($this->fd) {
            $this->close();
        }
    }
}
