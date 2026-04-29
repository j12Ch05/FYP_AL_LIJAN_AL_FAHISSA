<?php
    session_start();

    if(!isset($_SESSION['email'])){
        header("Location: signUp.php");
        unset($_SESSION["email"]);
        exit();
    }

    if(isset($_POST["verifyCode"])){
       $is_valid = true;
       //for verifying the code
       $digits = $_SESSION["digits"];
       //taking the user input
       $d1 = $_POST["d1"];
       $d2 = $_POST["d2"];
       $d3 = $_POST["d3"];
       $d4 = $_POST["d4"];
       $d5 = $_POST["d5"];
       $d6 = $_POST["d6"];

       $inputs = [$d1,$d2,$d3,$d4,$d5,$d6];
       for($i=0;$i<6;$i++){
            if($inputs[$i] != $digits[$i]){
                $is_valid = false;
                break;
            }
       }

       if($is_valid){
            header("Location: signUpData.php");
            exit();
       }
       else{
        $_SESSION["error"] = "The code is incorrect";
       }
    }
    else if(isset($_POST["resend"])){
        header("Location: emailCode.php");
    }
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="ULFS.ico">
</head>
<body>
    <div class="box-container">
        <?php 
            if (isset($_SESSION["error"])) {
                echo '<p style="color: red; font-weight: bold;">' . $_SESSION["error"] . '</p>';
                
                unset($_SESSION["error"]);
            }
        ?>
        <h1>Verify Email</h1>
        <p>We sent a 6-digit verification code to your email.</p>
        <form id="verifyEmail" action="verifyEmail.php"  method="post">
        <div class="code-inputs">
            <input type="text" maxlength="1" inputmode="numeric" name="d1">
            <input type="text" maxlength="1" inputmode="numeric" name="d2">
            <input type="text" maxlength="1" inputmode="numeric" name="d3">
            <input type="text" maxlength="1" inputmode="numeric" name="d4">
            <input type="text" maxlength="1" inputmode="numeric" name="d5">
            <input type="text" maxlength="1" inputmode="numeric" name="d6">
        </div>
        <div class="error-message" id="codeError">Please enter the valid code</div><br>
        <button type="submit" class="submit-btn" name="verifyCode">Verify Code</button>
        <button class="submit-btn" name="resend">Resend the code</button>
        <div class="back">
            <a href="signUp.php">Back</a>
        </div>
        </form>
    </div>
</body>
<script>
        const inputs = document.querySelectorAll('.code-inputs input');

        inputs.forEach((input, index) => {
            // Jumps to the NEXT input when a digit is typed
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            // Jumps to the PREVIOUS input when Backspace is pressed on an empty box
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });
</script>
</html>
