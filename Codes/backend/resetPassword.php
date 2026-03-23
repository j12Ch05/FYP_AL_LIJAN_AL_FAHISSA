<?php
    session_start();
    include("database.php");

    $email = $_SESSION["email"];

    if(isset($_POST["changePassword"])){
        $newPassword = $_POST["newPassword"];
        $confirmPassword = $_POST["confirmPassword"];

        if($newPassword == $confirmPassword){
            $sql_change = "UPDATE professor
                            SET prof_password = ?
                            WHERE prof_email = ?";

            $stmt = mysqli_prepare($conn,$sql_change);
            $hash_password = password_hash($confirmPassword,PASSWORD_DEFAULT);

            if ($stmt) {
                
                mysqli_stmt_bind_param($stmt, "ss", $hash_password, $email);
                
                
                if (mysqli_stmt_execute($stmt)) {
                    
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        header("location: login.php");
                        exit();
                    } else {
                        echo "Something went wrong";
                    }
                    
                } else {
                    echo "Execution failed: " . mysqli_stmt_error($stmt);
                }

                mysqli_stmt_close($stmt);
            } else {
                echo "Preparation failed: " . mysqli_error($conn);
            }
        }
        else{
            $_SESSION["error"] = "The Passwords are not similar";
        }
    }

    
    mysqli_close($conn);
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
        <h1>Reset Password</h1>
        <p>Create a new password for your account</p><br>
        <form id="resetPasswordForm" action="resetPassword.php" method="post">
            <div class="form-group">
                <label for="password">New Password</label>
               <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your new password" required>
                    <button type="button" id="togglePassword" class="toggle-btn">Show</button>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Confirm your password" required>
                    <button type="button" id="togglePassword" class="toggle-btn">Show</button>
                </div>
            </div>
           <br>
            <button type="submit" class="submit-btn" name="changePassword">Change Password</button>
            <div class="back">
                <a href="enterCode.php">Back</a>
            </div>
        </form>
    </div>
</body>
 <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the button text
            this.textContent = type === 'password' ? 'Show' : 'Hide';
        });
    </script>
</html>