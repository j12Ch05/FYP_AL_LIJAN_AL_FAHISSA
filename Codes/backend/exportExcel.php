<?php
    session_start();
    ob_start();
    include __DIR__ . "/database.php";

    $_SESSION["departments"] = [
        "bio" => "علوم الأحياء",
        "bioch" => "الكيمياء الحيوية",
        "che" => "كيمياء",
        "pe" => "الفيزياء والإلكترونيك",
        "css" => "المعلوماتية والاحصاء",
        "math" => "الرياضيات"
    ];

    $_SESSION["semesters"] = [
        "sem1" => "الفصل  الأول",
        "sem2" => "الفصل الثاني"
    ];

    $email = $_SESSION["email"];
    
    $sql_professors = "SELECT p.prof_file_nb,p.prof_first_name,p.prof_father_name,p.prof_last_name,a.dep_id FROM professor p Join professor a on a.dep_id = p.dep_id where a.prof_email= ?";
    $stmt_p = mysqli_prepare($conn, $sql_professors);
    mysqli_stmt_bind_param($stmt_p,"s",$email);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);

    $professors = [];
    while($row = mysqli_fetch_assoc($res_p)){
        $professors[$row["prof_file_nb"]] = $row["prof_first_name"] . " " . $row["prof_father_name"] . " " . $row["prof_last_name"];
        $dep = $row["dep_id"];
    }
    mysqli_stmt_close($stmt_p);

    $sql_courses = "SELECT c.course_code,c.course_name,c.course_lang FROM course c 
                    JOIN major m ON m.major_id = c.major_id
                    WHERE m.dep_id = ?";
    $stmt_c = mysqli_prepare($conn,$sql_courses);
    mysqli_stmt_bind_param($stmt_c,"s",$dep);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);


    $courses = [];
    while($row = mysqli_fetch_assoc($res_c)){
        $courses[$row["course_lang"]][$row["course_code"]] = $row["course_name"];
    }
    mysqli_stmt_close($stmt_c);

    $_SESSION["prof_full_names"] = $professors;
    $_SESSION["dep_id"] = $dep;
    $_SESSION["courses"] = $courses;


    
    if (isset($_POST["cancelExcel"])) {
        unset($_SESSION["excel_export_filter"], $_SESSION["excel_export_error"]);
        mysqli_close($conn);
        ob_end_clean();
        header("Location: AdminPage.php?tab=correctors");
        exit;
    }    


    if(isset($_POST["tawzi3"]) || isset($_POST["ta3in"]) || isset($_POST["edbarat"])){
        $session = $_POST["sessionId"] ?? "";
        $major = $_POST["excelMajor"] ?? "";
        $level = $_POST["excelLevel"] ?? "";

        $_SESSION["excel_export_filter"] = [
            "sessionId" => $session,
            "excelMajor" => $major,
            "excelLevel" => $level
        ];

        
        //here will be the switch between the excel format
        if(isset($_POST["tawzi3"])){
            //charbel part
            require __DIR__ . "/tawzi3_format.php";
        }
        else if(isset($_POST["ta3in"])){
            require __DIR__ . "/ta3in_format.php";
        }
        else if(isset($_POST["edbarat"])){
            require __DIR__ . "/edbarat_format.php";
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
    
