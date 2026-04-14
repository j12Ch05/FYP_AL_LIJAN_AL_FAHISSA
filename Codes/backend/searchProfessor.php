<?php
session_start();
include("database.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["professorId"])) {
    $prof_file_nb = $_POST["professorId"];

    $sql = "SELECT prof_first_name, prof_last_name FROM professor WHERE prof_file_nb = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $prof_file_nb);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $_SESSION['searched_prof_id'] = $prof_file_nb;
        $_SESSION['searched_prof_name'] = $row['prof_first_name'] . ' ' . $row['prof_last_name'];
        $_SESSION['search_message'] = "Professor found.";
    } else {
        $_SESSION['search_message'] = "Professor not found.";
        unset($_SESSION['searched_prof_id']);
        unset($_SESSION['searched_prof_name']);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    header("Location: AdminPage.php?tab=edit-admins");
    exit();
} else {
    header("Location: AdminPage.php?tab=edit-admins");
    exit();
}
?>