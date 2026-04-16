<?php
session_start();
include("database.php");

if (!isset($_SESSION["email"])) {
    header("location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Error: Invalid request method.";
    exit();
}

$email = $_SESSION["email"];
$first_name = trim($_POST["first_name"] ?? '');
$last_name = trim($_POST["last_name"] ?? '');
$father_name = trim($_POST["father_name"] ?? '');
$birth_date = trim($_POST["birth_date"] ?? '');
$address = trim($_POST["address"] ?? '');
$phone = trim($_POST["phone"] ?? '');
$department = trim($_POST["department"] ?? '');
$category = trim($_POST["category"] ?? '');

if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($department) || empty($category)) {
    echo "Error: All required fields must be filled.";
    exit();
}

$valid_categories = ['متعاقد بالساعة', 'ملاك', 'متفرغ'];
if (!in_array($category, $valid_categories)) {
    echo "Error: Invalid category selected.";
    exit();
}

$birth_date_obj = DateTime::createFromFormat('d-M-Y', $birth_date);
if (!$birth_date_obj || $birth_date_obj->format('d-M-Y') !== $birth_date) {
    echo "Error: Invalid birth date format. Use DD-MMM-YYYY, e.g. 15-Apr-1980.";
    exit();
}

$age = $birth_date_obj->diff(new DateTime('now'))->y;
if ($age < 26) {
    echo "Error: You must be at least 26 years old.";
    exit();
}

$birth_date_db = $birth_date_obj->format('Y-m-d');

$sql_update = "UPDATE professor SET
              prof_first_name = ?,
              prof_last_name = ?,
              prof_father_name = ?,
              prof_birth_date = ?,
              prof_address = ?,
              prof_phone = ?,
              dep_id = ?,
              prof_category = ?
              WHERE prof_email = ?";

$stmt = mysqli_prepare($conn, $sql_update);
if (!$stmt) {
    echo "Error: Database prepare failed. " . mysqli_error($conn);
    exit();
}

mysqli_stmt_bind_param($stmt, "sssssssss", $first_name, $last_name,$father_name, $birth_date_db, $address, $phone, $department, $category, $email);

if (mysqli_stmt_execute($stmt)) {
    echo "Profile updated successfully!";
} else {
    echo "Error updating profile: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>