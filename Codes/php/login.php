<?php
    session_start();
     if(isset($_POST["login"])){
        header("Location: charbel.php");
       $_SESSION["email"] = $_POST["email"];
        $_SESSION["password"] = password_hash($_POST["password"],PASSWORD_DEFAULT);
        if(isset($_POST["asAdmin"])){
            $admin = "yes";
        }
        else{
            $admin = "no";
        }
        $_SESSION["asAdmin"] = $admin;
    }
    exit();
?>