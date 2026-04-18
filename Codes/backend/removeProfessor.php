<?php
session_start();
include "database.php";

header('Content-Type: application/json');

if (!isset($_SESSION["email"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$prof_id = $_POST["prof_file_nb"] ?? null;

if (!$prof_id) {
    echo json_encode(["status" => "error", "message" => "Professor ID is required"]);
    exit;
}

// Ensure the admin isn't deleting themselves
if (isset($_SESSION["current_admin"]) && $prof_id == $_SESSION["current_admin"]) {
    echo json_encode(["status" => "error", "message" => "You cannot delete your own account."]);
    exit;
}

// Check if the professor teaches any courses
$sql_check_teaching = "SELECT COUNT(*) as course_count FROM teaching WHERE prof_file_nb = ?";
$stmt_check = mysqli_prepare($conn, $sql_check_teaching);
if ($stmt_check) {
    mysqli_stmt_bind_param($stmt_check, "i", $prof_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $row_check = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($row_check && $row_check['course_count'] > 0) {
        echo json_encode([
            "status" => "error", 
            "message" => "This professor is currently assigned to " . $row_check['course_count'] . " course(s). You must re-assign these courses to another professor before removing this account."
        ]);
        exit;
    }
}

// To prevent orphaned references in correctors table, set them to NULL if the professor was a 2nd or 3rd corrector
$sql_clean_corr1 = "UPDATE correctors SET second_corrector_file_nb = NULL WHERE second_corrector_file_nb = ?";
$stmt_clean1 = mysqli_prepare($conn, $sql_clean_corr1);
if ($stmt_clean1) {
    mysqli_stmt_bind_param($stmt_clean1, "i", $prof_id);
    mysqli_stmt_execute($stmt_clean1);
    mysqli_stmt_close($stmt_clean1);
}

$sql_clean_corr2 = "UPDATE correctors SET third_corrector_file_nb = NULL WHERE third_corrector_file_nb = ?";
$stmt_clean2 = mysqli_prepare($conn, $sql_clean_corr2);
if ($stmt_clean2) {
    mysqli_stmt_bind_param($stmt_clean2, "i", $prof_id);
    mysqli_stmt_execute($stmt_clean2);
    mysqli_stmt_close($stmt_clean2);
}

// Delete from professor. 
// Related teaching rows will be automatically deleted because of the ON DELETE CASCADE constraint.
$sql = "DELETE FROM professor WHERE prof_file_nb = ?";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $prof_id);
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode(["status" => "success", "message" => "Professor deleted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Professor not found or already deleted."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete professor due to database constraints: " . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(["status" => "error", "message" => "Database error preparing deletion query."]);
}

mysqli_close($conn);
?>
