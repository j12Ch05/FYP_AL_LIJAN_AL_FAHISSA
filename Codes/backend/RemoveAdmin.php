<?php
include("database.php");

    $prof_file_nb = $_POST["prof_file_nb"];

    $sql = "UPDATE professor SET isAdmin = 0 WHERE prof_file_nb = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $prof_file_nb);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo "Professor's admin status has been removed successfully.";
    } else {
        echo "Error: Professor not found or not an admin.";
    }

    mysqli_stmt_close($stmt);


mysqli_close($conn);

