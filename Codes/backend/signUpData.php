<?php
    session_start();
    include("database.php");

    //Creating a function to verify if the file number or the email is already taken 
    function value_exists($conn,$col,$value){
        $sql_query = "select $col from professor where $col = ?";
        $stmt = mysqli_prepare($conn,$sql_query);
        mysqli_stmt_bind_param($stmt,"s",$value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }

    
    $fileNumber = (int)$_SESSION["fileNumber"];
    $sql_file = 'select prof_file_nb from professor where prof_file_nb = ?';


    $firstName = mysqli_real_escape_string($conn, $_SESSION["firstName"]);
    $lastName  = mysqli_real_escape_string($conn, $_SESSION["lastName"]);
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
                        prof_email, prof_password, dep_id, isAdmin, prof_category
                    ) 
                    VALUES (
                        $fileNumber, '$firstName', '$lastName', 
                        '$birthDate', '$address', '$phone', 
                        '$email', '$password', '$department', FALSE, '$category'
                    )";

    
    if (mysqli_query($conn, $sql_insert)) {
        echo "Registration successful!";
    } else {
        echo "Error: " . mysqli_error($conn); 
    }
    
    mysqli_close($conn);

    exit();
?>