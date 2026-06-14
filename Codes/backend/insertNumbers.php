<?php
    session_start();
    ob_start();
    include __DIR__ ."/database.php";

    function isAjaxRequest() : bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    function fetchNumberRows(mysqli $conn, string $major, string $level, string $session, string $lang, string $exam, string $uniYear): array {
        $sql_fetch = "SELECT corr.course_code, c.course_name, corr.course_lang,
                             corr.major_id, c.course_level, corr.uni_year,
                             corr.prof_file_nb, p.prof_first_name, p.prof_last_name,
                             corr.second_corrector_file_nb AS second_corrector,
                             corr.partial_first_corrector, corr.partial_second_corrector,
                             corr.final_first_corrector, corr.final_second_corrector,
                             corr.session_nb
                      FROM correctors corr
                      LEFT JOIN course c ON corr.course_code = c.course_code AND corr.course_lang = c.course_lang AND corr.major_id = c.major_id AND corr.uni_year = c.uni_year
                      LEFT JOIN professor p ON p.prof_file_nb = corr.prof_file_nb
                      WHERE corr.session_nb = ? AND corr.uni_year = ?";

        $paramTypes = 'ss';
        $param = [$session, $uniYear];

        if ($major !== "all") {
            $sql_fetch .= " AND corr.major_id = ?";
            $paramTypes .= "s";
            $param[] = $major;
        }
        if ($level !== "all") {
            $sql_fetch .= " AND c.course_level = ?";
            $paramTypes .= "s";
            $param[] = $level;
        }
        if ($lang !== "all") {
            $sql_fetch .= " AND corr.course_lang = ?";
            $paramTypes .= "s";
            $param[] = $lang;
        }

        $sql_fetch .= " ORDER BY corr.major_id ASC, corr.course_code ASC, corr.course_lang ASC";

        $stmt = mysqli_prepare($conn, $sql_fetch);
        if (!$stmt) { return []; }

        mysqli_stmt_bind_param($stmt, $paramTypes, ...$param);

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
            $byKey = [];
            foreach ($rows as $row) {
                $key = $row['course_code'] . "\x1f" . ($row['major_id'] ?? '');
                $byKey[$key][] = $row;
            }

            foreach ($byKey as $group) {
                if (count($group) === 2) {
                    $r1 = $group[0];
                    $r2 = $group[1];
                    $langs = [$r1['course_lang'] ?? '', $r2['course_lang'] ?? ''];
                    sort($langs);
                    $isEfPair = ($langs === ['E', 'F']);
                    if ($isEfPair &&
                        $r1['prof_file_nb'] === $r2['prof_file_nb'] &&
                        $r1['second_corrector'] === $r2['second_corrector']) {

                        $r1['course_lang'] = "E/F";
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
        header("Location: AdminPage.php?tab=correctors&correctorAction=insert-numbers");
        exit;
    }

    if (isset($_POST["findNumber"])) {
        $session = $_POST["numberSession"] ?? "";
        $exam = $_POST["numberExam"] ?? "";
        $major = $_POST["numberMajor"] ?? "";
        $level = $_POST["numberLevel"] ?? "";
        $lang = $_POST["numberLang"] ?? "";
        $year = $_POST["numberYear"] ?? "";

        $_SESSION["insert_numbers_filter"] = [
            "numberSession" => $session,
            "numberExam" => $exam,
            "numberMajor" => $major,
            "numberLevel" => $level,
            "numberLang" => $lang,
            "numberYear" => $year
        ];

        $rows = fetchNumberRows($conn, $major, $level, $session, $lang, $exam, $year);
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
        header("Location: AdminPage.php?tab=correctors&correctorAction=insert-numbers");
        exit;
    }

    if (isset($_POST["applyNumbers"])) {
        if (!isset($_SESSION["insert_numbers_filter"])) {
            $_SESSION["insert_numbers_error"] = "Session expired. Please search again.";
            header("Location: AdminPage.php?tab=correctors");
            exit;
        }
        $first_numbers = $_POST["first_numbers"] ?? [];
        $second_numbers = $_POST["second_numbers"] ?? [];
        $session = $_SESSION["insert_numbers_filter"]["numberSession"] ?? "sem1";
        $exam = $_SESSION["insert_numbers_filter"]["numberExam"] ?? "F";
        $year = $_SESSION["insert_numbers_filter"]["numberYear"] ?? "";

        $col_first = ($session === "sess2" || $exam === "F") ? "final_first_corrector" : "partial_first_corrector";
        $col_second = ($session === "sess2" || $exam === "F") ? "final_second_corrector" : "partial_second_corrector";

        $updated = 0;
        foreach ($first_numbers as $code => $langs) {
            foreach ($langs as $lang_key => $byMajor) {
                if (!is_array($byMajor)) {
                    continue;
                }
                foreach ($byMajor as $major_id => $val_first) {
                    $val_second = $second_numbers[$code][$lang_key][$major_id] ?? 0;

                $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];

                foreach ($langs_to_update as $l) {
                    if ($lang_key === "E/F") {
                        if ($l === "E") {
                            $final_first = (int) ceil((int) $val_first / 2);
                            $final_second = (int) ceil((int) $val_second / 2);
                        } else {
                            $final_first = (int) floor((int) $val_first / 2);
                            $final_second = (int) floor((int) $val_second / 2);
                        }
                    } else {
                        $final_first = (int) $val_first;
                        $final_second = (int) $val_second;
                    }
                    $sql = "UPDATE correctors SET $col_first = ?, $col_second = ? 
                            WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ? AND uni_year = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "iisssss", $final_first, $final_second, $code, $l, $major_id, $session, $year);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $updated++;
                    }
                }
                }
            }
        }

        $_SESSION["insert_numbers_success"] = "Numbers updated successfully.";
        $inf = $_SESSION["insert_numbers_filter"];
        $_SESSION["insert_numbers_data"] = fetchNumberRows(
            $conn,
            (string) ($inf["numberMajor"] ?? "all"),
            (string) ($inf["numberLevel"] ?? "all"),
            (string) ($inf["numberSession"] ?? "sem1"),
            (string) ($inf["numberLang"] ?? "all"),
            (string) ($inf["numberExam"] ?? "F"),
            (string) ($inf["numberYear"] ?? "")
        );

        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=correctors&correctorAction=insert-numbers");
        exit;
    }

    if (isset($_POST["deleteNumbers"])) {
        if (!isset($_SESSION["insert_numbers_filter"])) {
            $_SESSION["insert_numbers_error"] = "Session expired. Please search again.";
            header("location: AdminPage.php?tab=correctors");
            exit;
        }
        $first_numbers = $_POST["first_numbers"] ?? [];
        $session = $_SESSION["insert_numbers_filter"]["numberSession"] ?? "sem1";
        $exam = $_SESSION["insert_numbers_filter"]["numberExam"] ?? "F";
        $year = $_SESSION["insert_numbers_filter"]["numberYear"] ?? "";

        $col_first = ($session === "sess2" || $exam === "F") ? "final_first_corrector" : "partial_first_corrector";
        $col_second = ($session === "sess2" || $exam === "F") ? "final_second_corrector" : "partial_second_corrector";

        foreach ($first_numbers as $code => $langs) {
            foreach ($langs as $lang_key => $byMajor) {
                if (!is_array($byMajor)) {
                    continue;
                }
                foreach ($byMajor as $major_id => $dummy) {
                $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];
                foreach ($langs_to_update as $l) {
                    $sql = "UPDATE correctors SET $col_first = 0, $col_second = 0 
                            WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ? AND uni_year = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "sssss", $code, $l, $major_id, $session, $year);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
                }
            }
        }

        $_SESSION["insert_numbers_success"] = "Numbers reset to 0.";
        $inf = $_SESSION["insert_numbers_filter"];
        $_SESSION["insert_numbers_data"] = fetchNumberRows(
            $conn,
            (string) ($inf["numberMajor"] ?? "all"),
            (string) ($inf["numberLevel"] ?? "all"),
            (string) ($inf["numberSession"] ?? "sem1"),
            (string) ($inf["numberLang"] ?? "all"),
            (string) ($inf["numberExam"] ?? "F"),
            (string) ($inf["numberYear"] ?? "")
        );

        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=correctors&correctorAction=insert-numbers");
        exit;
    }

    mysqli_close($conn);
    ob_end_clean();
    header("Location: AdminPage.php?tab=correctors&correctorAction=insert-numbers");
    exit;
