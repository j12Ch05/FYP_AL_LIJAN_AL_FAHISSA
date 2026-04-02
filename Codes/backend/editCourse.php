<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // keep output clean
    session_start();
    include("database.php");

    header("Content-Type: application/json; charset=utf-8");

    function json_out($arr) {
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$conn) {
        json_out(["status" => "error", "message" => "Database connection failed."]);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        json_out(["status" => "error", "message" => "Invalid method"]);
    }

    $action = $_POST["action"] ?? "";

    if ($action === "disable") {
        $code = trim($_POST["course_code"] ?? "");
        $lang = $_POST["course_lang"] ?? "";

        if ($code === "" || $lang === "") {
            json_out(["status" => "error", "message" => "Missing course code or language."]);
            mysqli_close($conn);
            exit();
        }

        $sql = "UPDATE `course` SET `isActive` = 0 WHERE `course_code` = ? AND `course_lang` = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (disable)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmt, "ss", $code, $lang);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($ok && $affected > 0) {
            json_out(["status" => "success", "message" => "Course disabled.", "isActive" => 0]);
        } elseif ($ok && $affected === 0) {
            json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]); 
        } else {
            json_out(["status" => "error", "message" => $err ?: "Update failed."]);
        }
        mysqli_close($conn);
        exit();
    }

    if ($action === "enable") {
        $code = trim($_POST["course_code"] ?? "");
        $lang = $_POST["course_lang"] ?? "";

        if ($code === "" || $lang === "") {
            json_out(["status" => "error", "message" => "Missing course code or language."]);
            mysqli_close($conn);
            exit();
        }

        $sql = "UPDATE `course` SET `isActive` = 1 WHERE `course_code` = ? AND `course_lang` = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (enable)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmt, "ss", $code, $lang);
        $ok = mysqli_stmt_execute($stmt);
        $err = mysqli_stmt_error($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($ok && $affected > 0) {
            json_out(["status" => "success", "message" => "Course enabled.", "isActive" => 1]);
        } elseif ($ok && $affected === 0) {
            json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]); 
        } else {
            json_out(["status" => "error", "message" => $err ?: "Update failed."]);
        }
        mysqli_close($conn);
        exit();
    }

    if ($action === "update") {
        $code = trim($_POST["course_code"] ?? "");
        $lang = $_POST["course_lang"] ?? "";
        $name = trim($_POST["course_name"] ?? "");
        $credit = (int) ($_POST["course_credit_nb"] ?? 0);
        $hours = (int) ($_POST["course_hours_nb"] ?? 0);
        $level = $_POST["course_level"] ?? "";
        $semester = (int) ($_POST["course_semester_nb"] ?? 0);
        $major = $_POST["major_id"] ?? "";
        $category = $_POST["course_category"] ?? "";
        $prof = $_POST["prof_file_nb"] ?? "";
        $uniYear = trim($_POST["uni_year"] ?? "");

        if ($code === "" || $lang === "") {
            json_out(["status" => "error", "message" => "Missing course code or language."]);
            mysqli_close($conn);
            exit();
        }

        mysqli_begin_transaction($conn);

        $q1 = "UPDATE `course` SET `course_name` = ?, `course_credit_nb` = ?, `course_hours_nb` = ?, `course_semester_nb` = ?, `course_level` = ?, `course_category` = ?, `major_id` = ? WHERE `course_code` = ? AND `course_lang` = ?";
        $st1 = mysqli_prepare($conn, $q1);
        if (!$st1) {
            mysqli_rollback($conn);
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (update course)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param(
            $st1,
            "siiisssss",
            $name,
            $credit,
            $hours,
            $semester,
            $level,
            $category,
            $major,
            $code,
            $lang
        );
        $ok1 = mysqli_stmt_execute($st1);
        $e1 = mysqli_stmt_error($st1);
        $affected1 = mysqli_stmt_affected_rows($st1);
        mysqli_stmt_close($st1);

        $q2 = "UPDATE `teaching` SET `prof_file_nb` = ?, `uni_year` = ? WHERE `course_code` = ? AND `course_lang` = ?";
        $st2 = mysqli_prepare($conn, $q2);
        if (!$st2) {
            mysqli_rollback($conn);
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (update teaching)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($st2, "ssss", $prof, $uniYear, $code, $lang);
        $ok2 = mysqli_stmt_execute($st2);
        $e2 = mysqli_stmt_error($st2);
        $affected2 = mysqli_stmt_affected_rows($st2);
        mysqli_stmt_close($st2);

        if ($ok1 && $ok2) {
            if ($affected1 === 0 && $affected2 === 0) {
                mysqli_rollback($conn);
                json_out(["status" => "error", "message" => "No matching course/teaching row found to update."]);
            } else {
                mysqli_commit($conn);
                json_out(["status" => "success", "message" => "Course updated."]);
            }
        } else {
            mysqli_rollback($conn);
            json_out(["status" => "error", "message" => $e1 ?: $e2 ?: "Update failed."]);
        }

        mysqli_close($conn);
        exit();
    }

    json_out(["status" => "error", "message" => "Unknown action: " . ($action === "" ? "(empty)" : $action)]);
    mysqli_close($conn);
    exit();


?>