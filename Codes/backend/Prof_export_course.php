<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

    $sql_courses = "SELECT c.course_code, c.course_name, c.course_credit_nb, c.course_level, c.course_lang, c.course_semester_nb, m.major_name, m.major_id
                    FROM teaching t
                    JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                    JOIN major m ON c.major_id = m.major_id
                    WHERE t.prof_file_nb = ?
                    ORDER BY c.course_level, c.course_code, c.course_lang";
    $stmt_courses = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_courses, 'i', $professor['prof_file_nb']);
    mysqli_stmt_execute($stmt_courses);
    $result_courses = mysqli_stmt_get_result($stmt_courses);

    $courses = [];
    while ($row = mysqli_fetch_assoc($result_courses)) {
        $courses[] = $row;
    }
    mysqli_stmt_close($stmt_courses);

    // Fetch corrector data from database
    $sql_correctors = "SELECT cr.course_code, cr.course_lang, cr.major_id, cr.session_nb,
                       cr.partial_first_corrector, cr.partial_second_corrector,
                       cr.final_first_corrector, cr.final_second_corrector
                       FROM correctors cr
                       WHERE cr.prof_file_nb = ?";
    $stmt_corr = mysqli_prepare($conn, $sql_correctors);
    mysqli_stmt_bind_param($stmt_corr, 'i', $professor['prof_file_nb']);
    mysqli_stmt_execute($stmt_corr);
    $result_corr = mysqli_stmt_get_result($stmt_corr);

    $correctorsData = [];
    while ($row = mysqli_fetch_assoc($result_corr)) {
        $key = $row['course_code'] . '_' . $row['course_lang'] . '_' . $row['major_id'];
        $correctorsData[$row['session_nb']][$key] = $row;
    }
    mysqli_stmt_close($stmt_corr);

    // Get professor names for corrector IDs
    $allCorrectorIds = [];
    foreach ($correctorsData as $sessionData) {
        foreach ($sessionData as $corr) {
            if ($corr['partial_first_corrector']) $allCorrectorIds[] = $corr['partial_first_corrector'];
            if ($corr['partial_second_corrector']) $allCorrectorIds[] = $corr['partial_second_corrector'];
            if ($corr['final_first_corrector']) $allCorrectorIds[] = $corr['final_first_corrector'];
            if ($corr['final_second_corrector']) $allCorrectorIds[] = $corr['final_second_corrector'];
        }
    }
    $allCorrectorIds = array_unique(array_filter($allCorrectorIds));

    $correctorNames = [];
    if (!empty($allCorrectorIds)) {
        $placeholders = implode(',', array_fill(0, count($allCorrectorIds), '?'));
        $sql_profs = "SELECT prof_file_nb, prof_first_name, prof_father_name, prof_last_name FROM professor WHERE prof_file_nb IN ($placeholders)";
        $stmt_profs = mysqli_prepare($conn, $sql_profs);
        $types = str_repeat('i', count($allCorrectorIds));
        mysqli_stmt_bind_param($stmt_profs, $types, ...$allCorrectorIds);
        mysqli_stmt_execute($stmt_profs);
        $result_profs = mysqli_stmt_get_result($stmt_profs);
        while ($row = mysqli_fetch_assoc($result_profs)) {
            $correctorNames[$row['prof_file_nb']] = $row['prof_first_name'] . ' ' . $row['prof_father_name'] . ' ' . $row['prof_last_name'];
        }
        mysqli_stmt_close($stmt_profs);
    }

    if (ob_get_contents()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();

    // Filter courses by semester
    $coursesSem1 = array_filter($courses, function($c) { return $c['course_semester_nb'] == 1; });
    $coursesSem2 = array_filter($courses, function($c) { return $c['course_semester_nb'] == 2; });

    // ==================== SHEET 1: S1 PARTIEL ====================
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setRightToLeft(true);
    $sheet1->setTitle('S1_Partiel');

    createCorrectionSheet($sheet1, $professor, $departmentName, array_values($coursesSem1), $correctorsData['sem1'] ?? [], 'جزئي', 'الأولى', 'الأول', $correctorNames);

    // ==================== SHEET 2: S1 FINAL ====================
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setRightToLeft(true);
    $sheet2->setTitle('S1_Final');

    createCorrectionSheet($sheet2, $professor, $departmentName, array_values($coursesSem1), $correctorsData['sem1'] ?? [], 'نهائي', 'الأولى', 'الأول', $correctorNames);

    // ==================== SHEET 3: S2 PARTIEL ====================
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setRightToLeft(true);
    $sheet3->setTitle('S2_Partiel');

    createCorrectionSheet($sheet3, $professor, $departmentName, array_values($coursesSem2), $correctorsData['sem2'] ?? [], 'جزئي', 'الثانية', 'الثاني', $correctorNames);

    // ==================== SHEET 4: S2 FINAL ====================
    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setRightToLeft(true);
    $sheet4->setTitle('S2_Final');

    createCorrectionSheet($sheet4, $professor, $departmentName, array_values($coursesSem2), $correctorsData['sem2'] ?? [], 'نهائي', 'الثانية', 'الثاني', $correctorNames);

    // ==================== SHEET 5: S2 RATTRAPAGE ====================
    $sheet5 = $spreadsheet->createSheet();
    $sheet5->setRightToLeft(true);
    $sheet5->setTitle('Session_2');

    createCorrectionSheet($sheet5, $professor, $departmentName, array_values($coursesSem2), $correctorsData['sem2'] ?? [], 'إعادة', 'الثانية', 'الثاني', $correctorNames);

    // ==================== SHEET 6: SUMMARY (ملخص) ====================
    $sheet6 = $spreadsheet->createSheet();
    $sheet6->setRightToLeft(true);
    $sheet6->setTitle('ملخص');

    createSummarySheet($sheet6, $professor, $departmentName, $courses);

    $fileName = 'اضبارة تصحيح مسابقات' . date('Y-m-d_H-i') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function createCorrectionSheet($sheet, $professor, $departmentName, $courses, $correctorsData, $examType, $session, $semester, $correctorNames = []) {
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
    $sheet->setCellValue('K4', $session);

    $sheet->setCellValue('H5', 'العام الجامعي:');
    $sheet->setCellValue('I5', date('Y') . ' - ' . (date('Y') + 1));
    $sheet->setCellValue('J5', 'الفصل:');
    $sheet->setCellValue('K5', $semester);

    $sheet->setCellValue('J6', 'الإسم:');
    $sheet->setCellValue('K6', $professor['prof_first_name'] . ' ' . $professor['prof_father_name'] . ' ' . $professor['prof_last_name']);
    $sheet->setCellValue('J7', 'رقم الملف:');
    $sheet->setCellValue('K7', $professor['prof_file_nb']);

    $sheet->setCellValue('F8', 'الامتحان:');
    $sheet->setCellValue('G8', $examType);
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
    $sheet->getStyle('F12:J13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E6E6');
    $sheet->getRowDimension(12)->setRowHeight(28);
    $sheet->getRowDimension(13)->setRowHeight(22);

    $startRow = 14;
    $licenseCount = 0;
    $masterCount = 0;

        foreach ($courses as $index => $course) {
            $row = $startRow + $index;
            $sheet->setCellValue('F' . $row, $course['course_code'] . ' (' . $course['course_lang'] . ')');

            // Find the correct corrector data for this course and professor
            $key = $course['course_code'] . '_' . $course['course_lang'] . '_' . $course['major_id'] . '_' . $professor['prof_file_nb'];
            $corrData = (isset($correctorsData[$session][$key])) ? $correctorsData[$session][$key] : null;

            if ($examType === 'جزئي') {
                $firstCorrId = $corrData['partial_first_corrector'] ?? null;
                $secondCorrId = $corrData['partial_second_corrector'] ?? null;
            } elseif ($examType === 'نهائي' || $examType === 'إعادة') {
                $firstCorrId = $corrData['final_first_corrector'] ?? null;
                $secondCorrId = $corrData['final_second_corrector'] ?? null;
            }

            $firstCorrName = $firstCorrId && isset($correctorNames[$firstCorrId]) ? $correctorNames[$firstCorrId] : '';
            $secondCorrName = $secondCorrId && isset($correctorNames[$secondCorrId]) ? $correctorNames[$secondCorrId] : '';

            if (in_array($course['course_level'], ['L1', 'L2', 'L3'], true)) {
                $sheet->setCellValue('G' . $row, $firstCorrName);
                $sheet->setCellValue('H' . $row, $secondCorrName);
                $licenseCount++;
            } elseif ($course['course_level'] === 'M1') {
                $sheet->setCellValue('I' . $row, $firstCorrName);
                $sheet->setCellValue('J' . $row, $secondCorrName);
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
}

function createSummarySheet($sheet, $professor, $departmentName, $courses) {
    // Column widths
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(10);
    $sheet->getColumnDimension('E')->setWidth(10);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(12);

    // Header
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:G2');
    $sheet->setCellValue('A2', 'ملخص المقررات');

    $sheet->getStyle('A1:G2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(25);
    $sheet->getRowDimension(2)->setRowHeight(22);

    // Professor info
    $sheet->setCellValue('E4', 'القسم:');
    $sheet->setCellValue('F4', $departmentName);
    $sheet->setCellValue('E5', 'الإسم:');
    $sheet->setCellValue('F5', $professor['prof_first_name'] . ' ' . $professor['prof_father_name'] . ' ' . $professor['prof_last_name']);
    $sheet->setCellValue('E6', 'رقم الملف:');
    $sheet->setCellValue('F6', $professor['prof_file_nb']);

    // Table headers
    $sheet->setCellValue('A8', 'م');
    $sheet->setCellValue('B8', 'رمز المقرر');
    $sheet->setCellValue('C8', 'اسم المقرر');
    $sheet->setCellValue('D8', 'اللغة');
    $sheet->setCellValue('E8', 'المستوى');
    $sheet->setCellValue('F8', 'الوحدات');
    $sheet->setCellValue('G8', 'القسم');

    $sheet->getStyle('A8:G8')->getFont()->setBold(true);
    $sheet->getStyle('A8:G8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A8:G8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
    $sheet->getRowDimension(8)->setRowHeight(22);

    // Data
    $startRow = 9;
    $licenseCourses = [];
    $masterCourses = [];

    foreach ($courses as $index => $course) {
        $row = $startRow + $index;
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $course['course_code']);
        $sheet->setCellValue('C' . $row, $course['course_name']);
        $sheet->setCellValue('D' . $row, $course['course_lang']);
        $sheet->setCellValue('E' . $row, $course['course_level']);
        $sheet->setCellValue('F' . $row, $course['course_credit_nb']);
        $sheet->setCellValue('G' . $row, $course['major_name']);

        if (in_array($course['course_level'], ['L1', 'L2', 'L3'], true)) {
            $licenseCourses[] = $course;
        } elseif ($course['course_level'] === 'M1') {
            $masterCourses[] = $course;
        }
    }

    $endRow = $startRow + count($courses) - 1;

    $sheet->getStyle('A' . $startRow . ':G' . $endRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A' . $startRow . ':G' . $endRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Summary section
    $summaryRow = $endRow + 3;
    $sheet->setCellValue('A' . $summaryRow, 'ملخص');
    $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(12);
    $sheet->mergeCells('A' . $summaryRow . ':B' . $summaryRow);

    $sheet->setCellValue('A' . ($summaryRow + 1), 'إجمالي المقررات:');
    $sheet->setCellValue('B' . ($summaryRow + 1), count($courses));
    $sheet->setCellValue('A' . ($summaryRow + 2), 'مقررات الإجازة:');
    $sheet->setCellValue('B' . ($summaryRow + 2), count($licenseCourses));
    $sheet->setCellValue('A' . ($summaryRow + 3), 'مقررات الماستر:');
    $sheet->setCellValue('B' . ($summaryRow + 3), count($masterCourses));

    $totalCredits = array_sum(array_column($courses, 'course_credit_nb'));
    $sheet->setCellValue('A' . ($summaryRow + 4), 'إجمالي الوحدات:');
    $sheet->setCellValue('B' . ($summaryRow + 4), $totalCredits);

    $sheet->getStyle('A' . ($summaryRow + 1) . ':B' . ($summaryRow + 4))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A' . ($summaryRow + 1) . ':B' . ($summaryRow + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
