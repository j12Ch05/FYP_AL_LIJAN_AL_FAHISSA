<?php
    ob_start();
    ini_set('display_errors', '1');
    require __DIR__ . '/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;

    session_start();
    include __DIR__ . '/database.php';

    $professors = $_SESSION["prof_full_names"];
    $departments = $_SESSION["departments"];
    $dep = $_SESSION["dep_id"];
    $semesters = $_SESSION["semesters"];

    //in this format we need just the session number
    $sess = $_SESSION["excel_export_filter"]["sessionId"];
    $d = $departments[$dep] ?? "غير محدد";

    $file_name="";
    $title1= "الجامعة اللبنانية - كلية العلوم الفرع الثاني";
    $dep_name = "قسم: $d";
    $title2 = "";
    $title3 = "";
    if($sess == "sess2"){
        $file_name = "مجموع اضبارات التصحيح الدورة الثانية - قسم $d ";
        $title2 = " جدول صرف تعويضات لجان فاحصة (تصحيح مسابقات سنوات الإجازة) الدورة الثانية للعام الجامعي";
        $title3 = " جدول صرف تعويضات لجان فاحصة (تصحيح مسابقات ماستر 1) الدورة الثانية للعام الجامعي";
    }
    else{
        $sem = $semesters[$sess];
         $file_name = "مجموع اضبارات التصحيح $sem - قسم $d ";
        $title2 = " جدول صرف تعويضات لجان فاحصة (تصحيح مسابقات سنوات الإجازة) $sem  للعام الجامعي";
        $title3 = " جدول صرف تعويضات لجان فاحصة (تصحيح مسابقات ماستر 1) $sem للعام الجامعي";
    }


    $sql = "SELECT corr.prof_file_nb as prof_file_nb,
                   corr.second_corrector_file_nb as second_corrector,
                   corr.third_corrector_file_nb as third_corrector,
                   t.uni_year
            FROM correctors corr
            LEFT JOIN teaching t ON t.course_code = corr.course_code AND t.course_lang = corr.course_lang
            LEFT JOIN course c ON c.course_code = t.course_code AND c.course_lang = t.course_lang
            LEFT JOIN major m ON m.major_id = c.major_id
            WHERE m.dep_id = ? and corr.session_nb = ?";

    $stmt = mysqli_prepare($conn,$sql);
    if(!$stmt){
        $_SESSION["excel_export_error"] = "Query prepare failed: " . mysqli_error($conn);
        mysqli_close($conn);
        if (ob_get_length()) {
            ob_end_clean();
        }
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }
    else{
        mysqli_stmt_bind_param($stmt,"ss",$dep,$sess);
    }


    $rows = [];
    $corrProfs = [];
    if(!mysqli_stmt_execute($stmt)){
        $_SESSION["excel_export_error"] = "Query execute failed: " . mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        if (ob_get_length()) {
            ob_end_clean();
        }
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }
    else{
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($result)){
            $rows[] = $row;
        }

        $corrProfs = array_fill_keys(array_column($rows, 'prof_file_nb'), 1);
        mysqli_stmt_close($stmt);


    }

    foreach ($rows as $row) {
            $corrProfs[$row['prof_file_nb']]++;
            if (!empty($row['second_corrector_file_nb'])) {
                $corrProfs[$row['second_corrector_file_nb']]++;
            }
            if (!empty($row['third_corrector_file_nb'])) {
                $corrProfs[$row['third_corrector_file_nb']]++;
            }
        }


    if (empty($rows)) {
        die("No data found for the selected session and department.");
    }

    $file_name .= " " . $rows[0]["uni_year"];
    $title2 .= " " . $rows[0]["uni_year"];
    $title3 .= " " . $rows[0]["uni_year"];


    //creating the spreadsheet
    try{
        //creating the first sheet for the license
        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setRightToLeft(true);
        $sheet1->setTitle("اجازة");

        //page titles
        $sheet1->mergeCells('A1:D1');
        $sheet1->setCellValue('A1',$title1);
        $sheet1->mergeCells('A2:C2');
        $sheet1->setCellValue('A2',$dep_name);

        $sheet1->getStyle('A1:K2')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);




        $fileName = $file_name . '.xlsx';
        $fallbackFileName = "edbarat-format.xlsx";
        $tmpFile = tempnam(sys_get_temp_dir(), 'edbarat_');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to create temporary file for export.');
        }





        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$fallbackFileName}\"; filename*=UTF-8''" . rawurlencode($fileName));
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Pragma: public');

        readfile($tmpFile);
        @unlink($tmpFile);
        mysqli_close($conn);
        exit;

    }catch(Throwable $e){
        $_SESSION["excel_export_error"] = "Excel export failed: " . $e->getMessage();
        mysqli_close($conn);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }


    
    