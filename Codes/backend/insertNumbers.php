<?php
    session_start();
    ob_start();
    include __DIR__ ."/database.php";

    function isAjaxRequest() : bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    function fetchNumberRows(mysqli $conn, string $major, string $level, string $session, string $lang, string $exam): array {
        $sql_fetch = "SELECT corr.course_code, c.course_name, corr.course_lang,
                             corr.prof_file_nb, corr.second_corrector_file_nb AS second_corrector,
                             corr.partial_first_corrector, corr.partial_second_corrector,
                             corr.final_first_corrector, corr.final_second_corrector,
                             corr.session_nb
                      FROM correctors corr
                      LEFT JOIN course c ON corr.course_code = c.course_code AND c.course_lang = corr.course_lang AND c.major_id = corr.major_id
                      WHERE corr.major_id = ? AND c.course_level = ? AND corr.session_nb = ?";

        if ($lang !== "all") {
            $sql_fetch .= " AND corr.course_lang = ?";
        }

        $stmt = mysqli_prepare($conn, $sql_fetch);
        if (!$stmt) { return []; }

        if ($lang !== "all") {
            mysqli_stmt_bind_param($stmt, "ssss", $major, $level, $session, $lang);
        } else {
            mysqli_stmt_bind_param($stmt, "sss", $major, $level, $session);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return [];
        }

        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        if ($lang === "all") {
            $merged = [];
            $byCode = [];
            foreach ($rows as $row) {
                $byCode[$row['course_code']][] = $row;
            }

            foreach ($byCode as $code => $group) {
                if (count($group) === 2) {
                    $r1 = $group[0];
                    $r2 = $group[1];
                    if ($r1['prof_file_nb'] === $r2['prof_file_nb'] && 
                        $r1['second_corrector'] === $r2['second_corrector']) {
                        
                        $r1['course_lang'] = "E/F";
                        // Sum numbers based on exam type
                        if ($session === "sess2" || $exam === "F") {
                            $r1['final_first_corrector'] = $r1['final_first_corrector'] + $r2['final_first_corrector'];
                            $r1['final_second_corrector'] = $r1['final_second_corrector'] + $r2['final_second_corrector'];
                        } else {
                            $r1['partial_first_corrector'] = $r1['partial_first_corrector'] + $r2['partial_first_corrector'];
                            $r1['partial_second_corrector'] = $r1['partial_second_corrector'] + $r2['partial_second_corrector'];
                        }
                        $merged[] = $r1;
                    } else {
                        $merged[] = $r1;
                        $merged[] = $r2;
                    }
                } else {
                    $merged[] = $group[0];
                }
            }
            return $merged;
        }

        return $rows;
    }

    if (isset($_POST["cancelNumber"]) || isset($_GET["cancelNumber"])) {
        unset($_SESSION["insert_numbers_data"],
              $_SESSION["insert_numbers_filter"],
              $_SESSION["insert_numbers_error"],
              $_SESSION["insert_numbers_success"]);
        if (isset($conn)) mysqli_close($conn);
        if (ob_get_length()) ob_end_clean();
        header("location: AdminPage.php?tab=correctors");
        exit;
    }

    if (isset($_POST["findNumber"])) {
        $session = $_POST["numberSession"] ?? "";
        $exam = $_POST["numberExam"] ?? "";
        $major = $_POST["numberMajor"] ?? "";
        $level = $_POST["numberLevel"] ?? "";
        $lang = $_POST["numberLang"] ?? "";

        $_SESSION["insert_numbers_filter"] = [
            "numberSession" => $session,
            "numberExam" => $exam,
            "numberMajor" => $major,
            "numberLevel" => $level,
            "numberLang" => $lang
        ];

        $rows = fetchNumberRows($conn, $major, $level, $session, $lang, $exam);
        $_SESSION["insert_numbers_data"] = $rows;
        unset($_SESSION["insert_numbers_error"]);

        if (isAjaxRequest()) {
            ob_end_clean();
            header("Content-Type: application/json");
            echo json_encode([
                'status' => 'success',
                'courses' => $rows,
                'professors' => $_SESSION['professors'] ?? []
            ]);
            mysqli_close($conn);
            exit;
        }

        mysqli_close($conn);
        ob_end_clean();
        header("location: AdminPage.php?tab=correctors");
        exit;
    }

    if (isset($_POST["applyNumbers"])) {
        if (!isset($_SESSION["insert_numbers_filter"])) {
            $_SESSION["insert_numbers_error"] = "Session expired. Please search again.";
            header("location: AdminPage.php?tab=correctors");
            exit;
        }
        $first_numbers = $_POST["first_numbers"] ?? [];
        $second_numbers = $_POST["second_numbers"] ?? [];
        $session = $_SESSION["insert_numbers_filter"]["numberSession"] ?? "sem1";
        $exam = $_SESSION["insert_numbers_filter"]["numberExam"] ?? "F";
        $major = $_SESSION["insert_numbers_filter"]["numberMajor"] ?? "";

        $col_first = ($session === "sess2" || $exam === "F") ? "final_first_corrector" : "partial_first_corrector";
        $col_second = ($session === "sess2" || $exam === "F") ? "final_second_corrector" : "partial_second_corrector";

        $updated = 0;
        foreach ($first_numbers as $code => $langs) {
            foreach ($langs as $lang_key => $val_first) {
                $val_second = $second_numbers[$code][$lang_key] ?? 0;
                
                $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];
                $final_first = ($lang_key === "E/F") ? floor($val_first / 2) : $val_first;
                $final_second = ($lang_key === "E/F") ? floor($val_second / 2) : $val_second;

                foreach ($langs_to_update as $l) {
                    $sql = "UPDATE correctors SET $col_first = ?, $col_second = ? 
                            WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "iissss", $final_first, $final_second, $code, $l, $major, $session);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $updated++;
                    }
                }
            }
        }

        $_SESSION["insert_numbers_success"] = "Numbers updated successfully ($updated rows).";
        $_SESSION["insert_numbers_data"] = fetchNumberRows($conn, $major, $_SESSION["insert_numbers_filter"]["numberLevel"], $session, $_SESSION["insert_numbers_filter"]["numberLang"], $exam);

        mysqli_close($conn);
        ob_end_clean();
        header("location: AdminPage.php?tab=correctors");
        exit;
    }

    if (isset($_POST["deleteNumbers"])) {
        if (!isset($_SESSION["insert_numbers_filter"])) {
            $_SESSION["insert_numbers_error"] = "Session expired. Please search again.";
            header("location: AdminPage.php?tab=correctors");
            exit;
        }
        $first_numbers = $_POST["first_numbers"] ?? []; // We use the keys to know what to delete
        $session = $_SESSION["insert_numbers_filter"]["numberSession"] ?? "sem1";
        $exam = $_SESSION["insert_numbers_filter"]["numberExam"] ?? "F";
        $major = $_SESSION["insert_numbers_filter"]["numberMajor"] ?? "";

        $col_first = ($session === "sess2" || $exam === "F") ? "final_first_corrector" : "partial_first_corrector";
        $col_second = ($session === "sess2" || $exam === "F") ? "final_second_corrector" : "partial_second_corrector";

        foreach ($first_numbers as $code => $langs) {
            foreach ($langs as $lang_key => $dummy) {
                $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];
                foreach ($langs_to_update as $l) {
                    $sql = "UPDATE correctors SET $col_first = 0, $col_second = 0 
                            WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "ssss", $code, $l, $major, $session);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }

        $_SESSION["insert_numbers_success"] = "Numbers reset to 0.";
        $_SESSION["insert_numbers_data"] = fetchNumberRows($conn, $major, $_SESSION["insert_numbers_filter"]["numberLevel"], $session, $_SESSION["insert_numbers_filter"]["numberLang"], $exam);

        mysqli_close($conn);
        ob_end_clean();
        header("location: AdminPage.php?tab=correctors");
        exit;
    }

    mysqli_close($conn);
    ob_end_clean();
    header("location: AdminPage.php?tab=correctors");
    exit;
?>