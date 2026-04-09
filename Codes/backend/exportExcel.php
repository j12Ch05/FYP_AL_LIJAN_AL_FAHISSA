<?php
    session_start();
    ob_start();
    include __DIR__ . "/database.php";
    
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

        $_SESSION["excelLoaded"] = true;
        
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
    
