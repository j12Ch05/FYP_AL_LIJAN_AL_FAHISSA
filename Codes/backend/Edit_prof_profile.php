<?php
    session_start();
    include("database.php");

    if (!isset($_SESSION["email"])) {
        header("location: login.html");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_SESSION["email"];

        $first_name = trim($_POST["first_name"]);
        $last_name = trim($_POST["last_name"]);
        $birth_date = $_POST["birth_date"];
        $address = trim($_POST["address"]);
        $phone = trim($_POST["phone"]);
        $department = $_POST["department"];

        if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($department)) {
            echo "Error: All required fields must be filled.";
            exit();
        }

        $birth_date_obj = DateTime::createFromFormat('d-M-Y', $birth_date);
        if (!$birth_date_obj) {
            echo "Error: Invalid birth date format.";
            exit();
        }
        $birth_date_db = $birth_date_obj->format('Y-m-d');

        $sql_update = "UPDATE professor SET
                      prof_first_name = ?,
                      prof_last_name = ?,
                      prof_birth_date = ?,
                      prof_address = ?,
                      prof_phone = ?,
                      dep_id = ?
                      WHERE prof_email = ?";

        $stmt = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt, "sssssss", $first_name, $last_name, $birth_date_db, $address, $phone, $department, $email);

        if (mysqli_stmt_execute($stmt)) {
            echo "Profile updated successfully!";
        } else {
            echo "Error updating profile: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "Invalid request method.";
    }

    mysqli_close($conn);
?>