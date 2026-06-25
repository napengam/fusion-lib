<?php

class LogToFile {

    private string $logFile, $logDir;
    private string $logFilePath;
    private string $ident;
    private bool $console = false;
    private bool $logEnabled = true;
    private $fileHandle = null;
    private int $maxLines = 10000;
    private int $maxFileSize = 5 * 1024 * 1024; // 5 MB default
    private int $lineCount = 0;
    private int $pid;
    public string $error = '';

    public function __construct() {

        $lo = GetAllConfig::load()['loging'];

        $this->ident = $lo['ident'];
        $this->logFile = $lo['logfile'];
        $this->pid = getmypid();
        $this->logDir = $lo['logdir'];
        $this->logFilePath = "$this->logDir/$this->logFile";

        // Ensure directory exists or create it
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0777, true) && !is_dir($this->logDir)) {
                $this->error = "Failed to create log directory: {$this->logDir}";
            }
        }

        if (!is_writable($this->logDir)) {
            $this->error = "{$this->logDir} is not writable";
        }

        if ($this->error !== '') {
            openlog($this->ident, LOG_PID, LOG_USER);
            syslog(LOG_ERR, "Cannot access or create log directory {$this->logDir}; logging disabled");
            closelog();
            $this->logEnabled = false;
            return;
        }

        $this->openLogFile();
    }

    public function log(string $message): void {
        if ($this->console) {
            echo date('c') . "; $message\n";
        }

        if (!$this->logEnabled || !$this->fileHandle) {
            return;
        }

        // Check file size BEFORE writing
        if (file_exists($this->logFilePath) && filesize($this->logFilePath) >= $this->maxFileSize) {
            $this->rotateLogFile();
        }

        fwrite($this->fileHandle, date('r') . "; $message\n");
        $this->lineCount++;

    }

    public function close(): void {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }

    public function setEnabled(bool $enabled): void {
        if ($this->error === '') {
            $this->logEnabled = $enabled;
        }
    }

    private function openLogFile(): void {
        if (!$this->logEnabled) {
            return;
        }

        $this->fileHandle = fopen($this->logFilePath, 'a+');

        if ($this->fileHandle === false) {
            openlog($this->ident, LOG_PID, LOG_USER);
            syslog(LOG_ERR, "Cannot open log file {$this->logFilePath}; logging disabled");
            closelog();
            $this->logEnabled = false;
            return;
        }

        $this->lineCount = $this->countLines($this->logFilePath);
    }

    private function rotateLogFile(): void {
        if (!$this->fileHandle) {
            return;
        }

        fclose($this->fileHandle);
        $newFilePath = $this->getNextLogFileName();
        rename($this->logFilePath, $newFilePath);

        $this->openLogFile();
    }

    private function getNextLogFileName(): string {
        $info = pathinfo($this->logFilePath);
        $base = "{$info['dirname']}/{$info['filename']}";
        $ext = isset($info['extension']) ? ".{$info['extension']}" : '';
        $i = 1;

        while (file_exists("{$base}_{$i}{$ext}")) {
            $i++;
        }

        return "{$base}_{$i}{$ext}";
    }

    private function countLines(string $filePath): int {
        $lines = 0;
        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            $lines += substr_count(fread($handle, 8192), "\n");
        }
        fclose($handle);
        return $lines;
    }
}
