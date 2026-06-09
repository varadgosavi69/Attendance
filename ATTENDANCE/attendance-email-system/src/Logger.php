<?php

class Logger
{
    private $logFile;

    public function __construct()
    {
        $date = date('Y-m-d');
        $this->logFile = __DIR__ . "/../logs/attendance_{$date}.log";
    }

    public function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        // Ensure logs directory exists
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }

        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
    }

    public function info($message)
    {
        $this->log($message, 'INFO');
    }

    public function error($message)
    {
        $this->log($message, 'ERROR');
    }

    public function success($message)
    {
        $this->log($message, 'SUCCESS');
    }
}
?>