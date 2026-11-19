<?php

require __DIR__.'/../../vendor/autoload.php';

$reader = new \OpenSpout\Reader\XLSX\Reader;
$reader->open($argv[1]);
$sheets = [];
try {
    foreach ($reader->getSheetIterator() as $sheet) {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) $rows[] = $row->toArray();
        $sheets[$sheet->getName()] = $rows;
    }
} finally {
    $reader->close();
}
echo json_encode($sheets, JSON_THROW_ON_ERROR);
