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

    $sql_prof = "SELECT prof_file_nb, prof_first_name, prof_last_name, dep_id FROM professor WHERE prof_email = ?";
    $stmt_prof = mysqli_prepare($conn, $sql_prof);
    mysqli_stmt_bind_param($stmt_prof, 's', $email);
    mysqli_stmt_execute($stmt_prof);
    $result_prof = mysqli_stmt_get_result($stmt_prof);
    $professor = mysqli_fetch_assoc($result_prof);

    if (!$professor) {
        die('Professor not found.');
    }

    $sql_dep = "SELECT dep_name FROM department WHERE dep_id = ?";
    $stmt_dep = mysqli_prepare($conn, $sql_dep);
    mysqli_stmt_bind_param($stmt_dep, 's', $professor['dep_id']);
    mysqli_stmt_execute($stmt_dep);
    $result_dep = mysqli_stmt_get_result($stmt_dep);
    $department = mysqli_fetch_assoc($result_dep);
    $departmentName = $department['dep_name'] ?? $professor['dep_id'];

    $sql_courses = "SELECT c.course_code, c.course_name, c.course_credit_nb, c.course_level, c.course_lang, c.course_semester_nb, m.major_name
                    FROM teaching t
                    JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                    JOIN major m ON c.major_id = m.major_id
                    WHERE t.prof_file_nb = ?
                    ORDER BY c.course_code";
    $stmt_courses = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_courses, 'i', $professor['prof_file_nb']);
    mysqli_stmt_execute($stmt_courses);
    $result_courses = mysqli_stmt_get_result($stmt_courses);

    $courses = [];
    while ($row = mysqli_fetch_assoc($result_courses)) {
        $courses[] = $row;
    }

    if (ob_get_contents()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $sheet->setTitle('Course Export');

    // Page header
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', 'إضـبـارة تـصـحـيـح الـمـسـابـقـات');

    $sheet->getStyle('A1:K2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    // Professor metadata
    $sheet->setCellValue('H4', 'القسم:');
    $sheet->setCellValue('I4', $departmentName);
    $sheet->setCellValue('H5', 'الاسم:');
    $sheet->setCellValue('I5', $professor['prof_first_name'] . ' ' . $professor['prof_last_name']);
    $sheet->setCellValue('H6', 'رقم الملف:');
    $sheet->setCellValue('I6', $professor['prof_file_nb']);
    $sheet->setCellValue('H7', 'العام الجامعي:');
    $sheet->setCellValue('I7', date('Y') . ' - ' . (date('Y') + 1));
    $sheet->setCellValue('K4', 'الدورة:');
    $sheet->setCellValue('K5', 'الأولى');
    $sheet->setCellValue('K6', 'الفصل:');
    $sheet->setCellValue('K7', 'الأول');
    $sheet->setCellValue('F4', 'الامتحان:');
    $sheet->setCellValue('G4', 'جزئي');
    $sheet->setCellValue('F5', 'وضعه في الكلية:');
    $sheet->setCellValue('G5', 'ملاك');
    $sheet->setCellValue('F6', 'X');

    $sheet->getStyle('F4:K7')->getFont()->setSize(11);
    $sheet->getStyle('F4:K7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Table headers
    $sheet->mergeCells('F9:F10');
    $sheet->setCellValue('F9', 'المقرر');
    $sheet->mergeCells('G9:H9');
    $sheet->setCellValue('G9', 'إجازة');
    $sheet->mergeCells('I9:J9');
    $sheet->setCellValue('I9', 'ماستر');

    $sheet->setCellValue('G10', 'مصحح أول');
    $sheet->setCellValue('H10', 'مصحح ثان');
    $sheet->setCellValue('I10', 'مصحح أول');
    $sheet->setCellValue('J10', 'مصحح ثان');

    $sheet->getStyle('F9:J10')->getFont()->setBold(true);
    $sheet->getStyle('F9:J10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(9)->setRowHeight(25);
    $sheet->getRowDimension(10)->setRowHeight(20);

    $startRow = 11;
    foreach ($courses as $index => $course) {
        $row = $startRow + $index;
        $sheet->setCellValue('F' . $row, $course['course_code'] . ' (' . $course['course_name'] . ')');
        $sheet->setCellValue('G' . $row, '');
        $sheet->setCellValue('H' . $row, '');
        $sheet->setCellValue('I' . $row, '');
        $sheet->setCellValue('J' . $row, '');
    }

    if (count($courses) > 0) {
        $endRow = $startRow + count($courses) - 1;
    } else {
        $endRow = $startRow;
        $sheet->setCellValue('F' . $endRow, 'لا توجد مقررات');
    }

    $summaryRow = $endRow + 2;
    $sheet->setCellValue('F' . $summaryRow, 'المجموع');
    $sheet->setCellValue('G' . $summaryRow, count($courses));
    $sheet->setCellValue('F' . ($summaryRow + 1), 'العدد الإجمالي (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 1), count($courses));
    $sheet->setCellValue('F' . ($summaryRow + 2), 'العدد الإجمالي (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 2), count($courses));

    $sheet->getStyle('F9:J' . $endRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 2))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    foreach (range('A', 'K') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    $fileName = 'Prof_Course_Export_' . date('Y-m-d_H-i') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
