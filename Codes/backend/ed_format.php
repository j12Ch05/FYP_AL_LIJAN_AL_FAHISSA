<?php
/**
 * Department export "اضبارة تصحيح مسابقات": one workbook.
 * For each professor in the same department as the logged-in admin, adds the same sheet set
 * as Prof_export_course.php (S1 partial/final, S2 partial/final, session 2, summary) with unique tab names.
 */
ob_start();
ini_set('display_errors', '0');
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/database.php';

if (!isset($_SESSION['email'])) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$dep_id = $_SESSION["dep_id"];
$filter = $_SESSION['excel_export_filter'] ?? [];
$sess = $filter['sessionId'] ?? '';
$year = $filter['excelYear'] ?? '';
$major = $filter['excelMajor'] ?? 'all';
$level = $filter['excelLevel'] ?? 'all';
$sem = "";

if ($sess == "sess2") {
    $d = "الثانية";
} else {
    $d = "الاول";
    $sem = ($sess == "sem1") ? "الاول" : "الثاني";
}

if ($year === '') {
    if (ob_get_length()) {
        ob_end_clean();
    }
    $_SESSION['excel_export_error'] = 'Please choose a university year and export again.';
    header('Location: AdminPage.php?tab=correctors');
    exit;
}

// 1. Get all professors in the department
$sql_professors = "SELECT prof_file_nb, prof_first_name, prof_last_name, prof_category FROM professor WHERE dep_id = ?";
$stmt_p = mysqli_prepare($conn, $sql_professors);
mysqli_stmt_bind_param($stmt_p, 's', $dep_id);
mysqli_stmt_execute($stmt_p);
$res_p = mysqli_stmt_get_result($stmt_p);

$professors = [];
while ($row = mysqli_fetch_assoc($res_p)) {
    $professors[$row["prof_file_nb"]] = [
        "full_name" => $row["prof_first_name"] . " " . $row["prof_last_name"],
        "category"  => $row["prof_category"]
    ];
}

// 2. Build the course query dynamically
$sql_courses = "SELECT corr.course_code,
                       corr.course_lang,
                       corr.prof_file_nb,
                       corr.second_corrector_file_nb,
                       corr.partial_first_corrector,
                       corr.partial_second_corrector,
                       corr.final_first_corrector,
                       corr.final_second_corrector,
                       c.course_level,
                       d.dep_name,
                       corr.session_nb
                FROM correctors corr
                JOIN course c ON c.course_code = corr.course_code 
                     AND c.course_lang = corr.course_lang 
                     AND c.major_id = corr.major_id 
                     AND c.uni_year = corr.uni_year
                JOIN professor p ON p.prof_file_nb = corr.prof_file_nb
                JOIN department d ON d.dep_id = p.dep_id
                WHERE corr.session_nb = ? AND d.dep_id = ? AND corr.uni_year = ? ";

$paramTypes = 'sss';
$param = [$sess, $dep_id, $year];

if ($level !== "all") {
    $sql_courses .= " AND c.course_level = ? ";
    $paramTypes .= "s";
    $param[] = $level;
}
if ($major !== "all") {
    $sql_courses .= " AND c.major_id = ? ";
    $paramTypes .= "s";
    $param[] = $major;
}

$sql_courses .= " ORDER BY corr.course_code ASC";

$stmt_c = mysqli_prepare($conn, $sql_courses);
mysqli_stmt_bind_param($stmt_c, $paramTypes, ...$param);
mysqli_stmt_execute($stmt_c);
$res_c = mysqli_stmt_get_result($stmt_c);

$courses = [];
$dep_name = "Department"; // Default
while ($row = mysqli_fetch_assoc($res_c)) {
    $dep_name = $row["dep_name"];
    $courses[] = [
        "display_code"     => $row["course_code"] . "(" . $row["course_lang"] . ")",
        "level"            => $row["course_level"],
        "first_corrector"  => $row["prof_file_nb"],
        "second_corrector" => $row["second_corrector_file_nb"],
        "first_partial"    => $row["partial_first_corrector"],
        "second_partial"   => $row["partial_second_corrector"],
        "first_final"      => $row["final_first_corrector"],
        "second_final"     => $row["final_second_corrector"],
    ];
}
mysqli_close($conn);

// 3. Generate Spreadsheet
$spreadsheet = new Spreadsheet();
$isFirstSheet = true;

foreach ($professors as $profId => $profData) {
    if ($sess == "sess2") {
        $sheet = $isFirstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        createCorrectionSheet($sheet, $profId, $profData, $dep_name, $courses, 'نهائي',  $d, $sem, $year);
        $sheet->setTitle(mb_substr($profData["full_name"], 0, 20, 'UTF-8') . '_SESS2');
        $isFirstSheet = false;
    } else {
        // Partiel Sheet
        $sheetP = $isFirstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        createCorrectionSheet($sheetP, $profId, $profData, $dep_name, $courses, 'جزئي', $d, $sem, $year);
        $sheetP->setTitle(mb_substr($profData["full_name"], 0, 20, 'UTF-8') . '_Partiel');
        
        // Final Sheet
        $sheetF = $spreadsheet->createSheet();
        createCorrectionSheet($sheetF, $profId, $profData, $dep_name, $courses, 'نهائي', $d, $sem, $year);
        $sheetF->setTitle(mb_substr($profData["full_name"], 0, 20, 'UTF-8') . '_Final');
        $isFirstSheet = false;
    }
}

// Final check: Clear all buffers to ensure no whitespace/notices get into the file
while (ob_get_level()) {
    ob_end_clean();
}

$fileName = 'اضبارة تصحيح مسابقات_' . date('Y-m-d_H-i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

/**
 * --- HELPER FUNCTION ---
 */
function createCorrectionSheet($sheet, $profId, $professor, $departmentName, $courseList, $examType, $session, $semester,$year): void
{
    $sheet->setRightToLeft(true);

    // Column Widths
    $sheet->getColumnDimension('A')->setWidth(3);
    $sheet->getColumnDimension('B')->setWidth(4);
    $sheet->getColumnDimension('C')->setWidth(4);
    $sheet->getColumnDimension('D')->setWidth(7);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(25);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(14);
    $sheet->getColumnDimension('I')->setWidth(16);
    $sheet->getColumnDimension('J')->setWidth(18);
    $sheet->getColumnDimension('K')->setWidth(20);

    // Header
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', 'إضـبـارة تـصـحـيـح الـمـسـابـقـات');
    $sheet->getStyle('A1:K2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('H4', 'القسم:');
    $sheet->setCellValue('I4', $departmentName);
    $sheet->setCellValue('J4', 'الدورة:');
    $sheet->setCellValue('K4', $session);

    $sheet->setCellValue('H5', 'العام الجامعي:');
    $sheet->setCellValue('I5', $year);
    $sheet->setCellValue('J5', 'الفصل:');
    $sheet->setCellValue('K5', $semester);

    $sheet->setCellValue('J6', 'الإسم:');
    $sheet->setCellValue('K6', $professor["full_name"]);
    $sheet->setCellValue('J7', 'رقم الملف:');
    $sheet->setCellValue('K7', $profId);

    $sheet->setCellValue('F8', 'الامتحان:');
    $sheet->setCellValue('G8', $examType);
    $sheet->setCellValue('F9', 'وضعه في الكلية:');
    $sheet->setCellValue('G9', 'ملاك');
    $sheet->setCellValue('H9', 'متفرغ');
    $sheet->setCellValue('I9', 'متعاقد بالساعة');

    $sheet->setCellValue('G10', ($professor['category'] ?? '') === 'ملاك' ? 'X' : '');
    $sheet->setCellValue('H10', ($professor['category'] ?? '') === 'متفرغ' ? 'X' : '');
    $sheet->setCellValue('I10', ($professor['category'] ?? '') === 'متعاقد بالساعة' ? 'X' : '');

    $sheet->getStyle('F8:I10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F8:I10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Table Headers
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

    $sheet->getStyle('F12:J13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E6E6');
    $sheet->getStyle('F12:J13')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $rowNum = 14;
    $licenseCount = 0;
    $masterCount = 0;
    $totalCoursesFound = 0;

    foreach ($courseList as $c) {
        if ($c['first_corrector'] != $profId && $c['second_corrector'] != $profId) {
            continue;
        }

        $sheet->setCellValue('F' . $rowNum, $c['display_code']);
        $isMaster = (strpos($c['level'], 'M') === 0);
        $isMaster ? $masterCount++ : $licenseCount++;
        $totalCoursesFound++;

        $colOffset = $isMaster ? 2 : 0;

        if ($examType == 'جزئي') {
            if($c['first_corrector'] == $profId){
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['first_partial']);
            }
            else{
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['second_partial']);
            }
        } else {
            if($c['first_corrector'] == $profId){
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['first_final']);
            }
            else{
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['second_final']);
            }
        }
        $rowNum++;
    }

    if ($rowNum > 14) {
        $sheet->getStyle('F14:J' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    $summaryRow = $rowNum + 2;
    $sheet->setCellValue('F' . $summaryRow, 'المجموع');
    $sheet->setCellValue('G' . $summaryRow, $totalCoursesFound);
    $sheet->setCellValue('F' . ($summaryRow + 1), 'العدد الإجمالي (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 1), $licenseCount);
    $sheet->setCellValue('F' . ($summaryRow + 2), 'العدد الإجمالي (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 2), $masterCount);
    
    $sheet->setCellValue('F' . ($summaryRow + 5), 'التاريخ: ' . date('Y-m-d'));
    $sheet->setCellValue('F' . ($summaryRow + 6), 'توقيع صاحب العلاقة');
    $sheet->setCellValue('F' . ($summaryRow + 7), 'ختم وتوقيع رئيس القسم');

    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 2))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F' . $summaryRow . ':F' . ($summaryRow + 7))->getFont()->setBold(true);
}