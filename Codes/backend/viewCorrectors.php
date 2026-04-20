<?php


    session_start();
    ob_start();
    include __DIR__ . "/database.php";


    if (isset($_POST["cancelCorrectors"])) {
        unset($_SESSION["view_correctors_data"], $_SESSION["view_correctors_filter"], $_SESSION["view_correctors_error"], $_SESSION["view_correctors_loaded"]);
        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }

    if(isset($_POST["searchID"])){
        $course = $_POST["courseId"] ?? "";
        $lang = $_POST["correctorLang"] ?? "";
        $sess = $_POST["correctorSession"] ?? "";
        $major = $_POST["correctorMajor"] ?? "";

        $_SESSION["view_correctors_filter"] = [
            "courseId" => $course,
            "correctorLang" => $lang,
            "correctorSession" => $sess,
            "correctorMajor" => $major
        ];


        $sql = "SELECT c.course_code,
                       c.course_name,
                       COALESCE(corr.prof_file_nb, t.prof_file_nb) AS prof_file_nb,
                       corr.second_corrector_file_nb,
                       corr.third_corrector_file_nb
                FROM course c
                LEFT JOIN correctors corr ON c.course_code = corr.course_code
                    AND c.course_lang = corr.course_lang
                    AND c.major_id = corr.major_id
                    AND corr.session_nb = ?
                LEFT JOIN teaching t ON c.course_code = t.course_code
                    AND c.course_lang = t.course_lang
                    AND c.major_id = t.major_id
                    AND t.isActive = 1
                WHERE c.course_code = ? AND c.course_lang = ? AND c.major_id = ?";


        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt){
            $_SESSION["view_correctors_data"] = [];
            $_SESSION["view_correctors_error"] = "Query failed to prepare: " . mysqli_error($conn);
        }
        else{
            mysqli_stmt_bind_param($stmt,"ssss",$sess, $course, $lang, $major);
            if(!mysqli_stmt_execute($stmt)){
                $_SESSION["view_correctors_data"] = [];
                $_SESSION["view_correctors_error"] = "Query execution failed: " . mysqli_stmt_error($stmt);
            }
            else{
                $result = mysqli_stmt_get_result($stmt);
                $rows = [];
                
                while($row = mysqli_fetch_assoc($result)){
                    $rows[] = $row;
                }

                $_SESSION["view_correctors_data"] = $rows;
                unset($_SESSION["view_correctors_error"]);
                $_SESSION["view_correctors_loaded"] = true;
            }

            mysqli_stmt_close($stmt);

        }

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            mysqli_close($conn);
            exit;
        }

        mysqli_close($conn);
        ob_end_clean();
        header("location: AdminPage.php?tab=correctors");
        exit;

    }

    mysqli_close($conn);
    ob_end_clean();
    header("location: AdminPage.php?tab=correctors");
    exit;

    