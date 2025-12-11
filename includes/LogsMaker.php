<?php

namespace Includes;

class LogsMaker
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->write('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->file, "[$time] [$level] $message\n", FILE_APPEND);
    }
}