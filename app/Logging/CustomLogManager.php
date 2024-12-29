<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use File;
use Carbon\Carbon;
class CustomLogManager
{
    private $currentHandler;
    private $logger;
    private $maxSize;
    private $baseFile;
    private $logRetentionDays = 30; // Retention period for logs in days
    /**
     * Create a Monolog instance.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $this->logger = new Logger('split_daily');
        $this->maxSize = 50 * 1024 * 1024; // 1MB
        
        $this->baseFile = storage_path('logs/laravel.log');
        $this->currentHandler = $this->createInitialHandler();
        $this->logger->pushHandler($this->currentHandler);
        $this->logger->pushProcessor(function ($record) {
            $this->checkAndUpdateHandler();
            return $record;
        });
        return $this->logger;
    }

    private function checkAndUpdateHandler()
    {
        $currentFile = $this->currentHandler->getUrl();
        if (file_exists($currentFile) && filesize($currentFile) >= $this->maxSize) {
            // Create new file
            $newFile = $this->getNextLogFile($this->baseFile);
            
            // Create and set new handler
            $newHandler = new StreamHandler($newFile, Logger::DEBUG);
            $this->updateHandler($newHandler);
        }
    }

    private function updateHandler($newHandler){
        $this->logger->popHandler($this->currentHandler);
        $this->currentHandler->close();
        $this->currentHandler = $newHandler;
        $this->logger->pushHandler($this->currentHandler);
    }

    private function createInitialHandler()
    {
        $currentFile = $this->getCurrentLogFile($this->baseFile);
        return new StreamHandler($currentFile, Logger::DEBUG);
    }

    /**
     * Get the current log file, checking if one exists for the current date.
     *
     * @param string $baseFile
     * @return string
     */
    private function getCurrentLogFile(string $baseFile)
    {
        $date = date('Ymd');
        $baseName = pathinfo($baseFile, PATHINFO_FILENAME);
        $extension = pathinfo($baseFile, PATHINFO_EXTENSION);

        $pattern = sprintf('%s-%s-*.%s', $baseName, $date, $extension);
        $files = glob(storage_path('logs/') . $pattern);

        return $files ? end($files) : storage_path('logs/') . sprintf('%s-%s-01.%s', $baseName, $date, $extension);
    }

    /**
     * Get the next log file based on current date and increment.
     *
     * @param string $baseFile
     * @return string
     */
    private function getNextLogFile(string $baseFile)
    {
        $date = date('Ymd');
        $baseName = pathinfo($baseFile, PATHINFO_FILENAME);
        $extension = pathinfo($baseFile, PATHINFO_EXTENSION);
    
        $directory = storage_path('logs'); // Get the storage/logs directory
        $i = 1;
    
        do {
            $newFile = sprintf('%s/%s-%s-%02d.%s', $directory, $baseName, $date, $i, $extension);
            $i++;
        } while (file_exists($newFile));
        return $newFile; // Full path to the next log file
    }


        /**
     * Delete log files older than 30 days.
     */
    private function deleteOldLogs()
    {
        $logDirectory = storage_path('logs');
        $files = glob($logDirectory . '/*.log'); // Adjust the pattern to match your log files
        
        foreach ($files as $file) {
            $fileCreationTime = Carbon::createFromTimestamp(filemtime($file)); // Get last modified time
            $expiryDate = Carbon::now()->subDays($this->logRetentionDays); // 30 days ago

            if ($fileCreationTime->isBefore($expiryDate)) {
                // Delete the file if it's older than 30 days
                File::delete($file);
            }
        }
    }
}
