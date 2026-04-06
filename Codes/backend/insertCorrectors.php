<?php
    session_start();
    include("database.php");

    if (isset($_POST["cancelBtn"])) {
        unset($_SESSION["insert_correctors_data"], $_SESSION["insert_correctors_filter"], $_SESSION["insert_correctors_error"]);
        mysqli_close($conn);
        ob_clean();
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }


    if(isset($_POST["findBtn"])){
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

        if($session== "sem1" || $session == "sem2"){
            
            
            $sql_fetch = "SELECT c.course_code, c.course_name,
                       p.prof_first_name, p.prof_last_name,
                       corr.second_corrector_file_nb AS second_corrector,
                       corr.third_corrector_file_nb AS third_corrector,
                       corr.session_nb
                FROM course c
                LEFT JOIN teaching t ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                LEFT JOIN professor p ON p.prof_file_nb = t.prof_file_nb
                LEFT JOIN correctors corr ON corr.course_code = c.course_code AND corr.course_lang = c.course_lang
                WHERE c.major_id = ? AND c.course_level = ? AND c.course_semester_nb = ? AND c.course_lang = ?";

            $stmt = mysqli_prepare($conn,$sql_fetch);
            if(!$stmt){
                $_SESSION["insert_correctors_data"] =[];
                $_SESSION["insert_correctors_error"] = "Query prepare failed: " . mysqli_error($conn);;
            }
            else{
                $sem = $session == "sem1" ? 1 : 2;
                mysqli_stmt_bind_param($stmt, "ssis", $major, $level, $sem, $language);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION["insert_correctors_data"] = [];
                    $_SESSION["insert_correctors_error"] = "Query failed: " . mysqli_stmt_error($stmt);
                } else {
                    $result = mysqli_stmt_get_result($stmt);
                    $rows = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rows[] = $row;
                    }
                    $_SESSION["insert_correctors_data"] = $rows;
                    unset($_SESSION["insert_correctors_error"]);
                }
                mysqli_stmt_close($stmt);
            }
        }
        else{
            $sql_fetch = "SELECT c.course_code, c.course_name,
                       p.prof_first_name, p.prof_last_name,
                       corr.second_corrector_file_nb AS second_corrector,
                       corr.third_corrector_file_nb AS third_corrector,
                       corr.session_nb
                FROM course c
                LEFT JOIN teaching t ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                LEFT JOIN professor p ON p.prof_file_nb = t.prof_file_nb
                LEFT JOIN correctors corr ON corr.course_code = c.course_code AND corr.course_lang = c.course_lang
                WHERE c.major_id = ? AND c.course_level = ?  AND c.course_lang = ?";

            $stmt = mysqli_prepare($conn,$sql_fetch);
            if(!$stmt){
                $_SESSION["insert_correctors_data"] =[];
                $_SESSION["insert_correctors_error"] = "Query prepare failed: " . mysqli_error($conn);;
            }
            else{
                
                mysqli_stmt_bind_param($stmt, "sss", $major, $level, $language);
                if (!mysqli_stmt_execute($stmt)) {
                    $_SESSION["insert_correctors_data"] = [];
                    $_SESSION["insert_correctors_error"] = "Query failed: " . mysqli_stmt_error($stmt);
                } else {
                    $result = mysqli_stmt_get_result($stmt);
                    $rows = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rows[] = $row;
                    }
                    $_SESSION["insert_correctors_data"] = $rows;
                    unset($_SESSION["insert_correctors_error"]);
                }
                mysqli_stmt_close($stmt);
            }
        }

        mysqli_close($conn);

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            $err = $_SESSION['insert_correctors_error'] ?? null;
            echo json_encode([
                'status' => $err ? 'error' : 'success',
                'courses' => $_SESSION['insert_correctors_data'] ?? [],
                'professors' => $_SESSION['professors'] ?? [],
                'message' => $err ? $err : '',
            ]);
            exit;
        }

        header("Location: AdminPage.php?tab=correctors");
        exit;
    }

    if (isset($_POST["applyCorr"])) {
        $second_correctors = $_POST["second_corrector"] ?? [];
        $third_correctors = $_POST["third_corrector"] ?? [];
        $lang = $_SESSION["insert_correctors_filter"]["corrLang"] ?? 'E';
        $session = $_SESSION["insert_correctors_filter"]["corrSession"] ?? '';
        $session_nb = null;
        if ($session === 'sem1') {
            $session_nb = 1;
        } elseif ($session === 'sem2') {
            $session_nb = 2;
        }

        foreach ($second_correctors as $course_code => $second) {
            $third = $third_correctors[$course_code] ?? null;
            $second_val = $second ?: null;
            $third_val = $third ?: null;

            // Check if exists
            $sql_check = "SELECT COUNT(*) FROM correctors WHERE course_code = ? AND course_lang = ?";
            $stmt_check = mysqli_prepare($conn, $sql_check);
            if (!$stmt_check) {
                continue;
            }
            mysqli_stmt_bind_param($stmt_check, "ss", $course_code, $lang);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $count);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($count > 0) {
                $sql = "UPDATE correctors SET second_corrector_file_nb = ?, third_corrector_file_nb = ?, session_nb = ? WHERE course_code = ? AND course_lang = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssiss", $second_val, $third_val, $session_nb, $course_code, $lang);
                }
            } else {
                $prof_file_nb = null;
                $sql_prof = "SELECT prof_file_nb FROM teaching WHERE course_code = ? AND course_lang = ? LIMIT 1";
                $stmt_prof = mysqli_prepare($conn, $sql_prof);
                if ($stmt_prof) {
                    mysqli_stmt_bind_param($stmt_prof, "ss", $course_code, $lang);
                    mysqli_stmt_execute($stmt_prof);
                    mysqli_stmt_bind_result($stmt_prof, $prof_file_nb);
                    mysqli_stmt_fetch($stmt_prof);
                    mysqli_stmt_close($stmt_prof);
                }

                if ($prof_file_nb === null) {
                    continue;
                }

                $sql = "INSERT INTO correctors (course_code, course_lang, prof_file_nb, second_corrector_file_nb, third_corrector_file_nb, session_nb) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssisii", $course_code, $lang, $prof_file_nb, $second_val, $third_val, $session_nb);
                }
            }

            if (isset($stmt) && $stmt) {
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        mysqli_close($conn);
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }

    mysqli_close($conn);
    header("Location: AdminPage.php?tab=correctors");
    exit;
?>
