<?php
    session_start();
    include("database.php");

    

    
    $fileNumber = (int)$_SESSION["fileNumber"];
    $firstName = mysqli_real_escape_string($conn, $_SESSION["firstName"]);
    $lastName  = mysqli_real_escape_string($conn, $_SESSION["lastName"]);
    $fatherName  = mysqli_real_escape_string($conn, $_SESSION["fatherName"]);
    $birth  = $_SESSION["birthDate"];
    if($birth instanceof DateTime){
        $birth = $birth->format('Y-m-d');
    }
    $birthDate = mysqli_real_escape_string($conn , $birth);
    $address   = mysqli_real_escape_string($conn, $_SESSION["address"]);
    $phone     = mysqli_real_escape_string($conn, $_SESSION["phone"]);
    $department = mysqli_real_escape_string($conn, $_SESSION["department"]);
    $category = mysqli_real_escape_string($conn,$_SESSION["category"]);


    $email   = mysqli_real_escape_string($conn, $_SESSION["email"]);


    $password   = password_hash($_SESSION["password"], PASSWORD_DEFAULT); // Security: Hash the password!

    
    $sql_insert = "INSERT INTO professor (
                        prof_file_nb, prof_first_name, prof_last_name, 
                        prof_birth_date, prof_address, prof_phone, 
                        prof_email, prof_password, dep_id, isAdmin, prof_category,prof_father_name
                    ) 
                    VALUES (
                        $fileNumber, '$firstName', '$lastName', 
                        '$birthDate', '$address', '$phone', 
                        '$email', '$password', '$department', FALSE, '$category','$fatherName'
                    )";

    
    if (mysqli_query($conn, $sql_insert)) {
        header("location: login.php");
        echo "Registration successful!";
    } else {
        echo "Error: " . mysqli_error($conn); 
    }
    
    mysqli_close($conn);

    exit();
?>