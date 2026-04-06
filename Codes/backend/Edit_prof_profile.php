<?php
    session_start();
    include("database.php");

     $ulDomain = "ul.edu.lb";
    $arabicPattern = "/^[\x{0600}-\x{06FF}\s]+$/u";
    $passwordFormat = "/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";

    if (!isset($_SESSION["email"])) {
        header("location: login.html");
        exit();
    }

 //Checking the first and last name if they are written in arabic
        if (!preg_match($arabicPattern, $firstName)) {
            $errors[] = "First name must be in Arabic.";
        }
        if (!preg_match($arabicPattern, $lastName)) {
            $errors[] = "Last name must be in Arabic.";
        }

         if(value_exists($conn,"prof_phone",$phone)){
            $errors[] = "Phone is already taken";
        }

        //Checking the file number
        if (empty($fileNumber) || !filter_var($fileNumber, FILTER_VALIDATE_INT)) {
            $errors[] = "File number is required and must be a number.";
        }
        if(value_exists($conn,"prof_file_nb",$fileNumber)){
            $errors[] = "This file number is already used";
        }

        //Checking the email
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || substr($email, -strlen($ulDomain)) != $ulDomain){
            $errors[] = "Invalid email format";
        }
        if(value_exists($conn,"prof_email",$email)){
            $errors[] = "This email is already used";
        }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_SESSION["email"];

        $first_name = trim($_POST["first_name"]);
        $last_name = trim($_POST["last_name"]);
        $birth_date = $_POST["birth_date"];
        $address = trim($_POST["address"]);
        $phone = trim($_POST["phone"]);
        $department = $_POST["department"];

        if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($department)) {
            echo "Error: All required fields must be filled.";
            exit();
        }

        $birth_date_obj = DateTime::createFromFormat('d-M-Y', $birth_date);
        if (!$birth_date_obj) {
            echo "Error: Invalid birth date format.";
            exit();
        }
        $birth_date_db = $birth_date_obj->format('Y-m-d');

        $sql_update = "UPDATE professor SET
                      prof_first_name = ?,
                      prof_last_name = ?,
                      prof_birth_date = ?,
                      prof_address = ?,
                      prof_phone = ?,
                      dep_id = ?
                      WHERE prof_email = ?";

        $stmt = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt, "sssssss", $first_name, $last_name, $birth_date_db, $address, $phone, $department, $email);

        if (mysqli_stmt_execute($stmt)) {
            echo "Profile updated successfully!";
        } else {
            echo "Error updating profile: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "Invalid request method.";
    }

    mysqli_close($conn);
?>