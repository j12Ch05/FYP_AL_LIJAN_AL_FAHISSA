<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // keep output clean
    session_start();
    include __DIR__ .'/database.php';

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
        $major = $_POST["major_id"] ?? "";
        $uniyear = trim($_POST["uni_year"] ?? "");

        if ($code === "" || $lang === "" || $major === "" || $uniyear === ""){
            json_out(["status" => "error", "message" => "Missing course code, language, or major."]);
            mysqli_close($conn);
            exit();
        }

        $sqlCourse = "UPDATE `course` SET `isActive` = 0 WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND 
        `uni_year` = ?";
        $stmtCourse = mysqli_prepare($conn, $sqlCourse);
        if (!$stmtCourse) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (disable course)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmtCourse, "ssss", $code, $lang, $major,$uniyear);
        $okCourse = mysqli_stmt_execute($stmtCourse);
        $courseErr = mysqli_stmt_error($stmtCourse);
        $courseAffected = mysqli_stmt_affected_rows($stmtCourse);
        mysqli_stmt_close($stmtCourse);

        if (!$okCourse) {
            json_out(["status" => "error", "message" => $courseErr ?: "Course disable failed."]);
            mysqli_close($conn);
            exit();
        }

        $sqlTeaching = "UPDATE `teaching` SET `isActive` = 0 WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
        $stmtTeaching = mysqli_prepare($conn, $sqlTeaching);
        if (!$stmtTeaching) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (disable teaching)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmtTeaching, "ssss", $code, $lang, $major,$uniyear);
        $okTeaching = mysqli_stmt_execute($stmtTeaching);
        $teachingErr = mysqli_stmt_error($stmtTeaching);
        mysqli_stmt_close($stmtTeaching);

        if (!$okTeaching) {
            json_out(["status" => "error", "message" => $teachingErr ?: "Teaching disable failed."]);
            mysqli_close($conn);
            exit();
        }

        if ($courseAffected > 0) {
            json_out(["status" => "success", "message" => "Course disabled.", "isActive" => 0]);
        } else {
                // If no rows were affected, try to provide a clearer reason.
                $checkQ = "SELECT isActive, uni_year FROM `course` WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
                $stCheck = mysqli_prepare($conn, $checkQ);
                if ($stCheck) {
                    mysqli_stmt_bind_param($stCheck, "ssss", $code, $lang, $major, $uniyear);
                    mysqli_stmt_execute($stCheck);
                    $resCheck = mysqli_stmt_get_result($stCheck);
                    $rowCheck = mysqli_fetch_assoc($resCheck);
                    mysqli_stmt_close($stCheck);

                    if ($rowCheck) {
                        // Row exists but no affected rows -> likely already disabled
                        if ((int)$rowCheck['isActive'] === 0) {
                            json_out(["status" => "success", "message" => "Course already disabled.", "isActive" => 0]);
                        } else {
                            json_out(["status" => "error", "message" => "Course exists but update did not apply."]);
                        }
                    } else {
                        // Try to find the course ignoring uni_year to suggest a uni_year mismatch
                        $checkQ2 = "SELECT uni_year FROM `course` WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? LIMIT 1";
                        $stCheck2 = mysqli_prepare($conn, $checkQ2);
                        if ($stCheck2) {
                            mysqli_stmt_bind_param($stCheck2, "sss", $code, $lang, $major);
                            mysqli_stmt_execute($stCheck2);
                            $resCheck2 = mysqli_stmt_get_result($stCheck2);
                            $rowCheck2 = mysqli_fetch_assoc($resCheck2);
                            mysqli_stmt_close($stCheck2);

                            if ($rowCheck2) {
                                json_out(["status" => "error", "message" => "No course found for the requested university year. Found course in year: " . ($rowCheck2['uni_year'] ?? '(unknown)')]);
                            }
                        }
                        json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]);
                    }
                } else {
                    json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]);
                }
        }
        mysqli_close($conn);
        exit();
    }

    if ($action === "enable") {
        $code = trim($_POST["course_code"] ?? "");
        $lang = $_POST["course_lang"] ?? "";
        $major = $_POST["major_id"] ?? "";
        $uniyear = trim($_POST["uni_year"] ?? "");

        if ($code === "" || $lang === "" || $major === "" || $uniyear === "") {
            json_out(["status" => "error", "message" => "Missing course code, language, or major."]);
            mysqli_close($conn);
            exit();
        }

        $sqlCourse = "UPDATE `course` SET `isActive` = 1 WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND 
        `uni_year` = ?";
        $stmtCourse = mysqli_prepare($conn, $sqlCourse);
        if (!$stmtCourse) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (enable course)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmtCourse, "ssss", $code, $lang, $major,$uniyear);
        $okCourse = mysqli_stmt_execute($stmtCourse);
        $courseErr = mysqli_stmt_error($stmtCourse);
        $courseAffected = mysqli_stmt_affected_rows($stmtCourse);
        mysqli_stmt_close($stmtCourse);

        if (!$okCourse) {
            json_out(["status" => "error", "message" => $courseErr ?: "Course enable failed."]);
            mysqli_close($conn);
            exit();
        }

        $sqlTeaching = "UPDATE `teaching` SET `isActive` = 1 WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
        $stmtTeaching = mysqli_prepare($conn, $sqlTeaching);
        if (!$stmtTeaching) {
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (enable teaching)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($stmtTeaching, "ssss", $code, $lang, $major,$uniyear);
        $okTeaching = mysqli_stmt_execute($stmtTeaching);
        $teachingErr = mysqli_stmt_error($stmtTeaching);
        mysqli_stmt_close($stmtTeaching);

        if (!$okTeaching) {
            json_out(["status" => "error", "message" => $teachingErr ?: "Teaching enable failed."]);
            mysqli_close($conn);
            exit();
        }

        if ($courseAffected > 0) {
            json_out(["status" => "success", "message" => "Course enabled.", "isActive" => 1]);
        } else {
                // Provide more helpful diagnostics when no rows were affected
                $checkQ = "SELECT isActive, uni_year FROM `course` WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
                $stCheck = mysqli_prepare($conn, $checkQ);
                if ($stCheck) {
                    mysqli_stmt_bind_param($stCheck, "ssss", $code, $lang, $major, $uniyear);
                    mysqli_stmt_execute($stCheck);
                    $resCheck = mysqli_stmt_get_result($stCheck);
                    $rowCheck = mysqli_fetch_assoc($resCheck);
                    mysqli_stmt_close($stCheck);

                    if ($rowCheck) {
                        if ((int)$rowCheck['isActive'] === 1) {
                            json_out(["status" => "success", "message" => "Course already enabled.", "isActive" => 1]);
                        } else {
                            json_out(["status" => "error", "message" => "Course exists but update did not apply."]);
                        }
                    } else {
                        $checkQ2 = "SELECT uni_year FROM `course` WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? LIMIT 1";
                        $stCheck2 = mysqli_prepare($conn, $checkQ2);
                        if ($stCheck2) {
                            mysqli_stmt_bind_param($stCheck2, "sss", $code, $lang, $major);
                            mysqli_stmt_execute($stCheck2);
                            $resCheck2 = mysqli_stmt_get_result($stCheck2);
                            $rowCheck2 = mysqli_fetch_assoc($resCheck2);
                            mysqli_stmt_close($stCheck2);

                            if ($rowCheck2) {
                                json_out(["status" => "error", "message" => "No course found for the requested university year. Found course in year: " . ($rowCheck2['uni_year'] ?? '(unknown)')]);
                            }
                        }
                        json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]);
                    }
                } else {
                    json_out(["status" => "error", "message" => "No course found with given code/language. Nothing was changed."]);
                }
        }
        mysqli_close($conn);
        exit();
    }

    if ($action === "update") {
        $code = trim($_POST["course_code"] ?? "");
        $lang = $_POST["course_lang"] ?? "";
        $old_major = $_POST["old_major_id"] ?? "";
        $name = trim($_POST["course_name"] ?? "");
        $credit = (int) ($_POST["course_credit_nb"] ?? 0);
        $hours = (int) ($_POST["course_hours_nb"] ?? 0);
        $level = $_POST["course_level"] ?? "";
        $semester = (int) ($_POST["course_semester_nb"] ?? 0);
        $major = $_POST["major_id"] ?? "";
        $category = $_POST["course_category"] ?? "";
        $prof = $_POST["prof_file_nb"] ?? "";
        $uniYear = trim($_POST["uni_year"] ?? "");

        if ($code === "" || $lang === "" || $old_major === "" || $uniYear === "") {
            json_out(["status" => "error", "message" => "Missing course code, language, or original major."]);
            mysqli_close($conn);
            exit();
        }

        mysqli_begin_transaction($conn);

        $q1 = "UPDATE `course` SET `course_name` = ?, `course_credit_nb` = ?, `course_hours_nb` = ?, `course_semester_nb` = ?, `course_level` = ?, `course_category` = ?, `major_id` = ? WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
        $st1 = mysqli_prepare($conn, $q1);
        if (!$st1) {
            mysqli_rollback($conn);
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (update course)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param(
            $st1,
            "siiisssssss",
            $name,
            $credit,
            $hours,
            $semester,
            $level,
            $category,
            $major,
            $code,
            $lang,
            $old_major,
            $uniYear
        );
        $ok1 = mysqli_stmt_execute($st1);
        $e1 = mysqli_stmt_error($st1);
        $affected1 = mysqli_stmt_affected_rows($st1);
        mysqli_stmt_close($st1);

        $q2 = "UPDATE `teaching` SET `major_id` = ?, `prof_file_nb` = ?, `uni_year` = ? WHERE `course_code` = ? AND `course_lang` = ? AND `major_id` = ? AND `uni_year` = ?";
        $st2 = mysqli_prepare($conn, $q2);
        if (!$st2) {
            mysqli_rollback($conn);
            json_out(["status" => "error", "message" => mysqli_error($conn) ?: "Prepare failed (update teaching)."]);
            mysqli_close($conn);
            exit();
        }
        mysqli_stmt_bind_param($st2, "sssssss", $major, $prof, $uniYear, $code, $lang, $old_major,$uniYear);
        $ok2 = mysqli_stmt_execute($st2);
        $e2 = mysqli_stmt_error($st2);
        $affected2 = mysqli_stmt_affected_rows($st2);
        mysqli_stmt_close($st2);

        if ($ok1 && $ok2) {
            mysqli_commit($conn);
            json_out(["status" => "success", "message" => "Course updated."]);
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


