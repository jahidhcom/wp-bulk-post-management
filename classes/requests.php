<?php

require_once 'XLSReader.php';
require_once 'XLSXReader.php';

if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $name = $file['name'];
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $filepath = $file['tmp_name'];
    $data = [];
    if ($extension == 'xlsx') {
        $xlsx = XLSXReader::parse($filepath);
        $sheets = [];
        if (count($xlsx->sheetNames()) > 0) {
            $sheet_names = $xlsx->sheetNames();
            foreach ($sheet_names as $key => $name) {
                if (count($xlsx->rows($key)) > 0) {
                    $sheets[$key] = $name;
                    $rows = $xlsx->rows($key);
                    $max = max(array_map('count', $rows));
                    $result = array_map(function ($row) use ($max) {
                        return array_pad($row, $max, '');
                    }, $rows);
                    $data[$name] = $result;
                }
            }
        }
    } else if ($extension == 'xls') {
        $xls = XLSReader::parse($filepath);
        $sheets = [];
        if (count($xls->sheets) > 0) {
            $sheet_names = $xls->sheets;
            foreach ($sheet_names as $key => $name) {
                if (count($xls->sheets[$key]['cells']) > 0) {
                    $name = $xls->boundsheets[$key]['name'];
                    $sheets[$key] = $name;
                    // $rows = $xls->sheets[$key]['cells'];                    
                    $rows = $xls->rows($key);
                    $rows = array_filter($rows, function ($row) {
                        return !empty(array_filter(array_map('trim', $row)));
                    });

                    $max = max(array_map('count', $rows));
                    $result = array_map(function ($row) use ($max) {
                        return array_pad($row, $max, '');
                    }, $rows);
                    // echo $name;
                    $data[$name] = $result;
                }
            }
        }
    } else if ($extension == 'csv') {
        $rows = [];
        $csv = fopen($filepath, "r");
        while (($row = fgetcsv($csv)) !== false) {
            $rows[] = json_encode($row);
        }
        $max = 0;
        foreach ($rows as $key => $single_row) {
            $rows[$key] = json_decode($single_row);
            if (count((array) $rows[$key]) > $max) {
                $max = count((array) $rows[$key]);
            }
        }
        $result = array_map(function ($row) use ($max) {
            return array_pad((array) $row, $max, '');
        }, $rows);
        $data['Sheet 1'] = $result;
    } else if ($extension == 'xml') {
        // $sheets = [];
        $xml = (array) simplexml_load_file($filepath);
        // $data = simplexml_load_file($filepath);
        foreach ($xml as $key => $rows) {
            $sheets[] = $key;
            $data[$key] = [];
            foreach ($rows as $row) {
                $row = (array) $row;
                unset($row['@attributes']);
                $data[$key][] = $row;
            }

        }
        foreach ($data as $key => $rows) {
            $max = max(array_map('count', $rows));
            $result = array_map(function ($row) use ($max) {
                return array_pad($row, $max, '');
            }, $rows);
            $data[$key] = $result;
        }

        // echo '<pre>';
        // print_r($data);
        // echo '</pre>';
        // die;
        // $data = 'Hello';

    }
    // $data['filetype'] = $extension;
    echo json_encode($data);
}


?>