<?php
    session_start();
    include("database.php");

    

    function course_exists($conn, $code, $lang) {
        if (empty($lang)) return false; 
        $sql_query = "SELECT course_code from course where course_code = ? and course_lang = ?";
        $stmt = mysqli_prepare($conn, $sql_query);
        mysqli_stmt_bind_param($stmt, "ss", $code, $lang);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }

    if(isset($_POST["submitCourse"])) {
        $error = "";

        $courseCode = mysqli_real_escape_string($conn, $_POST["courseCode"]);
        $courseEng = isset($_POST["courseEng"]) ? "E" : "";
        $courseFr = isset($_POST["courseFr"]) ? "F" : "";
        $courseName = mysqli_real_escape_string($conn, $_POST["courseName"]);
        $courseCredit = mysqli_real_escape_string($conn, $_POST["courseCredit"]);
        $courseProf = mysqli_real_escape_string($conn, $_POST["courseProf"]);
        $courseMajor = mysqli_real_escape_string($conn, $_POST["courseMajor"]);
        $courseLevel = mysqli_real_escape_string($conn, $_POST["courseLevel"]);
        $courseSemester = (int)$_POST["courseSemester"];
        $courseCategory = mysqli_real_escape_string($conn, $_POST["courseCategory"]);
        $courseHours = mysqli_real_escape_string($conn, $_POST["courseHours"]);

        $uniYear = mysqli_real_escape_string($conn, $_POST["courseYear"]);;

        if(empty($courseEng) && empty($courseFr)) {
            $error = "You must select at least a language for the course";
        }
        
        if(course_exists($conn, $courseCode, $courseEng) || course_exists($conn, $courseCode, $courseFr)) {
            $error = "The code you entered is already taken with the selected language";
        }

        if(empty($error)) {
            $ok = true;

            if(!empty($courseEng)) {
                $sql_1 = "INSERT INTO `course`(`course_code`, `course_name`, `course_credit_nb`, `course_hours_nb`, `course_lang`, `course_semester_nb`, `course_level`, `course_category`, `major_id`, `isActive`) 
                          VALUES ('$courseCode','$courseName','$courseCredit','$courseHours','E','$courseSemester','$courseLevel','$courseCategory','$courseMajor','1')";

                if(!mysqli_query($conn, $sql_1)) {
                    $ok = false;
                    echo "English Error: " . mysqli_error($conn);
                }

                $t1 = "INSERT INTO `teaching`(`course_code`, `course_lang`, `prof_file_nb`, `uni_year`) 
                        VALUES ('$courseCode','E','$courseProf','$uniYear')";

                if($ok && !mysqli_query($conn, $t1)) {
                    $ok = false;
                    echo "English Error: " . mysqli_error($conn);
                }
            }

            if(!empty($courseFr)) {
                $sql_2 = "INSERT INTO `course`(`course_code`, `course_name`, `course_credit_nb`, `course_hours_nb`, `course_lang`, `course_semester_nb`, `course_level`, `course_category`, `major_id`, `isActive`) 
                          VALUES ('$courseCode','$courseName','$courseCredit','$courseHours','F','$courseSemester','$courseLevel','$courseCategory','$courseMajor','1')";

                if($ok && !mysqli_query($conn, $sql_2)) {
                    $ok = false;
                    echo "French Error: " . mysqli_error($conn);
                }

                $t2 = "INSERT INTO `teaching`(`course_code`, `course_lang`, `prof_file_nb`, `uni_year`) 
                        VALUES ('$courseCode','F','$courseProf','$uniYear')";

                if($ok && !mysqli_query($conn, $t2)) {
                    $ok = false;
                    echo "French Error: " . mysqli_error($conn);
                }
            }

            if($ok) {
                echo "Done";
            }
        } else {
            $_SESSION["error"] = $error;
        }
    }

    unset(
    $_POST['courseCode'],
    $_POST['courseEng'],
    $_POST['courseFr'],
    $_POST['courseName'],
    $_POST['courseCredit'],
    $_POST['courseProf'],
    $_POST['courseMajor'],
    $_POST['courseLevel'],
    $_POST['courseSemester'],
    $_POST['courseCategory']
    );
    

    mysqli_close($conn);
?>