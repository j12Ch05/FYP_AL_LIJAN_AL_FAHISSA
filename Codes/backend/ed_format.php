<?php
/**
 * Department export "اضبارة تصحيح مسابقات": one workbook.
 * For each professor in the same department as the logged-in admin, adds the same sheet set
 * as Prof_export_course.php (S1 partial/final, S2 partial/final, session 2, summary) with unique tab names.
 */
ob_start();
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();
include __DIR__ . '/database.php';

if (!isset($_SESSION['email'])) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Location: login.php');
    exit;
}

$email = $_SESSION['email'];
$filter = $_SESSION['excel_export_filter'] ?? [];
$year = $filter['excelYear'] ?? '';
$major = $filter['excelMajor'] ?? 'all';
$level = $filter['excelLevel'] ?? 'all';

if ($year === '') {
    if (ob_get_length()) {
        ob_end_clean();
    }
    $_SESSION['excel_export_error'] = 'Please choose a university year and export again.';
    header('Location: AdminPage.php?tab=correctors');
    exit;
}

// All professors in the same department as the logged-in user (chair/admin), with full row for export
$sql_prof = 'SELECT p.prof_file_nb, p.prof_first_name, p.prof_father_name, p.prof_last_name, p.prof_category,
                     d.dep_id, d.dep_name
              FROM professor p
              JOIN department d ON p.dep_id = d.dep_id
              JOIN professor a ON p.dep_id = a.dep_id
              WHERE a.prof_email = ?
              ORDER BY p.prof_last_name, p.prof_first_name';
$stmt_prof = mysqli_prepare($conn, $sql_prof);
if (!$stmt_prof) {
    die('Database error (professors).');
}
mysqli_stmt_bind_param($stmt_prof, 's', $email);
mysqli_stmt_execute($stmt_prof);
$result_prof = mysqli_stmt_get_result($stmt_prof);

$professorsById = [];
$departmentName = 'General';
while ($row = mysqli_fetch_assoc($result_prof)) {
    $professorsById[$row['prof_file_nb']] = $row;
    $departmentName = $row['dep_name'] ?? $departmentName;
}
mysqli_stmt_close($stmt_prof);

if (!$professorsById) {
    die('No professors found for this department.');
}

/**
 * Fetch corrector rows for one professor (same logic as Prof_export_course.php) with optional major/level/year filters.
 *
 * @return array{0: array<string, array<int, array>>, 1: array<string, array<string, mixed>>}
 */
function fetchProfessorCorrectorData(mysqli $conn, string $profId, string $year, string $major, string $level): array
{
    $sql = 'SELECT corr.*, c.course_name, c.course_level, c.course_credit_nb, m.major_name, t.uni_year
            FROM correctors corr
            JOIN teaching t ON corr.course_code = t.course_code AND t.course_lang = corr.course_lang
                AND t.prof_file_nb = corr.prof_file_nb AND t.major_id = corr.major_id
            JOIN course c ON c.course_code = t.course_code AND c.course_lang = t.course_lang AND c.major_id = t.major_id
            JOIN major m ON m.major_id = c.major_id
            WHERE (corr.prof_file_nb = ? OR corr.second_corrector_file_nb = ?)
              AND corr.uni_year = ?';
    $types = 'sss';
    $params = [$profId, $profId, $year];
    if ($major !== 'all') {
        $sql .= ' AND corr.major_id = ?';
        $types .= 's';
        $params[] = $major;
    }
    if ($level !== 'all') {
        $sql .= ' AND c.course_level = ?';
        $types .= 's';
        $params[] = $level;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [['sem1' => [], 'sem2' => [], 'sess2' => []], []];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = ['sem1' => [], 'sem2' => [], 'sess2' => []];
    $course_details = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $sess = $row['session_nb'];
        if (!isset($data[$sess])) {
            $data[$sess] = [];
        }

        $full_code = $row['course_code'] . ' (' . $row['course_lang'] . ')';
        $data[$sess][] = [
            'display_code' => $full_code,
            'level' => $row['course_level'],
            'first_corrector' => $row['prof_file_nb'],
            'correctors' => [
                $row['partial_first_corrector'],
                $row['partial_second_corrector'],
                $row['final_first_corrector'],
                $row['final_second_corrector'],
            ],
        ];

        $course_details[$full_code] = [
            'name' => $row['course_name'],
            'level' => $row['course_level'],
            'major' => $row['major_name'],
            'credits' => $row['course_credit_nb'],
        ];
    }
    mysqli_stmt_close($stmt);

    return [$data, $course_details];
}

/** Excel worksheet title max 31 chars; forbidden characters removed. */
function excelSheetTitle(string $title): string
{
    $title = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '-', $title);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($title) > 31 ? mb_substr($title, 0, 31) : $title;
    }

    return strlen($title) > 31 ? substr($title, 0, 31) : $title;
}

$spreadsheet = new Spreadsheet();
$firstSheet = true;

foreach ($professorsById as $profId => $professor) {
    [$data, $course_details] = fetchProfessorCorrectorData($conn, (string) $profId, (string) $year, (string) $major, (string) $level);

    $sheetDefs = [
        [$data['sem1'], 'جزئي', 'الأولى', 'الأول', 'S1_Partiel'],
        [$data['sem1'], 'نهائي', 'الأولى', 'الأول', 'S1_Final'],
        [$data['sem2'], 'جزئي', 'الثانية', 'الثاني', 'S2_Partiel'],
        [$data['sem2'], 'نهائي', 'الثانية', 'الثاني', 'S2_Final'],
        [$data['sess2'], 'إعادة', 'الثانية', 'الثاني', 'Session_2'],
    ];

    foreach ($sheetDefs as $def) {
        [$courseList, $examType, $sessionLabel, $semesterLabel, $suffix] = $def;
        if ($firstSheet) {
            $sheet = $spreadsheet->getActiveSheet();
            $firstSheet = false;
        } else {
            $sheet = $spreadsheet->createSheet();
        }
        createCorrectionSheet($sheet, $professor, $departmentName, $courseList, $examType, $sessionLabel, $semesterLabel);
        $sheet->setTitle(excelSheetTitle($profId . '_' . $suffix));
    }

    $sumSheet = $spreadsheet->createSheet();
    createSummarySheet($sumSheet, $professor, $departmentName, $course_details);
    $sumSheet->setTitle(excelSheetTitle($profId . '_ملخص'));
}

mysqli_close($conn);

if (ob_get_length()) {
    ob_end_clean();
}

$fileName = 'اضبارة تصحيح مسابقات_' . date('Y-m-d_H-i') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

// --- Same layout as Prof_export_course.php ---

function createCorrectionSheet($sheet, $professor, $departmentName, $courseList, $examType, $session, $semester): void
{
    $sheet->setRightToLeft(true);

    $sheet->getColumnDimension('A')->setWidth(3);
    $sheet->getColumnDimension('B')->setWidth(4);
    $sheet->getColumnDimension('C')->setWidth(4);
    $sheet->getColumnDimension('D')->setWidth(7);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(19.82);
    $sheet->getColumnDimension('G')->setWidth(12);
    $sheet->getColumnDimension('H')->setWidth(14);
    $sheet->getColumnDimension('I')->setWidth(16);
    $sheet->getColumnDimension('J')->setWidth(18);
    $sheet->getColumnDimension('K')->setWidth(20);

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

    $sheet->setCellValue('G10', ($professor['prof_category'] ?? '') === 'ملاك' ? 'X' : '');
    $sheet->setCellValue('H10', ($professor['prof_category'] ?? '') === 'متفرغ' ? 'X' : '');
    $sheet->setCellValue('I10', ($professor['prof_category'] ?? '') === 'متعاقد بالساعة' ? 'X' : '');

    $sheet->getStyle('F8:I10')->getFont()->setSize(11);
    $sheet->getStyle('F8:I10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('F8:I10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

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

    foreach ($courseList as $c) {
        $sheet->setCellValue('F' . $rowNum, $c['display_code']);

        $isMaster = (strpos($c['level'], 'M') === 0);
        if ($isMaster) {
            $masterCount++;
        } else {
            $licenseCount++;
        }

        $colOffset = $isMaster ? 2 : 0;

        if ($examType === 'جزئي') {
            if ($c['first_corrector'] == $professor['prof_file_nb']) {
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][0]);
            } else {
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][1]);
            }
        } else {
            if ($c['first_corrector'] == $professor['prof_file_nb']) {
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][2]);
            } else {
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][3]);
            }
        }
        $rowNum++;
    }

    if ($rowNum > 14) {
        $sheet->getStyle('F14:J' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    $summaryRow = $rowNum + 2;
    $sheet->setCellValue('F' . $summaryRow, 'المجموع');
    $sheet->setCellValue('G' . $summaryRow, count($courseList));
    $sheet->setCellValue('F' . ($summaryRow + 1), 'العدد الإجمالي (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 1), $licenseCount);
    $sheet->setCellValue('F' . ($summaryRow + 2), 'العدد الإجمالي (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 2), $masterCount);
    $sheet->setCellValue('F' . ($summaryRow + 3), 'عدد اللجان المقترحة (إجازة):');
    $sheet->setCellValue('G' . ($summaryRow + 3), $licenseCount > 0 ? 1 : 0);
    $sheet->setCellValue('F' . ($summaryRow + 4), 'عدد اللجان المقترحة (ماستر):');
    $sheet->setCellValue('G' . ($summaryRow + 4), $masterCount > 0 ? 1 : 0);
    $sheet->setCellValue('F' . ($summaryRow + 5), 'التاريخ: ' . date('Y-m-d'));
    $sheet->setCellValue('F' . ($summaryRow + 6), 'توقيع صاحب العلاقة');
    $sheet->setCellValue('F' . ($summaryRow + 7), 'ختم وتوقيع رئيس القسم');

    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 4))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('F' . $summaryRow . ':F' . ($summaryRow + 7))->getFont()->setBold(true);
    $sheet->getStyle('F' . $summaryRow . ':G' . ($summaryRow + 7))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

function createSummarySheet($sheet, $professor, $departmentName, $course_details): void
{
    $sheet->getColumnDimension('A')->setWidth(11.36);
    $sheet->getColumnDimension('B')->setWidth(26.45);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(10);
    $sheet->getColumnDimension('E')->setWidth(26.45);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(15.64);

    $sheet->setRightToLeft(true);
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:G2');
    $sheet->setCellValue('A2', 'ملخص المقررات لعام ' . date('Y') . '-' . (date('Y') + 1));

    $sheet->getStyle('A1:G2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A3', 'القسم: ' . $departmentName);
    $sheet->setCellValue('A4', 'رقم الملف: ' . $professor['prof_file_nb']);
    $sheet->setCellValue('A5', 'الاسم: ' . $professor['prof_first_name'] . ' ' . $professor['prof_last_name']);

    $sheet->setCellValue('A8', 'رمز المقرر');
    $sheet->setCellValue('B8', 'اسم المقرر');
    $sheet->setCellValue('C8', 'المستوى');
    $sheet->setCellValue('D8', 'الوحدات');
    $sheet->setCellValue('E8', 'الاختصاص');

    $sheet->getStyle('A8:E8')->getFont()->setBold(true);
    $sheet->getStyle('A8:E8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

    $rowNum = 9;
    $totalCredits = 0;
    $mCount = 0;
    $lCount = 0;

    foreach ($course_details as $code => $info) {
        $sheet->setCellValue('A' . $rowNum, $code);
        $sheet->setCellValue('B' . $rowNum, $info['name']);
        $sheet->setCellValue('C' . $rowNum, $info['level']);
        $sheet->setCellValue('D' . $rowNum, $info['credits']);
        $sheet->setCellValue('E' . $rowNum, $info['major']);

        if (strpos($info['level'], 'M') === 0) {
            $mCount++;
        } else {
            $lCount++;
        }

        $totalCredits += (int) $info['credits'];
        $rowNum++;
    }

    if ($rowNum > 9) {
        $sheet->getStyle('A8:E' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    $sumStart = $rowNum + 2;
    $sheet->setCellValue('B' . $sumStart, 'ملخص المقررات:');
    $sheet->setCellValue('C' . $sumStart, 'العدد');

    $sheet->setCellValue('B' . ($sumStart + 1), 'عدد مقررات الإجازة:');
    $sheet->setCellValue('C' . ($sumStart + 1), $lCount);

    $sheet->setCellValue('B' . ($sumStart + 2), 'عدد مقررات الماستر:');
    $sheet->setCellValue('C' . ($sumStart + 2), $mCount);

    $sheet->setCellValue('B' . ($sumStart + 3), 'إجمالي الوحدات:');
    $sheet->setCellValue('C' . ($sumStart + 3), $totalCredits);

    $sheet->getStyle('B' . $sumStart . ':C' . ($sumStart + 3))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('B' . $sumStart . ':B' . ($sumStart + 3))->getFont()->setBold(true);
    $sheet->getStyle('C' . $sumStart . ':C' . ($sumStart + 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $sumStart)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
}
