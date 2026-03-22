<?php
    session_start();
    include("database.php");

    //the email that the professor enter the forgot password page
    $email = $_SESSION["email"];

    $token = bin2hex(random_bytes(16));

    $token_hash = hash("sha256",$token);

    $expiry = date("Y-m-d H:i:s",time()+60 * 20);

    $sql = "UPDATE professor
            SET reset_token_hash = ?
                reset_token_expires_at = ?
            WHERE prof_email = ?";
    
    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param($stmt,"sss",$token_hash,$expiry,$email);
    
    mysqli_stmt_execute($stmt);
    exit();
    mysqli_close($conn);
?>