<?php
    session_start();
    include("database.php");

    header("Content-Type: application/json; charset=utf-8");

    if (!isset($_POST["searchBtn"])) {
        echo json_encode(["status" => "error", "message" => "Invalid request"]);
        mysqli_close($conn);
        exit();
    }

    $courseCode = trim($_POST["searchCode"] ?? "");
    $courseLang = $_POST["searchCourseLang"] ?? $_POST["courseLang"] ?? "";

    if ($courseCode === "" || $courseLang === "") {
        echo json_encode(["status" => "error", "message" => "Course code and language are required."]);
        mysqli_close($conn);
        exit();
    }

    $query = "SELECT c.course_code, c.course_lang, c.course_name, c.course_credit_nb, c.course_hours_nb,
            c.course_level, c.course_semester_nb, c.major_id, c.course_category, c.isActive,
            p.prof_file_nb, t.uni_year
        FROM course c
        JOIN teaching t ON t.course_code = c.course_code AND t.course_lang = c.course_lang
        JOIN professor p ON p.prof_file_nb = t.prof_file_nb
        WHERE c.course_code = ? AND c.course_lang = ?";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $courseCode, $courseLang);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        echo json_encode([
            "status" => "success",
            "course_code" => $row["course_code"],
            "course_lang" => $row["course_lang"],
            "course_name" => $row["course_name"],
            "course_credit_nb" => (int) $row["course_credit_nb"],
            "course_hours_nb" => (int) $row["course_hours_nb"],
            "course_level" => $row["course_level"],
            "course_semester_nb" => (string) $row["course_semester_nb"],
            "major_id" => (string) $row["major_id"],
            "course_category" => $row["course_category"],
            "prof_file_nb" => $row["prof_file_nb"],
            "uni_year" => $row["uni_year"] ?? "",
            "isActive" => (int) $row["isActive"],
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Course not found."]);
    }

    mysqli_close($conn);
