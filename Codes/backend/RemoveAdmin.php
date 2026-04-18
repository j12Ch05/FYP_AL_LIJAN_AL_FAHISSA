<?php
session_start();
include("database.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['admin_status_message'] = 'Invalid request.';
    header('Location: AdminPage.php?tab=edit-admins');
    exit();
}

$prof_file_nb = trim($_POST["prof_file_nb"] ?? '');
$_SESSION["current_admin"] = $prof_file_nb;

if ($prof_file_nb === '') {
    $_SESSION['admin_status_message'] = 'Please select a professor first.';
    header('Location: AdminPage.php?tab=edit-admins');
    exit();
}

$sql = "UPDATE professor SET isAdmin = 0 WHERE prof_file_nb = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $prof_file_nb);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    $_SESSION['admin_status_message'] = 'Professor admin status has been removed successfully.';
} else {
    $_SESSION['admin_status_message'] = 'Error: Professor not found or not an admin.';
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

header('Location: AdminPage.php?tab=edit-admins');
exit();

