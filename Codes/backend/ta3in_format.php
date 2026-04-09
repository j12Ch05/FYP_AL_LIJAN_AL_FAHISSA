<?php
    ob_start();
    ini_set('display_errors', '0');
    require __DIR__ . '/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;

    session_start();
    include __DIR__ . '/database.php';

    //fetching the professors full name and the department
    $email = $_SESSION["email"];
    
    $sql_professors = "SELECT p.prof_file_nb,p.prof_first_name,p.prof_father_name,p.prof_last_name,a.dep_id FROM professor p Join professor a on a.dep_id = p.dep_id where a.prof_email= ?";
    $stmt_p = mysqli_prepare($conn, $sql_professors);
    mysqli_stmt_bind_param($stmt_p,"s",$email);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);

    $professors = [];
    while($row = mysqli_fetch_assoc($res_p)){
        $professors[$row["prof_file_nb"]] = $row["prof_first_name"] . " " . $row["prof_father_name"] . " " . $row["prof_last_name"];
        $dep = $row["dep_id"];
    }
    mysqli_stmt_close($stmt_p);
    
    $departments = [
        "bio" => "علوم الأحياء",
        "bioch" => "الكيمياء الحيوية",
        "che" => "كيمياء",
        "pe" => "الفيزياء والإلكترونيك",
        "css" => "المعلوماتية والاحصاء",
        "math" => "الرياضيات"
    ];

    $semesters = [
        "sem1" => "الفصل  الأول",
        "sem2" => "الفصل الثاني"
    ];


    //fetching the required data to fill the excel 
    $filter = $_SESSION["excel_export_filter"];
    $sess = $filter["sessionId"];
    $major = $filter["excelMajor"];
    $level = $filter["excelLevel"];

    $d = $departments[$dep] ?? "غير محدد";
    

    $file_name = "";
    $title1 = "تعيين اللجان الفاحصة  - قسم  $d ";
    $title2="";
    if($sess == "sess2"){
        $file_name = "تعيين اللجان الفاحصة  - قسم  $d  - الدورة  الثانية - ";
        $title2 = "لامتحانات  - الدورة الثانية  ";
    }
    else{
        $sem = $semesters[$sess];
        $file_name = "تعيين اللجان الفاحصة - قسم $d - -$sem الدورة  الاولى  ";
        $title2 = "لامتحانات $sem  - الدورة الاولى ";
    }

    $sql = "SELECT     c.course_code,
                       c.course_name,
                       COALESCE(corr.prof_file_nb, t.prof_file_nb) AS prof_file_nb,
                       corr.second_corrector_file_nb,
                       corr.third_corrector_file_nb,
                       t.uni_year
                FROM course c
                LEFT JOIN correctors corr ON c.course_code = corr.course_code
                    AND c.course_lang = corr.course_lang
                    AND corr.session_nb = ?
                LEFT JOIN teaching t ON c.course_code = t.course_code
                    AND c.course_lang = t.course_lang
                    AND t.isActive = 1
                ";
        //s is a variable to know which parameters we need
        $s = 0;

    if($level == "all" && $major == "all"){
        $s = 0;
    }
    else if($level == "all"){
        $sql .= "WHERE c.major_id = ?";
        $file_name .= $major;
        $s = 1;
    }
    else if($major == "all"){
        $sql .= "WHERE c.course_level = ?";
        $file_name .= $level;
        $s = 2;
    }
    else{
        $sql .= "WHERE c.major_id = ? AND c.course_level = ?";
        $file_name .= $level ." - " . $major;
        $s = 3;
    }

    $rows = [];
    $corrProfs = [];
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
    else if($s == 1){
        mysqli_stmt_bind_param($stmt,"ss",$sess,$major);
    }
    else if($s == 2){
        mysqli_stmt_bind_param($stmt,"ss",$sess,$level);
    }
    else if($s == 3){
        mysqli_stmt_bind_param($stmt,"sss",$sess,$major,$level);
    }
    else {
        mysqli_stmt_bind_param($stmt,"s",$sess);
    }

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
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        //list of the professors file number and how many correctors list they are in 
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

    $file_name .= " " . $rows[0]["uni_year"];
    $title2 .= " " . $rows[0]["uni_year"];

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle($sess);

        // Page header
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', $title1);
        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', $title2);

        $sheet->getStyle('A1:K2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        //drawing the table
        $sheet->getColumnDimension('A')->setWidth(10); 
        $sheet->getColumnDimension('B')->setWidth(30);   
        $sheet->getColumnDimension('C')->setWidth(40); 
        $sheet->getColumnDimension('D')->setWidth(10);  
        $sheet->getColumnDimension('E')->setWidth(10);   
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getRowDimension('3')->setRowHeight(50);
 
        $sheet->setCellValue('A3','رقم الملف');
        $sheet->setCellValue('B3','اسم الاستاذ الثلاثي');
        $sheet->setCellValue('C3','اسم المقرر');
        $sheet->setCellValue('D3','اللغة (F/E)');
        $sheet->setCellValue('E3','رقم المقرر');
        $sheet->setCellValue('F3','عدد اللجان');

        $sheet->getStyle('A3:F3')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $fileName = $file_name . '.xlsx';
        $fallbackFileName = "ta3in-format.xlsx";
        $tmpFile = tempnam(sys_get_temp_dir(), 'ta3in_');
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
    } catch (Throwable $e) {
        $_SESSION["excel_export_error"] = "Excel export failed: " . $e->getMessage();
        mysqli_close($conn);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }
