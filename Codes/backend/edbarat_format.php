<?php
    ob_start();
    ini_set('display_errors', '1');
    require __DIR__ . '/vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Font;

    session_start();
    include __DIR__ . '/database.php';

    $professors = $_SESSION["prof_full_names"];
    $departments = $_SESSION["departments"];
    $dep = $_SESSION["dep_id"];
    $semesters = $_SESSION["semesters"];

    //in this format we need just the session number
    $sess = $_SESSION["excel_export_filter"]["sessionId"];
    $d = $departments[$dep] ?? "ﻏﻴﺮ ﻣﺤﺪﺩ";

    $file_name="";
    $title1= "اﻟﺠﺎﻣﻌﺔ اﻟﻠﺒﻨﺎﻧﻴﺔ -  كلية العلوم الفرع الثاني";
    $dep_name = "القسم: $d";
    $title2 = "";
    $title3 = "";
    if($sess == "sess2"){
        $file_name = "ﻣﺠﻤﻮﻉ اﺿﺒﺎﺭاﺕ اﻟﺘﺼﺤﻴﺢ اﻟﺪﻭﺭﺓ اﻟﺜﺎﻧﻴﺔ - ﻗﺴﻢ $d ";
        $title2 = " ﺟﺪﻭﻝ ﺻﺮﻑ ﺗﻌﻮﻳﻀﺎﺕ ﻟﺠﺎﻥ ﻓﺎﺣﺼﺔ (تصحيح مسابقات سنوات الإجازة) اﻟﺪﻭﺭﺓ اﻟﺜﺎﻧﻴﺔ ﻟﻠﻌﺎﻡ اﻟﺠﺎﻣﻌﻲ";
        $title3 = " ﺟﺪﻭﻝ ﺻﺮﻑ ﺗﻌﻮﻳﻀﺎﺕ ﻟﺠﺎﻥ ﻓﺎﺣﺼﺔ (ﺗﺼﺤﻴﺢ ﻣﺴﺎﺑﻘﺎﺕ ﻣﺎﺳﺘﺮ1) اﻟﺪﻭﺭﺓ اﻟﺜﺎﻧﻴﺔ ﻟﻠﻌﺎﻡ اﻟﺠﺎﻣﻌﻲ";
    }
    else{
        $sem = $semesters[$sess];
         $file_name = "ﻣﺠﻤﻮﻉ اﺿﺒﺎﺭاﺕ اﻟﺘﺼﺤﻴﺢ $sem - ﻗﺴﻢ $d ";
        $title2 = " جدول صرف تعويضات لجان فاحصة (تصحيح مسابقات سنوات الإجازة)$sem  ﻟﻠﻌﺎﻡ اﻟﺠﺎﻣﻌﻲ";
        $title3 = " ﺟﺪﻭﻝ ﺻﺮﻑ ﺗﻌﻮﻳﻀﺎﺕ ﻟﺠﺎﻥ ﻓﺎﺣﺼﺔ (ﺗﺼﺤﻴﺢ ﻣﺴﺎﺑﻘﺎﺕ ﻣﺎﺳﺘﺮ 1) $sem ﻟﻠﻌﺎﻡ اﻟﺠﺎﻣﻌﻲ";
    }


    $sql = "SELECT corr.prof_file_nb as prof_file_nb,
                   corr.second_corrector_file_nb as second_corrector,
                   corr.third_corrector_file_nb as third_corrector,
                   t.uni_year,c.course_level
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
    $corrProfL = [];//the list of the correctors in L1/L2/L3
    $corrProfM = [];//the list of the correctors in M1
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

        mysqli_stmt_close($stmt);
    }

    $corrProfL = [];
    $corrProfM = [];

    foreach ($rows as $row) {
        // Checking if course level indicates a License course (e.g. L1, L2, L3)
        $isLicense = ($row["course_level"] == "L1" || $row["course_level"] == "L2" || $row["course_level"] == "L3");
        
        $prof1 = $row['prof_file_nb'];
        $prof2 = !empty($row['second_corrector']) ? $row['second_corrector'] : null;
        $prof3 = !empty($row['third_corrector']) ? $row['third_corrector'] : null;
        
        if ($isLicense) {
            if (!isset($corrProfL[$prof1])) { $corrProfL[$prof1] = 0; }
            $corrProfL[$prof1]++;
            
            if ($prof2 !== null) {
                if (!isset($corrProfL[$prof2])) { $corrProfL[$prof2] = 0; }
                $corrProfL[$prof2]++;
            }
            
            if ($prof3 !== null) {
                if (!isset($corrProfL[$prof3])) { $corrProfL[$prof3] = 0; }
                $corrProfL[$prof3]++;
            }
        } else {
            // Master courses
            if (!isset($corrProfM[$prof1])) { $corrProfM[$prof1] = 0; }
            $corrProfM[$prof1]++;
            
            if ($prof2 !== null) {
                if (!isset($corrProfM[$prof2])) { $corrProfM[$prof2] = 0; }
                $corrProfM[$prof2]++;
            }
            
            if ($prof3 !== null) {
                if (!isset($corrProfM[$prof3])) { $corrProfM[$prof3] = 0; }
                $corrProfM[$prof3]++;
            }
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
        $sheet1->setTitle("اﺟﺎﺯﺓ");

        //formatting the sheet
        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        $sheet1->mergeCells('A1:D1');
        $sheet1->setCellValue('A1',$title1);
        $sheet1->mergeCells('A2:C2');
        $sheet1->setCellValue('A2',$dep_name);

        $sheet1->getStyle('A1:D1')->getFont()->setBold(true)->setSize(18);
        $sheet1->getStyle('A2:C2')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A1:D2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getRowDimension('1')->setRowHeight(23.4);
        $sheet1->getRowDimension('2')->setRowHeight(18);
        $sheet1->getRowDimension('5')->setRowHeight(36);
        $sheet1->getRowDimension('6')->setRowHeight(65.3);
        $sheet1->getColumnDimension('A')->setWidth(8.11); 
        $sheet1->getColumnDimension('B')->setWidth(17.33);   
        $sheet1->getColumnDimension('C')->setWidth(15); 
        $sheet1->getColumnDimension('D')->setWidth(15);  
        $sheet1->getColumnDimension('E')->setWidth(15);  
        $sheet1->getColumnDimension('F')->setWidth(15);  
        $sheet1->getColumnDimension('G')->setWidth(17.11);  
        $sheet1->getColumnDimension('H')->setWidth(8.33);  
        $sheet1->getColumnDimension('I')->setWidth(16.22);  

        

        //underlined text
        $sheet1->mergeCells("A4:G4");
        // Use RichText to color the last part of the text (the year) differently
        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        
        // Separate the text into normal part and the part to color
        $mainPart = str_replace($rows[0]["uni_year"], "", $title2);
        $coloredPart = $rows[0]["uni_year"];
        
        // 1. Normal text run
        $run1 = $richText->createTextRun($mainPart);
        $run1->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE);
        
        // 2. Colored text run (Red)
        $run2 = $richText->createTextRun($coloredPart);
        $run2->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE)->getColor()->setARGB('FFFF0000');
        
        $sheet1->setCellValue("A4", $richText);
        $sheet1->getStyle('A4:G4')->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE);
        $sheet1->getRowDimension('4')->setRowHeight(25.5);

        $fileName = $file_name . '.xlsx';
        $fallbackFileName = "edbarat-format.xlsx";
        $tmpFile = tempnam(sys_get_temp_dir(), 'edbarat_');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to create temporary file for export.');
        }

        //adding the value of the column in row 5
        $sheet1->setCellValue("C5",'A');
        $sheet1->setCellValue("D5",'B');
        $sheet1->setCellValue("E5",'C');
        $sheet1->setCellValue("F5",'D');
        $sheet1->setCellValue("G5",'Ax0.5+Bx0.25+C+Dx0.5');

        $sheet1->getStyle('A5:I5')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A5:I5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('G5')->getAlignment()->setWrapText(true);

        //adding the name of the column in the table
        $sheet1->setCellValue('A6',"رقم الملف");
        $sheet1->setCellValue('B6',"اسم الأستاذ");
        $sheet1->setCellValue('C6',"عدد المسابقات \nمصحح اول جزئي");
        $sheet1->setCellValue('D6',"عدد المسابقات \nمصحح ثان جزئي");
        $sheet1->setCellValue('E6',"عدد المسابقات \nمصحح اول نهائي");
        $sheet1->setCellValue('F6',"عدد المسابقات \nمصحح ثان نهائي");
        $sheet1->setCellValue('G6',"المجموع مع التثقيل");
        $sheet1->setCellValue('H6',"عدد اللجان المقترحة");
        $sheet1->setCellValue('I6',"عدد اللجان الفاحصة المقبوضة سابقاً في كافة وحدات الجامعة");

        $sheet1->getStyle('A6:G6')->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('H6:I6')->getFont()->setSize(12);
        $sheet1->getStyle('A6:I6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('A6:I6')->getAlignment()->setWrapText(true);

        $r = 7;
        foreach($corrProfL as $profFileNb => $value) {
            $formula = "=ROUNDDOWN((C{$r}*0.5+D{$r}*0.25+E{$r}+F{$r}*0.5),0)";
            $sheet1->setCellValue('A'.$r,$profFileNb);
            $sheet1->setCellValue('B'.$r,$professors[$profFileNb]);
            $sheet1->setCellValue('G'.$r,$formula);
            $sheet1->setCellValue('H'.$r,$value);
            $sheet1->setCellValue('I'.$r,0);
            $sheet1->getRowDimension($r)->setRowHeight(18);
            $sheet1->getStyle('A'.$r.':I'.$r)->getFont()->setSize(13);
            $sheet1->getStyle('A'.$r.':I'.$r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle('A'.$r.':I'.$r)->getAlignment()->setWrapText(true);
            $r++;
        }




        $lastRow = $sheet1->getHighestRow(); // Gets the last row that has data
        $sheet1->getStyle('A5:I' . $lastRow)->applyFromArray($tableStyle);

        //the second sheet
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setRightToLeft(true);
        $sheet2->setTitle("ماستر");


        $sheet2->mergeCells('A1:D1');
        $sheet2->setCellValue('A1',$title1);
        $sheet2->mergeCells('A2:C2');
        $sheet2->setCellValue('A2',$dep_name);

        $sheet2->getStyle('A1:D1')->getFont()->setBold(true)->setSize(18);
        $sheet2->getStyle('A2:C2')->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle('A1:D2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet2->getRowDimension('1')->setRowHeight(23.4);
        $sheet2->getRowDimension('2')->setRowHeight(18);
        $sheet2->getRowDimension('5')->setRowHeight(36);
        $sheet2->getRowDimension('6')->setRowHeight(65.3);
        $sheet2->getColumnDimension('A')->setWidth(8.11); 
        $sheet2->getColumnDimension('B')->setWidth(17.33);   
        $sheet2->getColumnDimension('C')->setWidth(15); 
        $sheet2->getColumnDimension('D')->setWidth(15);  
        $sheet2->getColumnDimension('E')->setWidth(15);  
        $sheet2->getColumnDimension('F')->setWidth(15);  
        $sheet2->getColumnDimension('G')->setWidth(17.11);  
        $sheet2->getColumnDimension('H')->setWidth(8.33);  
        $sheet2->getColumnDimension('I')->setWidth(16.22);  

        

        //underlined text
        $sheet2->mergeCells("A4:G4");
        // Use RichText to color the last part of the text (the year) differently
        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        
        // Separate the text into normal part and the part to color
        $mainPart = str_replace($rows[0]["uni_year"], "", $title3);
        $coloredPart = $rows[0]["uni_year"];
        
        // 1. Normal text run
        $run1 = $richText->createTextRun($mainPart);
        $run1->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE);
        
        // 2. Colored text run (Red)
        $run2 = $richText->createTextRun($coloredPart);
        $run2->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE)->getColor()->setARGB('FFFF0000');
        
        $sheet2->setCellValue("A4", $richText);
        $sheet2->getStyle('A4:G4')->getFont()->setBold(true)->setSize(14)->setUnderline(Font::UNDERLINE_SINGLE);
        $sheet2->getRowDimension('4')->setRowHeight(25.5);


        //adding the value of the column in row 5
        $sheet2->setCellValue("C5",'A');
        $sheet2->setCellValue("D5",'B');
        $sheet2->setCellValue("E5",'C');
        $sheet2->setCellValue("F5",'D');
        $sheet2->setCellValue("G5",'Ax0.5+Bx0.25+C+Dx0.5');


        $sheet2->getStyle('A5:I5')->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle('A5:I5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('G5')->getAlignment()->setWrapText(true);

        //adding the name of the column in the table
        $sheet2->setCellValue('A6',"رقم الملف");
        $sheet2->setCellValue('B6',"اسم الأستاذ");
        $sheet2->setCellValue('C6',"عدد المسابقات \nمصحح اول جزئي");
        $sheet2->setCellValue('D6',"عدد المسابقات \nمصحح ثان جزئي");
        $sheet2->setCellValue('E6',"عدد المسابقات \nمصحح اول نهائي");
        $sheet2->setCellValue('F6',"عدد المسابقات \nمصحح ثان نهائي");
        $sheet2->setCellValue('G6',"المجموع مع التثقيل");
        $sheet2->setCellValue('H6',"عدد اللجان المقترحة");
        $sheet2->setCellValue('I6',"عدد اللجان الفاحصة المقبوضة سابقاً في كافة وحدات الجامعة");

        $sheet2->getStyle('A6:G6')->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle('H6:I6')->getFont()->setSize(12);
        $sheet2->getStyle('A6:I6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('A6:I6')->getAlignment()->setWrapText(true);

        //filling the tables with the fetched data
        $excelRow = 7;
        $r = $excelRow;
        foreach($corrProfM as $profFileNb => $value) {
            $formula = "=ROUNDDOWN((C{$r}*0.5+D{$r}*0.25+E{$r}+F{$r}*0.5),0)";
            $sheet2->setCellValue('A'.$r,$profFileNb);
            $sheet2->setCellValue('B'.$r,$professors[$profFileNb]);
            $sheet2->setCellValue('G'.$r,$formula);
            $sheet2->setCellValue('H'.$r,$value);
            $sheet2->setCellValue('I'.$r,0);
            $sheet2->getRowDimension($r)->setRowHeight(18);
            $sheet2->getStyle('A'.$r.':I'.$r)->getFont()->setSize(13);
            $sheet2->getStyle('A'.$r.':I'.$r)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet2->getStyle('A'.$r.':I'.$r)->getAlignment()->setWrapText(true);
            $r++;
        }

        $lastRow2 = $sheet2->getHighestRow();
        $sheet2->getStyle('A5:I' . $lastRow2)->applyFromArray($tableStyle);

        // Set the active sheet back to the first one
        $spreadsheet->setActiveSheetIndex(0);

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


    
    