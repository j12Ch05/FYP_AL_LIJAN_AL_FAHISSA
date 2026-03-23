<?php
    session_start();
    include("database.php");

    //Creating the random code the user should enter
    $d1 = rand(0,9);
    $d2 = rand(0,9);
    $d3 = rand(0,9);
    $d4 = rand(0,9);
    $d5 = rand(0,9);
    $d6 = rand(0,9);

    //the email that the professor enter the forgot password page
    $email = $_SESSION["email"];

    $token = bin2hex(random_bytes(16));

    $token_hash = hash("sha256",$token);

    $expiry = date("Y-m-d H:i:s",time()+60 * 20);

    $sql = "UPDATE professor
            SET reset_token_hash = ?,
                reset_token_expires_at = ?
            WHERE prof_email = ?";
    
    $stmt = mysqli_prepare($conn,$sql);

    if ($stmt) {
        
        mysqli_stmt_bind_param($stmt, "sss", $token_hash, $expiry, $email);
        
        
        if (mysqli_stmt_execute($stmt)) {
            
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $mail = require __DIR__ . "/mailer.php";

                $mail->setFrom("noreply@example.com");
                $mail->addAddress("receiveralaf@gmail.com");
                // $mail->addAddress($email)
                $mail->Subject = "Password Reset";
                $mail->Body = <<<END

                To reset your password please enter this code on the website
                <div><h2>{$d1}</h2> <h2>{$d2}</h2> <h2>{$d3}</h2> <h2>{$d4}</h2> <h2>{$d5}</h2> <h2>{$d6}</h2></div>

                END;

                try{
                    $mail->send();
                }catch(Exception $e){
                    echo "Message could not here be sent. Mailer error: {$mail->ErrorInfo}";
                }

            } else {
                echo "Something went wrong";
            }
            header("location: enterCode.php");
            $digits = [$d1,$d2,$d3,$d4,$d5,$d6];
            $_SESSION["digits"] = $digits;
        } else {
            echo "Execution failed: " . mysqli_stmt_error($stmt);
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "Preparation failed: " . mysqli_error($conn);
    }

    
    mysqli_close($conn);
    exit();
?>