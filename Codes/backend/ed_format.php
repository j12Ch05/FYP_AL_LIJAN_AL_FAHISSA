<?php
ob_start();
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();
include __DIR__ .'/database.php';

if (isset($_POST['exportExcel'])) {
    if (!isset($_SESSION['email'])) {
        header('Location: login.php');
        exit;
    }

    $email = $_SESSION['email'];
    $filter = $_SESSION['excel_export_filter'] ?? [];
    $session = $filter["sessionId"];
    $year = $filter["excelYear"];
    $professor = [];
    // 1. Get Professor Info
    $sql_prof = "SELECT p.prof_file_nb, p.prof_first_name, p.prof_father_name, p.prof_last_name, p.prof_category,d.dep_id, d.dep_name FROM professor p JOIN department d ON p.dep_id = d.dep_id JOIN professor a ON p.dep_id = a.dep_id WHERE a.prof_email = ?";
    $stmt_prof = mysqli_prepare($conn, $sql_prof);
    mysqli_stmt_bind_param($stmt_prof, 's', $email);
    mysqli_stmt_execute($stmt_prof);
    $result_prof = mysqli_stmt_get_result($stmt_prof);
    while($row = mysqli_fetch_assoc($result_prof)){
        $professor[$row["prof_file_nb"]] = $row;
    }
    mysqli_stmt_close($stmt_prof);

    if (!$professor) {
        die('Professor not found.');
    }
    $dep_id = $professor["dep_id"];
    $departmentName = $professor["dep_name"] ?? 'General';
    $prof_id = $professor["prof_file_nb"];

    // 2. Get Courses and Correctors
    $sql_courses = "SELECT corr.prof_file_nb , corr.second_corrector_file_nb as second_corrector,
                   corr.course_code,c.course_name,corr.course_lang,c.course_level,corr.session_nb,
                   m.major_name,t.uni_year,d.dep_name
                   FROM correctors corr
                   JOIN teaching t ON t.course_code = corr.course_code AND t.course_lang = corr.course_lang AND t.prof_file_nb = corr.prof_file_nb
                   AND t.major_id = corr.major_id
                   JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang AND c.major_id = t.major_id
                   JOIN major m ON c.major_id = m.major_id  
                   JOIN professor p ON p.prof_file_nb = corr.prof_file_nb
                   JOIN department d ON d.dep_id = p.dep_id
                   WHERE corr.session_nb = ? AND d.dep_id = ? AND corr.uni_year = ?";
    
    $stmt_c = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_c, "sss",$session,$dep_id,$year);
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
            'first_corrector' => $row["prof_file_nb"],
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
            'major' => $row["major_name"],
            'credits' => $row["course_credit_nb"]
        ];
    }
    mysqli_stmt_close($stmt_c);

    $spreadsheet = new Spreadsheet();

    // Generate Sheets
    // createCorrectionSheet($spreadsheet->getActiveSheet(), $professor, $departmentName, $data["sem1"], 'جزئي', 'الأولى', 'الأول');
    // $spreadsheet->getActiveSheet()->setTitle('S1_Partiel');

    foreach($professor as $arr){
        createCorrectionSheet($spreadsheet->getActiveSheet(), $professor, $departmentName, $data["sem1"], 'جزئي', 'الأولى', 'الأول');
        $spreadsheet->getActiveSheet()->setTitle($arr["prof_first_name"] . " ". $arr["prof_last_name"]);
    }

    if (ob_get_length()) ob_end_clean();

    $fileName =  'اضبارة تصحيح مسابقات' . date('Y-m-d_H-i') . '.xlsx';
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
            if($c['first_corrector'] == $professor["prof_file_nb"]){
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][0]);
            }
            else{
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][1]);
            }
        } else {
            if($c['first_corrector'] == $professor["prof_file_nb"]){
                $sheet->setCellValue([7 + $colOffset, $rowNum], $c['correctors'][2]);
            }
            else{
                $sheet->setCellValue([8 + $colOffset, $rowNum], $c['correctors'][3]);
            }
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

