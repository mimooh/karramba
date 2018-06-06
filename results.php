<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function produce_xls($data) { 
	$spreadsheet = new Spreadsheet();
	$spreadsheet->getActiveSheet()->fromArray(
			$data,  // The data to set
			NULL,   // Array values with this value will not be set
			'A1'    // Top left coordinate of the worksheet range where
	);

	// Redirect output to a client’s web browser (Xlsx)
	header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	header("Content-Disposition: attachment;filename=karramba.xlsx");
	header("Cache-Control: max-age=0");
	header("Cache-Control: max-age=1");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: cache, must-revalidate"); // HTTP/1.1
	header("Pragma: public"); // HTTP/1.0

	$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
}

$arrayData = [
	['Q1',   12,   15,   21],
	['Q2',   56,   73,   86],
	['Q3',   52,   61,   69],
	['Q4',   30,   32,    0],
];

produce_xls($arrayData);

?>
