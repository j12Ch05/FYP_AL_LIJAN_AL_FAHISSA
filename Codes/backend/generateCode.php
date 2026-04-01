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

    $reset_date = date("Y-m-d H:i:s",time());

    $sql = "UPDATE professor
            SET   reset_date  = ?
            WHERE prof_email = ?";
    
    $stmt = mysqli_prepare($conn,$sql);

    if ($stmt) {
        
        mysqli_stmt_bind_param($stmt, "ss", $reset_date, $email);
        
        
        if (mysqli_stmt_execute($stmt)) {
            
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $mail = require __DIR__ . "/mailer.php";

                $mail->setFrom("noreply@example.com");
                $mail->addAddress("receiveralaf@gmail.com");
                // $mail->addAddress($email)
                $mail->Subject = "Password Reset";
                $mail->Body = <<<END

                To reset your password please enter this code on the website
                <div><h1>{$d1} {$d2} {$d3} {$d4} {$d5} {$d6}</h1></div>

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