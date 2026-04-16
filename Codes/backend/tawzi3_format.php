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

    $departments = $_SESSION['departments'] ?? [];
    $dep = $_SESSION['dep_id'] ?? '';
    $filter = $_SESSION['excel_export_filter'] ?? [];
    $sess = $filter['sessionId'] ?? '';
    $major = $filter['excelMajor'] ?? '';
    $level = $filter['excelLevel'] ?? '';
    $d = $departments[$dep] ?? 'غير محدد';

    $file_name = "";
    $title1 = "توزيع اللجان الفاحصة - قسم $d";
    $title2 = "";
    if ($sess === 'sess2') {
        $file_name = "توزيع اللجان الفاحصة - قسم $d - الدورة الثانية";
        $title2 = "امتحانات - الدورة الثانية";
    } else {
        $sem = $sess !== '' ? ($_SESSION['semesters'][$sess] ?? 'الفصل الأول') : 'الفصل الأول';
        $file_name = "توزيع اللجان الفاحصة - قسم $d - $sem";
        $title2 = "امتحانات $sem - الدورة الأولى";
    }

    $sql = "SELECT p.prof_file_nb,
                   CONCAT(p.prof_first_name, ' ', p.prof_father_name, ' ', p.prof_last_name) AS prof_full_name,
                   c.course_code,
                   c.course_name,
                   c.course_lang,
                   c.course_level,
                   c.course_semester_nb,
                   m.major_name,
                   t.uni_year
            FROM teaching t
            JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang AND t.major_id = c.major_id
            JOIN major m ON c.major_id = m.major_id
            JOIN professor p ON t.prof_file_nb = p.prof_file_nb
            WHERE m.dep_id = ? AND t.isActive = 1";

    $paramTypes = 's';
    $params = [$dep];

    if ($major !== '' && $major !== 'all') {
        $sql .= ' AND c.major_id = ?';
        $paramTypes .= 's';
        $params[] = $major;
        $file_name .= " - $major";
    }
    if ($level !== '' && $level !== 'all') {
        $sql .= ' AND c.course_level = ?';
        $paramTypes .= 's';
        $params[] = $level;
        $file_name .= " - $level";
    }

    $sql .= ' ORDER BY p.prof_file_nb, c.course_code, c.course_lang';

    try {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new RuntimeException('Query prepare failed: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Query execute failed: ' . mysqli_stmt_error($stmt));
        }

        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        $uniYear = '';
        if (!empty($rows)) {
            $uniYear = $rows[0]['uni_year'] ?? '';
            if ($uniYear !== '') {
                $file_name .= ' ' . $uniYear;
                $title2 .= ' - ' . $uniYear;
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle($sess ?: 'توزيع');

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', $title1);
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', $title2);

        $sheet->getStyle('A1:G2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:G2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(20);

        $sheet->setCellValue('A3', 'اسم الأستاذ الثلاثي');
        $sheet->setCellValue('B3', 'رقم الملف');
        $sheet->setCellValue('C3', 'اسم المقرر');
        $sheet->setCellValue('D3', 'رمز المقرر');
        $sheet->setCellValue('E3', 'اللغة (F/E)');
        $sheet->setCellValue('F3', 'المرحلة');
        $sheet->setCellValue('G3', 'التخصص');

        $sheet->getStyle('A3:G3')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3:G3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $currentRow = 4;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $currentRow, $row['prof_full_name']);
            $sheet->setCellValue('B' . $currentRow, $row['prof_file_nb']);
            $sheet->setCellValue('C' . $currentRow, $row['course_name']);
            $sheet->setCellValue('D' . $currentRow, $row['course_code']);
            $sheet->setCellValue('E' . $currentRow, $row['course_lang']);
            $sheet->setCellValue('F' . $currentRow, $row['course_level']);
            $sheet->setCellValue('G' . $currentRow, $row['major_name']);
            $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setWrapText(true);
            $currentRow++;
        }

        $lastRow = $currentRow - 1;
        $tableStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        if ($lastRow >= 3) {
            $sheet->getStyle('A3:G' . $lastRow)->applyFromArray($tableStyle);
        }

        $fileName = $file_name . '.xlsx';
        $fallbackFileName = 'توزيع اللجان الفاحصة.xlsx';
        $tmpFile = tempnam(sys_get_temp_dir(), 'tawzi3_');
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
        $_SESSION['excel_export_error'] = 'Excel export failed: ' . $e->getMessage();
        mysqli_close($conn);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: AdminPage.php?tab=correctors');
        exit;
    }