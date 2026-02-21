<?php
    session_start();
    if(isset($_POST["sendCode"])){
        header("Location: enterCode.html");
        $_SESSION["email"] = $_POST["email"];
        $code = array();
        while(6-1){
            $digit = rand(0,10);
            array_push($code,$digit);
        }
        $_SESSION["code"] = $code;
    }

    exit();
?>