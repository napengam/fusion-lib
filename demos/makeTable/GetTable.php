<?php

require '../../php/makeTable.php';

class GetTable {

    use httpRequest;

    function __construct() {

        $param = $this->readRequest();

        $rows = $this->fetchAddressObjects();

        $head = array_keys(get_object_vars($rows[0]));

        $n = count($rows);
        $tab = new MakeTable("Adresses($n)");

        $tab->outrow($head);
        foreach ($rows as $row) {
            $tab->outRow((array) $row);
        }
        $table = $tab->closeTable();

        $param->result = $table;
        $this->closeRequest($param);
    }

    function fetchAddressObjects(): array {
        $cities = ['Berlin', 'Hamburg', 'Munich', 'Cologne', 'Frankfurt'];
        $rows = [];

        for ($i = 1;
                $i <= 20;
                $i++) {
            $rows[] = (object) [
                        'name' => "User {$i}",
                        'street' => "Street {$i}",
                        'zip' => sprintf('%05d', 10115 + $i),
                        'city' => $cities[$i % count($cities)],
                        'country' => 'DE'
            ];
        }

        return $rows;
    }
}

$x = new GetTable();
