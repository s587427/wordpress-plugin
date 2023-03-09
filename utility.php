<?php

function readArr($arr) {
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function echoLog($data, $isDie = true) {
    if (is_array($data)) {
        echo json_encode($data);
    } else {
        echo $data;
    }
    if ($isDie) die();
}


function myVarDump($arr) {
    echo "<pre>";
    var_dump($arr);
    echo "</pre>";
}


function debug($data, $description = "") {
    if (is_array($data) || is_object($data)) {
        $data = print_r($data, true);
    }

    if (!empty($description)) {
        $description = $description . ": ";
    }
    echo date('Y-m-d H:i:s') . "====================$description" . $data . "====================" . "\n";
}
