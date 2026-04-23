<?php
    session_start();
    include("database.php");

    //always define the error variable at the beginning of the code
    $error = "";
    //if email inserted is really in the database 
    function value_exists($conn,$col,$value){
        $sql_query = "select $col from professor where $col = ?";
        $stmt = mysqli_prepare($conn,$sql_query);
        mysqli_stmt_bind_param($stmt,"s",$value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }

    if(isset($_POST["login"])){
       
        $email = $_POST["email"];
        $password = $_POST["password"];
        $admin = isset($_POST["asAdmin"]);

        if(!value_exists($conn,"prof_email",$email)){
            $error = "This email do not exists. Please sign up";
        }
        else{
            $sql_query = "select prof_password,isAdmin from professor where prof_email = ?";
            $stmt = mysqli_prepare($conn,$sql_query);
            mysqli_stmt_bind_param($stmt,"s",$email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            if(!password_verify($password,$row["prof_password"])){
                $error = "Incorrect password. Please try again.";
            }

            //check if someone is an admin or not
            if($admin && !$row["isAdmin"]){
                $error = "You cannot login as an admin";
            }
            if(empty($error)){
                $_SESSION["email"] = $email;
                if($admin){
                    header("location: AdminPage.php");
                }
                else{
                    header("location: ProfessorPage.php");
                }
                exit();
            }
            
        }
        
    }
    if(!empty($error)){
            $_SESSION["error"] = $error;
        }
    mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="ULFS.ico">

</head>
<body>
    <div class="box-container">
         <?php 
         //Displaying  the error in red
            if (isset($_SESSION['error'])) {
                
                    echo '<p style="color: red; font-weight: bold;">' . $_SESSION['error'] . '</p>';
                unset($_SESSION['error']);
            }
        ?>
        <h1>Login</h1>
        <form id="loginForm" action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your UL email" 
                    required
                >
                <div class="error-message" id="emailError">Please enter a valid email</div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password" 
                    required
                >
                <div class="error-message" id="passwordError">Password is required</div>
            </div>

            <div class="login-admin">
                <label>
                    <input type="checkbox" name="asAdmin">
                    Login as admin
                </label>
                <a href="forgotPassword.php">Forgot Password?</a>
            </div>

            <button type="submit" class="submit-btn" name="login">Login</button>

            <div class="signup-link">
                Don't have an account? <a href="signUp.php">Sign up here</a>
            </div>
        </form>
    </div>
</body>
</html>
