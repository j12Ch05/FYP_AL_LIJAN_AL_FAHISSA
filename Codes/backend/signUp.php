<?php
    session_start();
    include("database.php");

    $ulDomain = "ul.edu.lb";
    $arabicPattern = "/^[\x{0600}-\x{06FF}\s]+$/u";
    $passwordFormat = "/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";
    $minDate = strtotime('-26 years');


    
    //Creating a function to verify if the file number or the email or phone is already taken 
    function value_exists($conn,$col,$value){
        $sql_query = "select $col from professor where $col = ?";
        $stmt = mysqli_prepare($conn,$sql_query);
        mysqli_stmt_bind_param($stmt,"s",$value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }


    if(isset($_POST["signUp"])){
        $errors = [];

        $firstName = trim($_POST["firstName"]);
        $lastName = trim($_POST["lastName"]);
        $birthDate = $_POST["birthDate"];
        $d = strtotime($birthDate);
        $phone = $_POST["phone"];
        $fileNumber = $_POST["fileNumber"];
        $email = trim($_POST["email"]);
        $password = $_POST["password"];

        //Checking the first and last name if they are written in arabic
        if (!preg_match($arabicPattern, $firstName)) {
            $errors[] = "First name must be in Arabic.";
        }
        if (!preg_match($arabicPattern, $lastName)) {
            $errors[] = "Last name must be in Arabic.";
        }

        //Checking if the user is old enough to Sign up
        if($d > $minDate){
            $errors[] = "You must be at least 26 years old to register";
        }

        if(value_exists($conn,"prof_phone",$phone)){
            $errors[] = "Phone is already taken";
        }

        //Checking the file number
        if (empty($fileNumber) || !filter_var($fileNumber, FILTER_VALIDATE_INT)) {
            $errors[] = "File number is required and must be a number.";
        }
        if(value_exists($conn,"prof_file_nb",$fileNumber)){
            $errors[] = "This file number is already used";
        }

        //Checking the email
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || substr($email, -strlen($ulDomain)) != $ulDomain){
            $errors[] = "Invalid email format";
        }
        if(value_exists($conn,"prof_email",$email)){
            $errors[] = "This email is already used";
        }

        //Checking the password
        if(!preg_match($passwordFormat,$password)){
            $errors[] = "Password must be at least 8 characters, include 1 uppercase letter, 1 number, and 1 special character.";
        }

        if(empty($errors)){
            $date = new DateTime($birthDate);
            $mysqlFormat = $date->format('Y-m-d');

            $_SESSION["firstName"]   = $firstName;
            $_SESSION["lastName"]    = $lastName;
            $_SESSION["birthDate"]   = $date;
            $_SESSION["address"]     = $_POST["address"];
            $_SESSION["phone"]       = $phone;
            $_SESSION["fileNumber"]  = (int)$fileNumber;
            $_SESSION["department"]  = $_POST["department"];
            $_SESSION["category"] = $_POST["category"];
            $_SESSION["email"]   = $email;
            $_SESSION["password"]    = $password;

            header("Location: signUpData.php");
            
            exit();
        }
        else{
            $_SESSION  ['form_errors'] = $errors;
        }

        
    }
    mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Page</title>
    <link rel="icon" href="ULFS.ico">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="box-container">
        <?php 
            if (isset($_SESSION['form_errors'])) {
                foreach ($_SESSION['form_errors'] as $error) {
                    echo '<p style="color: red; font-weight: bold;">' . $error . '</p>';
                }
                unset($_SESSION['form_errors']);
            }
        ?>
        <h1>Sign Up</h1>
        <form id="signUpForm" action="" method="post">
            <div class="form-group">
                <label for="fname">First Name</label>
                <input type="text" id="fname" name="firstName" placeholder="Enter your first name in arabic" maxlength="20" required />
            </div>

            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" id="lname" name="lastName" placeholder="Enter your last name in arabic" maxlength="20" required />
            </div>

            <div class="form-group">
                <label for="birthDate">Birth Date</label>
                <input type="date" id="birthDate" name="birthDate" required />
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" placeholder="Enter your address" maxlength="20" />
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" placeholder="xx xxx xxx" maxlength="10" />
            </div>

            <div class="form-group">
                <label for="filenumber">File Number</label>
                <input type="text" id="filenumber" name="fileNumber" placeholder="Enter your file number" maxlength="10" required/>
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <select class="dropdown-select" name="department" id="department" >
                    <option value="math">Math</option>
                    <option value="css">Computer Science and Statistics</option>
                    <option value="pe">Physics and Electronics</option>
                    <option value="bio">Biology</option>
                    <option value="bioch">Biochemistry</option>
                    <option value="che">Chemistry</option>
                </select>
            </div>

            <div class="form-group">
                <label for="prof_category">Category</label>
                <select name="category" id="prof_category" class="dropdown-select">
                    <option value="متعاقد بالساعة">متعاقد بالساعة</option>
                    <option value="متفرغ">متفرغ</option>
                    <option value="الملاك"> الملاك</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your UL email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" id="togglePassword" class="toggle-btn">Show</button>
                </div>
            </div>

            <button type="submit" class="submit-btn" name="signUp">Sign Up</button>
            <div class="back">
                <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>

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
</body>
</html>
