<?php
session_start();
ob_start();
include __DIR__ .'/database.php';

function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function fetchCorrectorsRows(mysqli $conn, string $major, string $level, string $language, string $session,string $uniYear): array
{
    $sql_fetch = "SELECT corr.course_code, c.course_name, corr.course_lang,
                   p.prof_file_nb AS first_corrector_id,
                   p.prof_first_name, p.prof_last_name,
                   corr.second_corrector_file_nb AS second_corrector,
                   corr.third_corrector_file_nb AS third_corrector,
                   corr.session_nb,
                   corr.major_id AS major_id, c.course_level
            FROM correctors corr
            LEFT JOIN course c ON corr.course_code = c.course_code AND corr.course_lang = c.course_lang AND corr.major_id = c.major_id AND c.uni_year = corr.uni_year
            LEFT JOIN professor p ON p.prof_file_nb = corr.prof_file_nb
            WHERE corr.session_nb = ? AND corr.uni_year = ?
            ";
    $paramTypes = 'ss';
    $param = [$session,$uniYear];

    if ($major !== "all") {
        $sql_fetch .= " AND corr.major_id = ? ";
        $paramTypes .= "s";
        $param[] = $major;
    }
    if ($level !== "all") {
        $sql_fetch .= " AND c.course_level = ? ";
        $paramTypes .= "s";
        $param[] = $level;
    }
    if ($language !== "all") {
        $sql_fetch .= " AND corr.course_lang = ? ";
        $paramTypes .= "s";
        $param[] = $language;
    }

    $sql_fetch .= " ORDER BY corr.course_code ASC";

    $stmt = mysqli_prepare($conn, $sql_fetch);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt,$paramTypes,...$param);
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

    if ($language === "all") {
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
                    $r1['first_corrector_id'] === $r2['first_corrector_id'] &&
                    $r1['second_corrector'] === $r2['second_corrector'] &&
                    $r1['third_corrector'] === $r2['third_corrector']) {
                    $r1['course_lang'] = "E/F";
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

if (isset($_POST["cancelBtn"]) || isset($_GET["cancelBtn"])) {
    unset($_SESSION["insert_correctors_data"], $_SESSION["insert_correctors_filter"], $_SESSION["insert_correctors_error"], $_SESSION["insert_correctors_success"]);
    if (isset($conn)) mysqli_close($conn);
    if (ob_get_length()) ob_end_clean();
    header("Location: AdminPage.php?tab=correctors");
    exit;
}

if (isset($_POST["findBtn"])) {
    $major = $_POST["corrMajor"] ?? "";
    $level = $_POST["corrLevel"] ?? "";
    $session = $_POST["corrSession"] ?? "";
    $language = $_POST["corrLang"] ?? "";
    $year = $_POST["corrYear"]??"";

    $_SESSION["insert_correctors_filter"] = [
        "corrMajor" => $major,
        "corrLevel" => $level,
        "corrSession" => $session,
        "corrLang" => $language,
        "corrYear" => $year
    ];

    $rows = fetchCorrectorsRows($conn, $major, $level, $language, $session,$year);
    $ajaxError = null;
    $_SESSION["insert_correctors_data"] = $rows;
    unset($_SESSION["insert_correctors_error"]);

    if (isAjaxRequest()) {
        ob_end_clean();
        header('Content-Type: application/json');
        if ($ajaxError !== null) {
            echo json_encode([
                'status' => 'error',
                'message' => $ajaxError
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'courses' => $rows,
                'professors' => $_SESSION['professors'] ?? []
            ]);
        }
        mysqli_close($conn);
        exit;
    }

    mysqli_close($conn);
    ob_end_clean();
    header("Location: AdminPage.php?tab=correctors");
    exit;
}

if (isset($_POST["applyCorr"])) {
    if (!isset($_SESSION["insert_correctors_filter"])) {
        $_SESSION["insert_correctors_error"] = "Session expired. Please search again.";
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }
    $second_correctors = $_POST["second_corrector"] ?? [];
    $third_correctors = $_POST["third_corrector"] ?? [];
    $session = $_SESSION["insert_correctors_filter"]["corrSession"] ?? 'sem1';
    $year = $_SESSION["insert_correctors_filter"]["corrYear"] ?? "";

    $updatedRows = 0;
    foreach ($second_correctors as $course_code => $byLang) {
        foreach ($byLang as $lang_key => $byMajor) {
            if (!is_array($byMajor)) {
                continue;
            }
            foreach ($byMajor as $row_major_id => $second) {
                $thirdMap = $third_correctors[$course_code][$lang_key] ?? [];
                $third = is_array($thirdMap) ? ($thirdMap[$row_major_id] ?? null) : null;
                $second_val = $second !== '' ? (int) $second : null;
                $third_val = $third !== '' && $third !== null ? (int) $third : null;

                $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];

                foreach ($langs_to_update as $lang) {
                    $sql_check = "SELECT COUNT(*) FROM correctors WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ? AND uni_year = ?";
                    $stmt_check = mysqli_prepare($conn, $sql_check);
                    if (!$stmt_check) {
                        continue;
                    }
                    mysqli_stmt_bind_param($stmt_check, "sssss", $course_code, $lang, $row_major_id, $session, $year);
                    mysqli_stmt_execute($stmt_check);
                    mysqli_stmt_bind_result($stmt_check, $count);
                    mysqli_stmt_fetch($stmt_check);
                    mysqli_stmt_close($stmt_check);

                    if ($count > 0) {
                        $sql = "UPDATE correctors SET second_corrector_file_nb = ?, third_corrector_file_nb = ? WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ? AND uni_year = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "iisssss", $second_val, $third_val, $course_code, $lang, $row_major_id, $session, $year);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            $updatedRows++;
                        }
                    } else {
                        $prof_file_nb = null;
                        $sql_prof = "SELECT prof_file_nb FROM teaching WHERE course_code = ? AND course_lang = ? AND major_id = ? AND isActive = 1 LIMIT 1";
                        $stmt_prof = mysqli_prepare($conn, $sql_prof);
                        if ($stmt_prof) {
                            mysqli_stmt_bind_param($stmt_prof, "sss", $course_code, $lang, $row_major_id);
                            mysqli_stmt_execute($stmt_prof);
                            mysqli_stmt_bind_result($stmt_prof, $prof_file_nb);
                            mysqli_stmt_fetch($stmt_prof);
                            mysqli_stmt_close($stmt_prof);
                        }

                        if ($prof_file_nb === null) {
                            continue;
                        }

                        $sql = "INSERT INTO correctors (course_code, course_lang, major_id, prof_file_nb, second_corrector_file_nb, third_corrector_file_nb, session_nb, uni_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "sssiiiss", $course_code, $lang, $row_major_id, $prof_file_nb, $second_val, $third_val, $session, $year);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            $updatedRows++;
                        }
                    }
                }
            }
        }
    }

    if ($updatedRows > 0) {
        $_SESSION["insert_correctors_success"] = "Correctors updated successfully.";
        unset($_SESSION["insert_correctors_error"]);
    } else {
        $_SESSION["insert_correctors_error"] = "No correctors were updated. Please check course filters and inputs.";
        unset($_SESSION["insert_correctors_success"]);
    }

    $cf = $_SESSION["insert_correctors_filter"] ?? [];
    if ($cf !== []) {
        $_SESSION["insert_correctors_data"] = fetchCorrectorsRows(
            $conn,
            (string) ($cf["corrMajor"] ?? "all"),
            (string) ($cf["corrLevel"] ?? "all"),
            (string) ($cf["corrLang"] ?? "all"),
            (string) ($cf["corrSession"] ?? $session),
            (string) ($cf["corrYear"] ?? $year)
        );
    }

    mysqli_close($conn);
    ob_end_clean();
    header("Location: AdminPage.php?tab=correctors");
    exit;
}

mysqli_close($conn);
ob_end_clean();
header("Location: AdminPage.php?tab=correctors");
exit;
