<?php
    session_start();
    ob_start();
    include __DIR__ . "/database.php";

    if (isset($_POST["deleteView"])) {
        unset($_SESSION["view_major_courses"], $_SESSION["view_major_filter"], $_SESSION["view_major_error"]);
        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=courses");
        exit;
    }

    if (isset($_POST["findView"])) {
        $major = $_POST["major"] ?? "";
        $level = $_POST["majorLevel"] ?? "";
        $semester = $_POST["majorSemester"] ?? "";
        $language = $_POST["majorLang"] ?? "";

        $_SESSION["view_major_filter"] = [
            "major" => $major,
            "majorLevel" => $level,
            "majorSemester" => $semester,
            "majorLang" => $language,
        ];

        $sql = "SELECT c.course_code, c.course_name, c.course_category, c.course_credit_nb,
                       p.prof_first_name, p.prof_last_name
                FROM course c
                LEFT JOIN teaching t ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                LEFT JOIN professor p ON p.prof_file_nb = t.prof_file_nb
                WHERE c.major_id = ? AND c.course_level = ? AND c.course_semester_nb = ? AND c.course_lang = ?";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $_SESSION["view_major_courses"] = [];
            $_SESSION["view_major_error"] = "Query prepare failed: " . mysqli_error($conn);
        } else {
            $semesterInt = (int) $semester;
            mysqli_stmt_bind_param($stmt, "ssis", $major, $level, $semesterInt, $language);
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION["view_major_courses"] = [];
                $_SESSION["view_major_error"] = "Query failed: " . mysqli_stmt_error($stmt);
            } else {
                $result = mysqli_stmt_get_result($stmt);
                $rows = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                $_SESSION["view_major_courses"] = $rows;
                unset($_SESSION["view_major_error"]);
            }
            mysqli_stmt_close($stmt);
        }

        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=courses");
        exit;
    }

    mysqli_close($conn);
    ob_end_clean();
    header("Location: AdminPage.php?tab=courses");
    exit;
