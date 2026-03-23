<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    // This one line replaces all the manual "require" lines above
    require __DIR__ . "/vendor/autoload.php";

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPAuth = true;

    $mail->Host = "smtp.gmail.com";
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
    $mail->Port = 587; 

    $mail->Username = "fypalaf@gmail.com";
    $mail->Password = "smtp-password";

    $mail->isHTML(true);

    return $mail;
?>