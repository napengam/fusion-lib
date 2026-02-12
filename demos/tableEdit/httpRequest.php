<?php

trait httpRequest {

    public $param;

    /**
     * Reads and validates HTTP requests for GET, POST, PUT, DELETE.
     *
     * @param array $allowedMethods List of allowed request methods.
     * @return object Decoded request data merged with defaults.
     */
    public function readRequest(array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE']) {
        $this->param = (object) ['error' => '', 'result' => ''];

        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, $allowedMethods, true)) {
            http_response_code(405); // Method Not Allowed
            $this->param->error = "Method $method not allowed.";
            echo $this->closeRequest($this->param);
            exit;
        }

        $data = [];

        if ($method === 'GET') {
            $data = $_GET;
        } else {
            // Check Content-Type for JSON
            if (!empty($_SERVER['CONTENT_TYPE']) &&
                stripos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
                http_response_code(415); // Unsupported Media Type
                $this->param->error = 'Invalid content type. Expected application/json.';
                echo $this->closeRequest($this->param);
                exit;
            }

            $json = file_get_contents('php://input');
            if ($json === '') {
                http_response_code(400);
                $this->param->error = 'JSON data is empty.';
                echo $this->closeRequest($this->param);
                exit;
            }

            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                $this->param->error = 'Invalid JSON: ' . json_last_error_msg();
                echo $this->closeRequest($this->param);
                exit;
            }
        }

        // Merge into response object
        $this->param = (object) array_merge((array) $this->param, $data);

        // CSRF check (typically only for POST in browser contexts)
        if ($method === 'POST' && isset($this->param->csrf)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['csrf_token']) || $this->param->csrf !== $_SESSION['csrf_token']) {
                http_response_code(403);
                $this->param->error = 'Invalid CSRF token.';
                echo $this->closeRequest($this->param);
                exit;
            }
        }

        return $this->param;
    }

    /**
     * Returns a JSON response with guaranteed fields.
     */
    public function closeRequest($param) {
        if (!isset($param->error)) {
            $param->error = '';
        }
        if (!isset($param->result)) {
            $param->result = '';
        }
        $param->rhash = hash('sha256', $param->result);
        $param->ehash = hash('sha256', $param->error);
        unset($_POST, $_GET);
        return json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    }
}
