<?php
ob_start();
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

    // 1. Get Professor Info
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

    $departmentName = $_SESSION["departments"][$professor["dep_id"]] ?? 'General';
    $prof_id = $professor["prof_file_nb"];

    // 2. Get Courses and Correctors
    $sql_courses = "SELECT corr.*, c.course_name, c.course_level, c.course_credit_nb, m.major_name, t.uni_year
                    FROM correctors corr
                    JOIN teaching t ON corr.course_code = t.course_code AND t.course_lang = corr.course_lang AND t.prof_file_nb = corr.prof_file_nb AND t.major_id = corr.major_id
                    JOIN course c ON c.course_code = t.course_code AND c.course_lang = t.course_lang AND c.major_id = t.major_id
                    JOIN major m ON m.major_id = c.major_id
                    WHERE corr.prof_file_nb = ? OR corr.second_corrector_file_nb = ?";
    
    $stmt_c = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_c, "ss", $prof_id, $prof_id);
    mysqli_stmt_execute($stmt_c);
    $result_course = mysqli_stmt_get_result($stmt_c);

    $data = ["sem1" => [], "sem2" => [], "sess2" => []];
    $course_details = []; 

    while ($row = mysqli_fetch_assoc($result_course)) {
        $sess = $row["session_nb"];
        if (!isset($data[$sess])) $data[$sess] = [];
        
        $full_code = $row["course_code"] . " (" . $row["course_lang"] . ")";
        
        $data[$sess][] = [
            'display_code' => $full_code,
            'level' => $row["course_level"],
            'correctors' => [
                $row["partial_first_corrector"], 
                $row["partial_second_corrector"], 
                $row["final_first_corrector"], 
                $row["final_second_corrector"]
            ]
        ];

        $course_details[$full_code] = [
            'name' => $row["course_name"],
            'level' => $row["course_level"],
            'credits' => $row["course_credit_nb"]
        ];
    }
    mysqli_stmt_close($stmt_c);

    $spreadsheet = new Spreadsheet();

    // Generate Sheets
    createCorrectionSheet($spreadsheet->getActiveSheet(), $professor, $departmentName, $data["sem1"], 'جزئي', 'الأولى', 'الأول');
    $spreadsheet->getActiveSheet()->setTitle('S1_Partiel');

    createCorrectionSheet($spreadsheet->createSheet(), $professor, $departmentName, $data["sem1"], 'نهائي', 'الأولى', 'الأول');
    $spreadsheet->getActiveSheet()->setTitle('S1_Final');

    createCorrectionSheet($spreadsheet->createSheet(), $professor, $departmentName, $data["sem2"], 'جزئي', 'الثانية', 'الثاني');
    $spreadsheet->getActiveSheet()->setTitle('S2_Partiel');

    createCorrectionSheet($spreadsheet->createSheet(), $professor, $departmentName, $data["sem2"], 'نهائي', 'الثانية', 'الثاني');
    $spreadsheet->getActiveSheet()->setTitle('S2_Final');

    createCorrectionSheet($spreadsheet->createSheet(), $professor, $departmentName, $data["sess2"], 'إعادة', 'الثانية', 'الثاني');
    $spreadsheet->getActiveSheet()->setTitle('Session_2');

    createSummarySheet($spreadsheet->createSheet(), $professor, $departmentName, $course_details);
    $spreadsheet->getActiveSheet()->setTitle('ملخص');

    if (ob_get_length()) ob_end_clean();

    $fileName = 'Correction_Report_' . date('Y-m-d_H-i') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function createCorrectionSheet($sheet, $professor, $departmentName, $courseList, $examType, $session, $semester) {
    $sheet->setRightToLeft(true);
    
    // Column Widths
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

    // --- PAGE HEADER (SAME AS SUMMARY) ---
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'الجامعة اللبنانية - كلية العلوم الفرع الثاني');
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', 'إضـبـارة تـصـحـيـح الـمـسـابـقـات');
    $sheet->getStyle('A1:K2')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Professor metadata
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
    
    $sheet->setCellValue('G10', $professor['prof_category'] === 'ملاك' ? 'X' : '');
    $sheet->setCellValue('H10', $professor['prof_category'] === 'متفرغ' ? 'X' : '');
    $sheet->setCellValue('I10', $professor['prof_category'] === 'متعاقد بالساعة' ? 'X' : '');

    $sheet->getStyle('F8:I10')->getFont()->setSize(11);
    $sheet->getStyle('F8:I10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('F8:I10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Table Header
    $sheet->mergeCells('F12:F13'); $sheet->setCellValue('F12', 'المقرر');
    $sheet->mergeCells('G12:H12'); $sheet->setCellValue('G12', 'إجازة');
    $sheet->mergeCells('I12:J12'); $sheet->setCellValue('I12', 'ماستر');
    $sheet->setCellValue('G13', 'مصحح أول'); $sheet->setCellValue('H13', 'مصحح ثان');
    $sheet->setCellValue('I13', 'مصحح أول'); $sheet->setCellValue('J13', 'مصحح ثان');
    
    $sheet->getStyle('F12:J13')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E6E6');
    $sheet->getStyle('F12:J13')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $rowNum = 14;
    $licenseCount = 0;
    $masterCount = 0;

    foreach ($courseList as $c) {
        $sheet->setCellValue('F' . $rowNum, $c['display_code']);
        
        $isMaster = (strpos($c['level'], 'M') === 0);
        if ($isMaster) $masterCount++; else $licenseCount++;

        $colOffset = $isMaster ? 2 : 0; 

        if ($examType == 'جزئي') {
            $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][0]);
            $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][1]);
        } else {
            $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][2]);
            $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][3]);
        }
        $rowNum++;
    }
    
    if($rowNum > 14) {
        $sheet->getStyle('F14:J' . ($rowNum-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    // FOOTER SUMMARY TABLE
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

function createSummarySheet($sheet, $professor, $departmentName, $course_details) {
    $sheet->getColumnDimension('A')->setWidth(11.36);
    $sheet->getColumnDimension('B')->setWidth(26.45);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(10);
    $sheet->getColumnDimension('E')->setWidth(10);
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
    $sheet->setCellValue('A5', 'الاسم: ' . $professor["prof_first_name"] . ' ' . $professor['prof_last_name']); 
    
    $sheet->setCellValue('A8', 'رمز المقرر');
    $sheet->setCellValue('B8', 'اسم المقرر');
    $sheet->setCellValue('C8', 'المستوى');
    $sheet->setCellValue('D8', 'الوحدات');
    
    $sheet->getStyle('A8:D8')->getFont()->setBold(true);
    $sheet->getStyle('A8:D8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');

    $rowNum = 9;
    $totalCredits = 0;
    $mCount = 0;
    $lCount = 0;

    foreach ($course_details as $code => $info) {
        $sheet->setCellValue('A' . $rowNum, $code);
        $sheet->setCellValue('B' . $rowNum, $info['name']);
        $sheet->setCellValue('C' . $rowNum, $info['level']);
        $sheet->setCellValue('D' . $rowNum, $info['credits']);
        
        if (strpos($info['level'], 'M') === 0) { $mCount++; } else { $lCount++; }
        
        $totalCredits += $info['credits'];
        $rowNum++;
    }

    if($rowNum > 9) {
        $sheet->getStyle('A8:D' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
    
    // --- SUMMARY TOTALS TABLE AT THE BOTTOM ---
    $sumStart = $rowNum + 2;
    $sheet->setCellValue('B' . $sumStart, 'ملخص المقررات:');
    $sheet->setCellValue('C' . $sumStart, 'العدد');
    
    $sheet->setCellValue('B' . ($sumStart + 1), 'عدد مقررات الإجازة:');
    $sheet->setCellValue('C' . ($sumStart + 1), $lCount);
    
    $sheet->setCellValue('B' . ($sumStart + 2), 'عدد مقررات الماستر:');
    $sheet->setCellValue('C' . ($sumStart + 2), $mCount);
    
    $sheet->setCellValue('B' . ($sumStart + 3), 'إجمالي الوحدات:');
    $sheet->setCellValue('C' . ($sumStart + 3), $totalCredits);

    // Style the small summary table
    $sheet->getStyle('B' . $sumStart . ':C' . ($sumStart + 3))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('B' . $sumStart . ':B' . ($sumStart + 3))->getFont()->setBold(true);
    $sheet->getStyle('C' . $sumStart . ':C' . ($sumStart + 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $sumStart)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
}