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

    /**
     * Handles standard PHP errors
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Respect the @ suppression operator
        if (!(error_reporting() & $errno)) {
            return true;
        }

        $this->reportError($errno, $errstr, $errfile, $errline);
        return true;
    }

    /**
     * Handles uncaught Exceptions and Throwable Errors (PHP 7+)
     */
    public function handleException(Throwable $e): void {
        $this->reportError(
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
        );
    }

    /**
     * Handles Fatal errors like E_PARSE or E_COMPILE_ERROR
     */
    public function handleShutdown(): void {
        $error = error_get_last();

        if ($error !== null && $this->isFatalError($error['type'])) {
            $this->reportError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
            );
        }
    }

    private function isFatalError(int $type): bool {
        $fatals = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR];
        return in_array($type, $fatals, true);
    }

    /**
     * The unified output method
     */
    private function reportError($errno, string $errstr, string $errfile, int $errline): void {
        if (class_exists('PDODB')) {
            try {
                PDODB::rollbackAll();
            } catch (Throwable $e) {
                error_log('Global rollback failed: ' . $e->getMessage());
            }
        }
        // Log technical details for the admin
        error_log("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}");

        // Clean any output currently in the buffer to prevent "dirty" JSON
        if (ob_get_length()) {
            ob_clean();
        }

        // Set headers for AJAX/JSON
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'error' => 'Fehler aufgetreten. Bitte dem Administrator mitteilen',
            'result' => ''
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        exit;
    }
}
