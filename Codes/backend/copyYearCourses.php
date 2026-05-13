<?php
session_start();
include __DIR__ . '/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromYear = $_POST['fromYear'] ?? '';
    $toYear = $_POST['toYear'] ?? '';

    if (empty($fromYear) || empty($toYear)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select both years.']);
        exit;
    }

    if ($fromYear === $toYear) {
        echo json_encode(['status' => 'error', 'message' => 'Source and target years must be different.']);
        exit;
    }

    $checkSql = "SELECT COUNT(*) as count FROM teaching WHERE uni_year = ?";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $toYear);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $checkRow = mysqli_fetch_assoc($checkRes);

    if ($checkRow['count'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'The target year already has courses. Copying is not allowed to prevent overwriting.']);
        mysqli_stmt_close($checkStmt);
        exit;
    }
    mysqli_stmt_close($checkStmt);

    
    $fetchSql = "SELECT t.*, c.course_name, c.course_credit_nb, c.course_hours_nb, c.course_semester_nb, c.course_level, c.course_category 
                 FROM teaching t 
                 JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang AND t.major_id = c.major_id 
                 WHERE t.uni_year = ?";
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    mysqli_stmt_bind_param($fetchStmt, "s", $fromYear);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);

    $coursesToCopy = [];
    while ($row = mysqli_fetch_assoc($fetchRes)) {
        $coursesToCopy[] = $row;
    }
    mysqli_stmt_close($fetchStmt);

    if (empty($coursesToCopy)) {
        echo json_encode(['status' => 'error', 'message' => 'The source year has no courses to copy.']);
        exit;
    }

    mysqli_begin_transaction($conn);
    $okCopy = true;
    
    $courseSql = "INSERT INTO course (course_code, course_name, course_credit_nb, course_hours_nb, course_lang, course_semester_nb, course_level, course_category, major_id, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $courseStmt = mysqli_prepare($conn, $courseSql);

    $teachingSql = "INSERT INTO teaching (course_code, course_lang, major_id, prof_file_nb, uni_year, isActive) VALUES (?, ?, ?, ?, ?, ?)";
    $teachingStmt = mysqli_prepare($conn, $teachingSql);

    foreach ($coursesToCopy as $course) {
        mysqli_stmt_bind_param(
            $courseStmt, 
            "ssiisssssi", 
            $course['course_code'], 
            $course['course_name'], 
            $course['course_credit_nb'], 
            $course['course_hours_nb'], 
            $course['course_lang'], 
            $course['course_semester_nb'], 
            $course['course_level'], 
            $course['course_category'], 
            $course['major_id'], 
            $course['isActive']
        );
        if (!mysqli_stmt_execute($courseStmt)) {
            $okCopy = false;
            break;
        }

        mysqli_stmt_bind_param(
            $teachingStmt, 
            "sssssi", 
            $course['course_code'], 
            $course['course_lang'], 
            $course['major_id'], 
            $course['prof_file_nb'], 
            $toYear,
            $course['isActive']
        );
        if (!mysqli_stmt_execute($teachingStmt)) {
            $okCopy = false;
            break;
        }
    }

    if ($okCopy) {
        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => 'Successfully copied ' . count($coursesToCopy) . ' courses to ' . $toYear . '.']);
    } else {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred during copy: ' . mysqli_error($conn)]);
    }

    mysqli_stmt_close($courseStmt);
    mysqli_stmt_close($teachingStmt);
    mysqli_close($conn);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}

?>