<?php
session_start();
ob_start();
include __DIR__ . "/database.php";

function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function fetchCorrectorsRows(mysqli $conn, string $major, string $level, string $language, string $session): array
{
    $sql_fetch = "SELECT corr.course_code, c.course_name, corr.course_lang,
                   p.prof_file_nb AS first_corrector_id,
                   p.prof_first_name, p.prof_last_name,
                   corr.second_corrector_file_nb AS second_corrector,
                   corr.third_corrector_file_nb AS third_corrector,
                   corr.session_nb
            FROM correctors corr
            LEFT JOIN course c ON corr.course_code = c.course_code AND corr.course_lang = c.course_lang AND corr.major_id = c.major_id
            LEFT JOIN professor p ON p.prof_file_nb = corr.prof_file_nb
            WHERE c.major_id = ? AND c.course_level = ? AND corr.session_nb = ?";

    if ($language !== "all") {
        $sql_fetch .= " AND corr.course_lang = ?";
    }

    $stmt = mysqli_prepare($conn, $sql_fetch);
    if (!$stmt) {
        return [];
    }

    if ($language !== "all") {
        mysqli_stmt_bind_param($stmt, "ssss", $major, $level, $session, $language);
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

    if ($language === "all") {
        $merged = [];
        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row['course_code']][] = $row;
        }

        foreach ($byCode as $code => $group) {
            if (count($group) === 2) {
                $r1 = $group[0];
                $r2 = $group[1];
                if ($r1['first_corrector_id'] === $r2['first_corrector_id'] && 
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

    $_SESSION["insert_correctors_filter"] = [
        "corrMajor" => $major,
        "corrLevel" => $level,
        "corrSession" => $session,
        "corrLang" => $language
    ];

    $rows = fetchCorrectorsRows($conn, $major, $level, $language, $session);
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
    $major = $_SESSION["insert_correctors_filter"]["corrMajor"] ?? "";

    $updatedRows = 0;
    foreach ($second_correctors as $course_code => $langs) {
        foreach ($langs as $lang_key => $second) {
            $third = $third_correctors[$course_code][$lang_key] ?? null;
            $second_val = $second !== '' ? $second : null;
            $third_val = $third !== '' ? $third : null;

            $langs_to_update = ($lang_key === "E/F") ? ["E", "F"] : [$lang_key];

            foreach ($langs_to_update as $lang) {
        $sql_check = "SELECT COUNT(*) FROM correctors WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        if (!$stmt_check) {
            continue;
        }
        mysqli_stmt_bind_param($stmt_check, "ssss", $course_code, $lang, $major, $session);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_bind_result($stmt_check, $count);
        mysqli_stmt_fetch($stmt_check);
        mysqli_stmt_close($stmt_check);

        if ($count > 0) {
            $sql = "UPDATE correctors SET second_corrector_file_nb = ?, third_corrector_file_nb = ? WHERE course_code = ? AND course_lang = ? AND major_id = ? AND session_nb = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iissss", $second_val, $third_val, $course_code, $lang, $major, $session);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $updatedRows++;
            }
        } else {
            $prof_file_nb = null;
            $sql_prof = "SELECT prof_file_nb FROM teaching WHERE course_code = ? AND course_lang = ? AND major_id = ? AND isActive = 1 LIMIT 1";
            $stmt_prof = mysqli_prepare($conn, $sql_prof);
            if ($stmt_prof) {
                mysqli_stmt_bind_param($stmt_prof, "sss", $course_code, $lang, $major);
                mysqli_stmt_execute($stmt_prof);
                mysqli_stmt_bind_result($stmt_prof, $prof_file_nb);
                mysqli_stmt_fetch($stmt_prof);
                mysqli_stmt_close($stmt_prof);
            }

            if ($prof_file_nb === null) {
                continue;
            }

            $sql = "INSERT INTO correctors (course_code, course_lang, major_id, prof_file_nb, second_corrector_file_nb, third_corrector_file_nb, session_nb) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssiiis", $course_code, $lang, $major, $prof_file_nb, $second_val, $third_val, $session);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $updatedRows++;
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

    $major = $_SESSION["insert_correctors_filter"]["corrMajor"] ?? "";
    $level = $_SESSION["insert_correctors_filter"]["corrLevel"] ?? "";
    $language = $_SESSION["insert_correctors_filter"]["corrLang"] ?? 'E';
    if ($major !== "" && $level !== "") {
        $_SESSION["insert_correctors_data"] = fetchCorrectorsRows($conn, $major, $level, $language, $session);
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
