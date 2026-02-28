<?php
    session_start();
     if(isset($_POST["login"])){
        if(isset($_POST["asAdmin"])){
            header("Location: charbel.php");
        }
        else{
            header("Location: ProfPage.html");
        }
        $_SESSION["asAdmin"] = $admin;
    }
    exit();
?>