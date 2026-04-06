<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// 1. Setting the Title (Merged Cells)
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'امتحانات الفصل الأول - الدورة الأولى - 2025/2026');
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

// 2. Formatting the Headers (Row 2)
$headers = ['عدد اللجان', 'رقم المقرر', 'اللغة', 'اسم المقرر', 'اسم الأستاذ الثلاثي', 'الملف'];
$sheet->fromArray($headers, NULL, 'A2');

// Style the header row
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD']
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ]
];
$sheet->getStyle('A2:F2')->applyFromArray($headerStyle);

// 3. Adding Data (Example based on your first image)
$data = [
    ['2', 'S1101', 'F', 'Statistique', 'أستاذ 1', '1234'],
    ['', 'S1101', 'A', 'Statistique', '', ''], // Empty cells for visual grouping
    ['3', 'S2250', 'F/A', 'Calcul des Probabilités', 'أستاذ 2', '1234'],
];

$sheet->fromArray($data, NULL, 'A3');

// 4. Mimicking the Second Image (Green Styling)
// Let's color a specific range green like your second image
$sheet->getStyle('A5:F10')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('C6EFCE');

// 5. Adjust Column Widths
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// 6. Output the file to the browser
$writer = new Xlsx($spreadsheet);
$fileName = 'schedule_2026.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
$writer->save('php://output');
exit;
?>