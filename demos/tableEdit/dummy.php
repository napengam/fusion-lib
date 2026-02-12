<?php

require __DIR__ . '/httpRequest.php';

class dummy {

    use httpRequest;

    function __construct() {
        $param = $this->readRequest();
        $param->result = $param->value.' ';
        if ($param->value === '?') {
            $param->error = "$param->task; Test error from backend; value is $param->value ";
        }
        echo $this->closeRequest($param);
    }
}

new dummy;
