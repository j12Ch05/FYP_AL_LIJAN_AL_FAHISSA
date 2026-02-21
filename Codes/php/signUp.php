<?php
    session_start();
    if(isset($_POST["signUp"])){
        header("Location: data.php");
        $d = $_POST["birthdate"];
        $date = date('d-m-Y',strtotime($d));
        $_SESSION["firstName"] = $_POST["firstName"];
        $_SESSION["lastName"] = $_POST["lastName"];
        $_SESSION["birthDate"] = $date;
        $_SESSION["address"] = $_POST["address"];
        $_SESSION["phone"] = $_POST["phone"];
        $_SESSION["fileNumber"] = $_POST["fileNumber"];
        $_SESSION["department"] = $_POST["department"];
        $_SESSION["email"] = $_POST["email"];
        $_SESSION["password"] = password_hash($_POST["password"],PASSWORD_DEFAULT);
    }
    exit();
?>