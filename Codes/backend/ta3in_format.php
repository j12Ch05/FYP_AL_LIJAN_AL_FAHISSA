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

        
    $professors = $_SESSION["prof_full_names"];
    $departments = $_SESSION["departments"];
    $dep = $_SESSION["dep_id"];
    $semesters = $_SESSION["semesters"];
    $courses = $_SESSION["courses"];

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
        $file_name = "تعيين اللجان الفاحصة - قسم $d - -$sem  ";
        $title2 = "لامتحانات $sem  - الدورة الاولى ";
    }

    $sql = "SELECT     c.course_code,
                       c.course_name,
                       c.course_lang,
                       corr.prof_file_nb AS prof_file_nb,
                       corr.second_corrector_file_nb,
                       corr.third_corrector_file_nb,
                       t.uni_year
                FROM course c
                LEFT JOIN correctors corr ON c.course_code = corr.course_code
                    AND c.course_lang = corr.course_lang
                    AND c.major_id = corr.major_id
                    AND corr.session_nb = ?
                LEFT JOIN teaching t ON c.course_code = t.course_code
                    AND c.course_lang = t.course_lang
                    AND c.major_id = t.major_id
                    AND t.isActive = 1
                LEFT JOIN professor p ON p.prof_file_nb = t.prof_file_nb
                ";
        //s is a variable to know which parameters we need
        $s = 0;

    if($level == "all" && $major == "all"){
        $sql .= "WHERE p.dep_id = ?";
        $s = 0;
    }
    else if($level == "all"){
        $sql .= "WHERE c.major_id = ? AND  p.dep_id = ?";
        $file_name .= $major;
        $s = 1;
    }
    else if($major == "all"){
        $sql .= "WHERE c.course_level = ? AND  p.dep_id = ?";
        $file_name .= $level;
        $s = 2;
    }
    else{
        $sql .= "WHERE c.major_id = ? AND c.course_level = ? AND  p.dep_id = ?";
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
        mysqli_stmt_bind_param($stmt,"sss",$sess,$major,$dep);
    }
    else if($s == 2){
        mysqli_stmt_bind_param($stmt,"sss",$sess,$level,$dep);
    }
    else if($s == 3){
        mysqli_stmt_bind_param($stmt,"ssss",$sess,$major,$level,$dep);
    }
    else {
        mysqli_stmt_bind_param($stmt,"ss",$sess,$dep);
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
        // Keys: prof_file_nb. Values: [0] = committee count, [1] = ['E' => course codes, 'F' => course codes]
        $profIds = array_filter(array_unique(array_column($rows, 'prof_file_nb')), static function ($v) {
            return $v !== null && $v !== '';
        });
        $langBuckets = static function () {
            return ['E' => [], 'F' => []];
        };
        $corrProfs = [];
        foreach ($profIds as $pid) {
            $corrProfs[(string)$pid] = [0, $langBuckets()];
        }
        mysqli_stmt_close($stmt);
    }

    $addCorrectorAssignment = function (&$map, $profId, $courseCode, $courseLang) use ($sess) {
        if ($profId === null || $profId === '') {
            return;
        }
        if ($sess === '' || $courseCode === '') {
            return;
        }
        $lang = strtoupper(trim((string)$courseLang));
        if ($lang !== 'E' && $lang !== 'F') {
            $lang = 'E';
        }
        $profKey = (string)$profId;
        if (!isset($map[$profKey])) {
            $map[$profKey] = [0, ['E' => [], 'F' => []]];
        }
        $map[$profKey][0]++;
        if (!in_array($courseCode, $map[$profKey][1][$lang], true)) {
            $map[$profKey][1][$lang][] = $courseCode;
        }
    };

    foreach ($rows as $row) {
        $courseCode = $row["course_code"] ?? '';
        $courseLang = $row["course_lang"] ?? 'E';
        $addCorrectorAssignment($corrProfs, $row['prof_file_nb'] ?? null, $courseCode, $courseLang);
        $addCorrectorAssignment($corrProfs, $row['second_corrector_file_nb'] ?? null, $courseCode, $courseLang);
        $addCorrectorAssignment($corrProfs, $row['third_corrector_file_nb'] ?? null, $courseCode, $courseLang);
    }

    $uniYear = $rows[0]["uni_year"] ?? '';
    if ($uniYear !== '') {
        $file_name .= " " . $uniYear;
        $title2 .= " " . $uniYear;
    }

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
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getRowDimension('3')->setRowHeight(50);

        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        
 
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

        //filling the table 
        $startRow = 4;
        $cell = $startRow;
        foreach($corrProfs as $profFileNb => $profData){
            $height = 30;
            $linesC = [];
            $linesD = [];
            $linesE = [];
            $byLang = $profData[1] ?? ['E' => [], 'F' => []];
            $perCourse = [];
            foreach (['E', 'F'] as $lg) {
                foreach ($byLang[$lg] ?? [] as $code) {
                    $codeKey = (string)$code;
                    if (!isset($perCourse[$codeKey])) {
                        $perCourse[$codeKey] = ['E' => false, 'F' => false];
                    }
                    $perCourse[$codeKey][$lg] = true;
                }
            }
            ksort($perCourse, SORT_STRING);
            foreach ($perCourse as $codeKey => $flags) {
                $hasE = !empty($flags['E']);
                $hasF = !empty($flags['F']);
                if ($hasE && $hasF) {
                    $linesD[] = 'F/E';
                } elseif ($hasF) {
                    $linesD[] = 'F';
                } else {
                    $linesD[] = 'E';
                }
                $cname = $courses['E'][$codeKey] ?? $courses['F'][$codeKey] ?? '';
                $linesC[] = $cname;
                $linesE[] = $codeKey;
                $height += 12;
            }
            $textC = implode("\n", $linesC);
            $textD = implode("\n", $linesD);
            $textE = implode("\n", $linesE);

            $sheet->setCellValue('A'.$cell , (string)$profFileNb);
            $sheet->setCellValue('B'.$cell , (string)($professors[$profFileNb] ?? 'Unknown'));
            $sheet->setCellValue('C'.$cell , $textC);
            $sheet->setCellValue('D'.$cell , $textD);
            $sheet->setCellValue('E'.$cell , $textE);
            $sheet->setCellValue('F'.$cell , (int)count($linesE));
            $alignTop = \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP;
            $sheet->getStyle('C'.$cell)->getAlignment()->setWrapText(true)->setVertical($alignTop);
            $sheet->getStyle('D'.$cell)->getAlignment()->setWrapText(true)->setVertical($alignTop);
            $sheet->getStyle('E'.$cell)->getAlignment()->setWrapText(true)->setVertical($alignTop);
            $sheet->getRowDimension(2)->setRowHeight(-1);
            $cell++;
        }

        $lastRow = $sheet->getHighestRow(); // Gets the last row that has data
        $sheet->getStyle('A3:F' . $lastRow)->applyFromArray($tableStyle);
        $sheet->getStyle('A4:A'.$cell)->getFont()->setSize(12);
        $sheet->getStyle('B4:B'.$cell)->getFont()->setSize(14);
        $sheet->getStyle('F4:F'.$cell)->getFont()->setSize(12);

        

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
