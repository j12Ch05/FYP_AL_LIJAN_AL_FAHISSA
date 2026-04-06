<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// This checks if the button from your other file was clicked
if (isset($_POST['exportExcel'])) {
    
    // Clear any previous output buffers to prevent file corruption
    if (ob_get_contents()) ob_end_clean();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);

    // Header
    $sheet->setCellValue('A1', 'جدول البيانات المستخرج');
    $sheet->mergeCells('A1:D1');

    // Sample Data (Replace this with your SQL Fetching logic)
    $data = [
        ['رقم المقرر', 'المادة', 'الأستاذ'],
        ['S1101', 'Statistique', 'Prof 1'],
        ['I2201', 'Web Dev', 'Prof 2'],
    ];
    $sheet->fromArray($data, NULL, 'A2');

    // Prepare the download
    $fileName = "Export_" . date('Y-m-d_H-i') . ".xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Stop execution so the rest of the HTML doesn't get inside the Excel file
    exit;
}