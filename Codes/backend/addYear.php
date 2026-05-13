<?php
session_start();
include __DIR__ . '/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newYear = $_POST['newYear'] ?? '';
    $nextYear = $_POST['nextYear'] ?? '';

    if (empty($newYear) || empty($nextYear)) {
        echo json_encode(['status' => 'error', 'message' => 'Both year fields are required.']);
        exit;
    }

    $formattedYear = $newYear . '-' . $nextYear;

    $checkSql = "SELECT uYear FROM uniyear WHERE uYear = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $formattedYear);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);

    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This university year already exists.']);
        mysqli_stmt_close($checkStmt);
        exit;
    }
    mysqli_stmt_close($checkStmt);

    $insertSql = "INSERT INTO uniyear (uYear) VALUES (?)";
    $insertStmt = mysqli_prepare($conn, $insertSql);
    mysqli_stmt_bind_param($insertStmt, "s", $formattedYear);

    if (mysqli_stmt_execute($insertStmt)) {
        echo json_encode(['status' => 'success', 'message' => 'University year created successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }

    mysqli_stmt_close($insertStmt);
    mysqli_close($conn);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
