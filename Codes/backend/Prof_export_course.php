<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

session_start();
include 'database.php';

if (isset($_POST['exportExcel'])) {
    if (!isset($_SESSION['email'])) {
        header('Location: login.php');
        exit;
    }

    $email = $_SESSION['email'];

    $sql_prof = "SELECT prof_file_nb, prof_first_name, prof_father_name, prof_last_name, prof_category, dep_id FROM professor WHERE prof_email = ?";
    $stmt_prof = mysqli_prepare($conn, $sql_prof);
    mysqli_stmt_bind_param($stmt_prof, 's', $email);
    mysqli_stmt_execute($stmt_prof);
    $result_prof = mysqli_stmt_get_result($stmt_prof);
    $professor = mysqli_fetch_assoc($result_prof);
    mysqli_stmt_close($stmt_prof);

    if (!$professor) {
        die('Professor not found.');
    }

    $sql_dep = "SELECT dep_name FROM department WHERE dep_id = ?";
    $stmt_dep = mysqli_prepare($conn, $sql_dep);
    mysqli_stmt_bind_param($stmt_dep, 's', $professor['dep_id']);
    mysqli_stmt_execute($stmt_dep);
    $result_dep = mysqli_stmt_get_result($stmt_dep);
    $department = mysqli_fetch_assoc($result_dep);
    mysqli_stmt_close($stmt_dep);
    $departmentName = $department['dep_name'] ?? $professor['dep_id'];

    $sql_courses = "SELECT c.course_code, c.course_name, c.course_credit_nb, c.course_level, c.course_lang, c.course_semester_nb, m.major_name
                    FROM teaching t
                    JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                    JOIN major m ON c.major_id = m.major_id
                    WHERE t.prof_file_nb = ?
                    ORDER BY c.course_code, c.course_lang";
    $stmt_courses = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_courses, 'i', $professor['prof_file_nb']);
    mysqli_stmt_execute($stmt_courses);
    $result_courses = mysqli_stmt_get_result($stmt_courses);

    $courses = [];
    while ($row = mysqli_fetch_assoc($result_courses)) {
        $courses[] = $row;
    }
    mysqli_stmt_close($stmt_courses);

    if (ob_get_contents()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $sheet->setTitle('Course Export');

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(3);
    $sheet->getColumnDimension('B')->setWidth(4);
    $sheet->getColumnDimension('C')->setWidth(4);
    $sheet->getColumnDimension('D')->setWidth(7);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(14);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(14);
    $sheet->getColumnDimension('I')->setWidth(16);
    $sheet->getColumnDimension('J')->setWidth(18);
    $sheet->getColumnDimension('K')->setWidth(20);

    // Page header
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', 'إضـبـارة تـصـحـيـح الـمـسـابـقـات');

    $sheet->getStyle('A1:K2')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->getRowDimension(2)->setRowHeight(24);

    // Professor metadata
    $sheet->setCellValue('H4', 'القسم:');
    $sheet->setCellValue('I4', $departmentName);
    $sheet->mergeCells('I4:J4');
    $sheet->setCellValue('J4', 'الدورة:');
    $sheet->setCellValue('K4', 'الأولى');

    $sheet->setCellValue('H5', 'العام الجامعي:');
    $sheet->setCellValue('I5', date('Y') . ' - ' . (date('Y') + 1));
    $sheet->setCellValue('J5', 'الفصل:');
    $sheet->setCellValue('K5', 'الأول');

    $sheet->setCellValue('J6', 'الإسم:');
    $sheet->setCellValue('K6', $professor['prof_first_name'] . ' ' . $professor['prof_father_name'] . ' ' . $professor['prof_last_name']);
    $sheet->setCellValue('J7', 'رقم الملف:');
    $sheet->setCellValue('K7', $professor['prof_file_nb']);

    $sheet->setCellValue('F8', 'الامتحان:');
    $sheet->setCellValue('G8', 'جزئي');
    $sheet->setCellValue('F9', 'وضعه في الكلية:');
    $sheet->setCellValue('G9', 'ملاك');
    $sheet->setCellValue('H9', 'متفرغ');
    $sheet->setCellValue('I9', 'متعاقد بالساعة');
    $sheet->setCellValue('G10', $professor['prof_category'] === 'ملاك' ? 'X' : '');
    $sheet->setCellValue('H10', $professor['prof_category'] === 'متفرغ' ? 'X' : '');
    $sheet->setCellValue('I10', $professor['prof_category'] === 'متعاقد بالساعة' ? 'X' : '');

    $sheet->getStyle('F8:I10')->getFont()->setSize(11);
    $sheet->getStyle('F8:I10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('F8:I10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Table headers
    $sheet->mergeCells('F12:F13');
    $sheet->setCellValue('F12', 'المقرر');
    $sheet->mergeCells('G12:H12');
    $sheet->setCellValue('G12', 'إجازة');
    $sheet->mergeCells('I12:J12');
    $sheet->setCellValue('I12', 'ماستر');

    $sheet->setCellValue('G13', 'مصحح أول');
    $sheet->setCellValue('H13', 'مصحح ثان');
    $sheet->setCellValue('I13', 'مصحح أول');
    $sheet->setCellValue('J13', 'مصحح ثان');

    $sheet->getStyle('F12:J13')->getFont()->setBold(true);
    $sheet->getStyle('F12:J13')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(12)->setRowHeight(28);
    $sheet->getRowDimension(13)->setRowHeight(22);

    $startRow = 14;
    $licenseCount = 0;
    $masterCount = 0;
    foreach ($courses as $index => $course) {
        $row = $startRow + $index;
        $sheet->setCellValue('F' . $row, $course['course_code'] . ' (' . $course['course_lang'] . ')');
        $sheet->setCellValue('G' . $row, '');
        $sheet->setCellValue('H' . $row, '');
        $sheet->setCellValue('I' . $row, '');
        $sheet->setCellValue('J' . $row, '');

        if (in_array($course['course_level'], ['L1', 'L2', 'L3'], true)) {
            $licenseCount++;
        } elseif ($course['course_level'] === 'M1') {
            $masterCount++;
        }
    }

    if (count($courses) > 0) {
        $endRow = $startRow + count($courses) - 1;
    } else {
        $endRow = $startRow;
        $sheet->setCellValue('F' . $endRow, 'لا توجد مقررات');
    }

    $sheet->getStyle('F' . $startRow . ':J' . $endRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F' . $startRow . ':F' . $endRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G' . $startRow . ':J' . $endRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $summaryRow = $endRow + 2;
    $sheet->setCellValue('F' . $summaryRow, 'المجموع');
    $sheet->setCellValue('G' . $summaryRow, count($courses));
    $sheet->setCellValue('F' . ($summaryRow + 1), 'العدد الإجمالي (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 1), $licenseCount);
    $sheet->setCellValue('F' . ($summaryRow + 2), 'العدد الإجمالي (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 2), $masterCount);
    $sheet->setCellValue('F' . ($summaryRow + 3), 'عدد اللجان المقترحة (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 3), 1);
    $sheet->setCellValue('F' . ($summaryRow + 4), 'عدد اللجان المقترحة (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 4), 1);
    $sheet->setCellValue('F' . ($summaryRow + 5), 'التاريخ:');
    $sheet->setCellValue('F' . ($summaryRow + 6), 'توقيع صاحب العلاقة');
    $sheet->setCellValue('F' . ($summaryRow + 7), 'ختم وتوقيع رئيس القسم');

    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 4))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F' . $summaryRow . ':F' . ($summaryRow + 7))->getFont()->setBold(true);
    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($summaryRow + 5)->setRowHeight(18);
    $sheet->getRowDimension($summaryRow + 6)->setRowHeight(18);
    $sheet->getRowDimension($summaryRow + 7)->setRowHeight(18);

    foreach (range('A', 'K') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(false);
    }

    $fileName = 'اضبارة تصحيح مسابقات' . date('Y-m-d_H-i') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
