<?php

class ErrorHandler {

    private static ?self $instance = null;

    private function __construct() {
        self::$instance = $this;

        // Intercept standard PHP errors (Warnings, Notices, etc.)
        set_error_handler([$this, 'handleError']);

        // Intercept Uncaught Exceptions and PHP 7/8 Error objects
        set_exception_handler([$this, 'handleException']);

        // Intercept Fatal errors that stop script execution
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public static function register(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        if (!(error_reporting() & $errno)) {
            return true;
        }

        $status = $this->extractStatus($errstr, 500);

        $this->reportError($errno, $errstr, $errfile, $errline, $status);
        return true;
    }

    public function handleException(Throwable $e): void {
        $message = $e->getMessage();
        $status = $this->extractStatus($message, 500);

        $this->reportError(
            get_class($e),
            $message,
            $e->getFile(),
            $e->getLine(),
            $status
        );
    }

    public function handleShutdown(): void {
        $error = error_get_last();

        if ($error !== null && $this->isFatalError($error['type'])) {
            $message = $error['message'];
            $status = $this->extractStatus($message, 500);

            $this->reportError(
                $error['type'],
                $message,
                $error['file'],
                $error['line'],
                $status
            );
        }
    }

    private function isFatalError(int $type): bool {
        $fatals = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR];
        return in_array($type, $fatals, true);
    }

    private function extractStatus(string &$message, int $default = 500): int {
        if (preg_match('/^\[(\d{3})\]\s*(.*)$/', $message, $matches)) {
            $message = $matches[2];
            return (int)$matches[1];
        }
        return $default;
    }

    private function reportError($errno, string $errstr, string $errfile, int $errline, int $status = 500): void {

        if (class_exists('PDODB')) {
            try {
                PDODB::rollbackAll();
            } catch (Throwable $e) {
                error_log('Global rollback failed: ' . $e->getMessage());
            }
        }

        error_log("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}");

        if (ob_get_length()) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => false,
            'error' => $status === 500
                ? 'Fehler aufgetreten. Bitte dem Administrator mitteilen'
                : $errstr
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        exit;
    }
}