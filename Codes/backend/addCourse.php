<?php
    session_start();
    include("database.php");

    

    function course_exists($conn, $code, $lang,$major) {
        if (empty($lang)) return false; 
        $sql_query = "SELECT course_code from course where course_code = ? and course_lang = ? and major_id = ?";
        $stmt = mysqli_prepare($conn, $sql_query);
        mysqli_stmt_bind_param($stmt, "sss", $code, $lang,$major);
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
        
        if(course_exists($conn, $courseCode, $courseEng,$courseMajor) || course_exists($conn, $courseCode, $courseFr,$courseMajor)) {
            $error = "The code you entered is already taken with the selected language or major";
        }

        if(empty($error)) {
            $ok = true;
            $errorMsg = "";

            // Use a transaction so course + teaching are always in sync
            mysqli_begin_transaction($conn);

            if(!empty($courseEng)) {
                $sql_1 = "INSERT INTO `course`(`course_code`, `course_name`, `course_credit_nb`, `course_hours_nb`, `course_lang`, `course_semester_nb`, `course_level`, `course_category`, `major_id`, `isActive`) 
                          VALUES ('$courseCode','$courseName','$courseCredit','$courseHours','E','$courseSemester','$courseLevel','$courseCategory','$courseMajor','1')";

                if(!mysqli_query($conn, $sql_1)) {
                    $ok = false;
                    $errorMsg .= "English Course Error: " . mysqli_error($conn) . " ";
                }

                if($ok) {
                    $t1 = "INSERT INTO `teaching`(`course_code`, `course_lang`, `major_id`, `prof_file_nb`, `uni_year`) 
                            VALUES ('$courseCode','E','$courseMajor','$courseProf','$uniYear')";

                    if(!mysqli_query($conn, $t1)) {
                        $ok = false;
                        $errorMsg .= "English Teaching Error: " . mysqli_error($conn) . " ";
                    }
                }
            }

            if($ok && !empty($courseFr)) {
                $sql_2 = "INSERT INTO `course`(`course_code`, `course_name`, `course_credit_nb`, `course_hours_nb`, `course_lang`, `course_semester_nb`, `course_level`, `course_category`, `major_id`, `isActive`) 
                          VALUES ('$courseCode','$courseName','$courseCredit','$courseHours','F','$courseSemester','$courseLevel','$courseCategory','$courseMajor','1')";

                if(!mysqli_query($conn, $sql_2)) {
                    $ok = false;
                    $errorMsg .= "French Course Error: " . mysqli_error($conn) . " ";
                }

                if($ok) {
                    $t2 = "INSERT INTO `teaching`(`course_code`, `course_lang`, `major_id`, `prof_file_nb`, `uni_year`) 
                            VALUES ('$courseCode','F','$courseMajor','$courseProf','$uniYear')";

                    if(!mysqli_query($conn, $t2)) {
                        $ok = false;
                        $errorMsg .= "French Teaching Error: " . mysqli_error($conn) . " ";
                    }
                }
            }

            if($ok) {
                mysqli_commit($conn);
                echo "Done";
            } else {
                mysqli_rollback($conn);
                echo $errorMsg;
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