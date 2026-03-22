<?php
    session_start();
    include("database.php");

    
    

    //if email inserted is really in the database 
    function value_exists($conn,$col,$value){
        $sql_query = "select $col from professor where $col = ?";
        $stmt = mysqli_prepare($conn,$sql_query);
        mysqli_stmt_bind_param($stmt,"s",$value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }

    if(isset($_POST["sendCode"])){
        $error = "";

        $email = $_POST["email"];

        if(!value_exists($conn,"prof_email",$email)){
            $error = "This email do not exists. Please sign up";
        }


        if(empty($error)){
            $_SESSION["email"] = $email;


            header("location: enterCode.php");
            exit();
        }
        else{
            $_SESSION["error"] = $error;
        }

    }


    mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        <h1>Forgot Password</h1>
        <form id="forgotPasswordForm" action="forgotPassword.php" method="post">
            <p>
            Enter your email address to receive a reset code.
            </p><br>
            <div class="form-group">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="Enter your UL email" 
                    required
                    />
            </div>
            <div class="error-message" id="emailError">Please enter a valid email</div><br>
            <button type="submit" class="submit-btn" name="sendCode">Send Code</button>
            <div class="back">
                <a href="login.html">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>