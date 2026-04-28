<?php
    session_start();

    //Creating the random code the user should enter
    $d1 = rand(0,9);
    $d2 = rand(0,9);
    $d3 = rand(0,9);
    $d4 = rand(0,9);
    $d5 = rand(0,9);
    $d6 = rand(0,9);

    //the email that the professor enter the forgot password page
    $email = $_SESSION["email"];
    $mail = require __DIR__ . "/mailer.php";

    $mail->setFrom("noreply@example.com");
    $mail->addAddress($email);
    $mail->Subject = "Verify Email";
    $mail->Body = <<<END

    To verify your email on the website.Enter the code:
    <div><h1>{$d1} {$d2} {$d3} {$d4} {$d5} {$d6}</h1></div>
    END;

    try{
        $mail->send();
    }catch(Exception $e){
        echo "Message could not here be sent. Mailer error: {$mail->ErrorInfo}";
    }

    header("Location: verifyEmail.php");
    $digits = [$d1,$d2,$d3,$d4,$d5,$d6];
    $_SESSION["digits"] = $digits;



    exit();