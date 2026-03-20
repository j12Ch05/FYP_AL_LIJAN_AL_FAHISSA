<?php
    session_start();
    include("database.php");

    //the data prompted by the user in the sign up page
    $fileNumber = $_SESSION["fileNumber"];
    $firstName =  $_SESSION["firstName"];
    $lastName = $_SESSION["lastName"];
    $birthDate = $_SESSION["birthDate"];
    $address = $_SESSION["address"];
    $phone = $_SESSION["phone"];
    $department = $_SESSION["department"];
    $email = $_SESSION["email"];
    $password = $_SESSION["password"];

    //inserting the data into the table professor
    $sql_insert = "INSERT INTO professor(prof_file_nb,prof_first_name,prof_last_name,
                                        prof_birth_date,prof_address,prof_phone,
                                        prof_email,prof_password,dep_id)
                    VALUES ({$fileNumber},{$firstName},{$lastName},{$birthDate},{$address},{$phone},{$email},{$password},{$department})";

    mysqli_query($conn,$sql_insert);
    
    mysqli_close($conn);
    exit();
?>