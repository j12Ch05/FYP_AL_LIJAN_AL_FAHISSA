<?php
    session_start();
    include("database.php");

    
    $sql_professors = "SELECT prof_first_name, prof_last_name FROM professor WHERE dep_id = 'css'";
    $stmt_p = mysqli_prepare($conn, $sql_professors);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);

    $professors = [];
    while($row = mysqli_fetch_assoc($res_p)){
        $professors[] = $row["prof_first_name"] . " " . $row["prof_last_name"];
    }
    mysqli_stmt_close($stmt_p); 

    
    $sql_majors = "SELECT major_name FROM major WHERE dep_id = 'css'";
    $stmt_m = mysqli_prepare($conn, $sql_majors);
    mysqli_stmt_execute($stmt_m);
    $res_m = mysqli_stmt_get_result($stmt_m);

    $majors = [];
    while($row = mysqli_fetch_assoc($res_m)){
        $majors[] = $row["major_name"];
    }
    mysqli_stmt_close($stmt_m); 


    $_SESSION["professors"] = !empty($professors) ? $professors : [];
    $_SESSION["majors"] = !empty($majors) ? $majors : [];

    mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
    <link rel="stylesheet" href="AdminPage.css">
    <link rel="icon" href="ULFS.ico">
</head>
<body>

    <input type="radio" name="nav" id="tab-professors" checked>
    <input type="radio" name="nav" id="tab-courses">
    <input type="radio" name="nav" id="tab-correctors">
    <input type="radio" name="nav" id="tab-edit-admins">

    <aside class="sidebar">
        <div class="logo-section">
            <h2>Lebanese University</h2>
            <p>Faculty of Science II</p>
        </div>
        
        <nav>
            <label for="tab-professors" class="nav-item label-professors">
                <span class="icon">🧑‍🏫</span> Professors
            </label>
            <label for="tab-courses" class="nav-item label-courses">
                <span class="icon">📚</span> Courses
            </label>
            <label for="tab-correctors" class="nav-item label-correctors">
                <span class="icon">✍️</span> Correctors
            </label>
            <label for="tab-edit-admins" class="nav-item label-edit-admins">
                <span class="icon">⚙️</span> Edit Admins
            </label>

        </nav>
    </aside>

    <main class="main-content">
        <header class="welcome-header">
            Welcome Admin
        </header>

        <section id="content-professors" class="tab-content">
            <h1>Professors</h1>
            <details class="dropdown-menu">
                <summary>View All Professor</summary>
                    <div class="dropdown-content">
                         <div class="form-group">
                            <label for="professorSearchBy">Filter by</label>
                            <select id="professorSearchBy" name="professorSearchBy" style="width: 220px; margin-bottom: 10px;">
                                <option value="">-- choose filter --</option>
                                <option value="all">All</option>
                                <option value="department">Department</option>
                                <option value="course taught">Course</option>
                            </select>
                    </div>
                </details>

            <details class="dropdown-menu">
                    <summary>Search Professor</summary>
                    <form id="searchProfessor" action="searchProfessor.php" method="post">
                        <div class="dropdown-content">
                        <div class="form-group">
                            <label for="professorSearchBy">Filter by</label>
                            <select id="professorSearchBy" name="professorSearchBy" style="width: 220px; margin-bottom: 10px;">
                                <option value="">-- choose filter --</option>
                                <option value="id">ID</option>
                                <option value="name">Name</option>
                                <option value="course">Course</option>
                            </select>
                            <label for="professorQuery">Enter the search term</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="text" id="professorQuery" name="professorQuery" placeholder="Enter department, name, or course" style="width: 200px;">
                                <input type="button"  id="searchID" name="searchID" value="Search">
                            </div><br>
                            <!--The professor information need to fetched from the backend-->
                            <p>Professor ID: </p><br>
                            <p>Professor Name: </p><br>
                            <p>Courses Taught(with language): </p><br>
                            <p>Professor Department: </p><br>
                            <p>Professor Birth Date: </p><br>
                            <p>Professor Email: </p><br>
                            <p>Professor Phone: </p><br>
                            <p>Professor Rank(admin or not): </p><br>
                        </div>
                        <input type="button" id="remove" name="removeProfessor" class="btn" value="remove Professor">

                        
                        </div>
                        </div>
                    </form>
                </details>
        </section>

        <section id="content-courses" class="tab-content">
            <h1>Courses</h1>
            <details class="dropdown-menu">
                <summary>Add Course</summary>
                    <div class="dropdown-content">


                    <?php 
                        include("database.php");
                        
                        //Check if the course code was already taken
                        function course_exists($conn,$code,$lang){
                            $sql_query = "SELECT course_code from course where course_code = ? and course_lang = ?";
                            $stmt = mysqli_prepare($conn,$sql_query);
                            mysqli_stmt_bind_param($stmt,"ss",$code,$lang);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            
                            return mysqli_num_rows($result) > 0;
                        }

                        if(isset($_POST["submitCourse"])){
                            $errors = [];

                            $courseCode = trim($_POST["courseCode"]);
                            $courseEng = isset($_POST["courseEng"]) ? "E":"";
                            $courseFr = isset($_POST["courseFr"]) ? "F":"";
                            

                            if(empty($courseEng) && empty($courseFr)){
                                $errors[] = "You must select a language for the course";
                            }
                            else{
                                
                                $courseName = trim($_POST["courseName"]);
                                $courseCredit = $_POST["courseCredit"];
                                $courseProf = $_POST["courseProf"];
                                $courseMajor = $_POST["courseMajor"];
                                $courseLevel = $_POST["courseLevel"];
                            }
                        }
                    
                        mysqli_close($conn);
                    ?>


                        <form id="addCourse" action="addCourse.php" method="post">
                            <div class="form-group">
                                <label for="courseCode">Enter the code of the course</label>
                                <input type="text" id="courseCode" name="courseCode" placeholder="I3341,M2250,P1101..."><br>
                                <label for="courseName">Enter the name of the course</label>
                                <input type="text" id="courseName" name="courseName" placeholder="Enter the name"><br>
                                <label for="courseCredit">Enter the credits of the course</label>
                                <input type="number" min="3" max="6" name="courseCredit" id="courseCredit"><br>
                                <label for="courseLang">Enter the language of the course</label>
                                <label class="checkbox-label" id="courseEng"><input type="checkbox" id="courseEng" name="courseEng"> English</label>
                                <label for="courseFr" class="checkbox-label"><input type="checkbox" id="courseFr" name="courseFr">French</label>
                                <label for="courseProf">Choose the professor of the course</label>
                                <select name="courseProf" id="courseProf">
                                   <?php
                                        //Preparing the dropdown list for choosing the name of the professor
                                        foreach($_SESSION["professors"] as $professor){
                                            echo "<option value='$professor'>$professor</option>";
                                        }
                
                                   ?>
                                </select><br>
                                <label for="courseMajor">Choose the major</label>
                                <select name="courseMajor" id="courseMajor">
                                    <?php
                                        foreach($_SESSION["majors"] as $major){
                                            echo "<option value='$major'>$major</option>";
                                        }
                                    ?>
                                </select><br>
                                <label for="courseLevel">Enter the level</label>
                                <select id="courseLevel" name="courseLevel">
                                    <option value="L1">L1</option>
                                    <option value="L2">L2</option>
                                    <option value="L3">L3</option>
                                    <option value="M1">M1</option>
                                </select><br>

                            </div>
                            
                            <input type="button" name="submitCourse" class="btn" value="Submit">
                            <input type="reset" name="cancelCourse" class="btn" value="Cancel">
                        </form>
                    </div>
                </details>
                <details class="dropdown-menu">
                    <summary>Search Course</summary>
                    <form id="searchCourse" action="searchCourse.php" method="post">
                        <div class="dropdown-content">
                        <div class="form-group">
                            <search>
                                <label for="courseCode">Course Code</label>
                                <input type="text" id="courseCode" name="courseCode" placeholder="I3350,P1100....">
                                <label for="courseLang">Course Language</label>
                                <select name="courseLang" id="courseLang">
                                    <option value="courseEng">E</option>
                                    <option value="courseFr">F</option>
                                </select>
                                <input type="button" id="searchbtn" name="searchBtn" class="btn" value="Search">
                            </search><br>
                            <!--The course information need to fetched from the backend-->
                            <p>Course Code: </p><br>
                            <p>Course Name: </p><br>
                            <p>Course Credits: </p><br>
                            <p>Course Level: </p><br>
                            <p>Course Major: </p><br>
                            <p>Course Professor:</p><br>
                            <p>Course Category: </p><br>
                        </div>
                        <input type="button" id="edit" name="editCourse" class="btn" value="Edit Course">
                        <input type="button" id="disable" name="disableCourse" class="btn" value="Disable Course">
                        <input type="reset" id="cancel" name="cancelSearch" class="btn" value="Cancel">
                        </div>
                    </form>
                </details>
                <details class="dropdown-menu">
                    <summary>View Courses of The Major</summary>
                    <form id="viewMajorCourses" action="viewMajorCourses" method="post">
                        <div class="dropdown-content">
                            <div class="form-group">
                                <search>
                                <label for="majorName">Major Name</label>
                                <select id="majorName" name="majorName">
                                    <option value=""></option>
                                    <option value="opt1">Option 1</option>
                                    <option value="opt2">Option 2</option>
                                </select>
                                <label for="majorLevel">Major Level</label>
                                <select id="majorLevel" name="majorLevel">
                                    <option value="L1">L1</option>
                                    <option value="L2">L2</option>
                                    <option value="L3">L3</option>
                                    <option value="M1">M1</option>
                                </select> 
                                <label for="majorSemester">Semester</label>
                                <select id="majorSemester" name="majorSemester">
                                    <option value="fall">Fall</option>
                                    <option value="Spring">Spring</option>
                                </select>
                                <label for="majorLang">Major Language</label>
                                <select id="majorLang" name="majorLang">
                                    <option value="E">English</option>
                                    <option value="F">French</option>
                                </select>
                                </search>
                            </div>
                            <input type="button" id="findButton" class="btn" name="findButton" value="Find">
                            <input type="reset" id="deleteButton" class="btn" name="deleteButton" value="Delete"><br>
                            <br><div class="table-container" style="display: none;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Course Category</th>
                                            <th>Credits</th>
                                            <th>Professor name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><a href="#" class="code-link">I2204</a></td>
                                            <td>Imperative Programming</td>
                                            <td>Mandatory</td>
                                            <td>3</td>
                                            <td>Ibitssam Constantin</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </details>
        </section>

        <section id="content-correctors" class="tab-content">
            <h1>Correctors</h1>

<details class="dropdown-menu">
                <summary>Insert Correctors</summary>
                    <div class="dropdown-content">
                         <div class="form-group">
                            <search>
                                <label for="corrSession">Session</label>
                                <select name="corrSession" id="corrSession">
                                    <option value="sem1">Semester 1</option>
                                        <option value="sem2">Semester 2</option>
                                        <option value="sess2">Session 2</option>
                                </select>
                                <label for="corrMajor">Major</label>
                                <select name="corrMajor" id="corrMajor">
                                    <option value=""></option>
                                    <option value="opt1">Option 1</option>
                                    <option value="opt2">Option 2</option>
                                </select>
                                <label for="corrLevel">Level</label>
                                <select name="corrLevel" id="corrLevel">
                                    <option value="L1">L1</option>
                                    <option value="L2">L2</option>
                                    <option value="L3">L3</option>
                                    <option value="M1">M1</option>
                                </select>
                                <label for="corrLang">Language</label>
                                <select name="corrLang" id="corrLang">
                                    <option value="corrEng">E</option>
                                    <option value="corrFr">F</option>
                                </select>
                            </search><br>
                    </div>
                    
                    <input type="button" id="findButton" name="findBtn" class="btn" value="Find">
                    <input type="reset" id="cancelButton" name="cancelBtn" class="btn" value="Cancel"><br><br>

                     <div class="table-container" style="display: none;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>First Corrector</th>
                                            <th>Second Corrector</th>
                                            <th>Third Corrector</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>I3350</td>
                                            <td>Mobile</td>
                                            <td>Ziad El-balaa</td>
                                            <td>
                                                <select name="secondCorr" class="correctorSelect" disabled>
                                                    <option value=""></option>
                                                    <option value="o1">Ralph El khoury</option>
                                                    <option value="o2">Bernadette Wakim</option>
                                                    <option value="o3">Joseph Constantin</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="thirdCorr" class="correctorSelect" disabled>
                                                    <option value=""></option>
                                                    <option value="o1">Ralph El khoury</option>
                                                    <option value="o2">Bernadette Wakim</option>
                                                    <option value="o3">Joseph Constantin</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>I3306</td>
                                            <td>Database</td>
                                            <td>Bernadette Wakim</td>
                                            <td>
                                                <select name="secondCorr" class="correctorSelect" disabled>
                                                    <option value=""></option>
                                                    <option value="o1">Ralph El khoury</option>
                                                    <option value="o2">Ziad El balaa</option>
                                                    <option value="o3">Joseph Constantin</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="thirdCorr" class="correctorSelect" disabled>
                                                    <option value=""></option>
                                                    <option value="o1">Ralph El khoury</option>
                                                    <option value="o2">Bernadette Wakim</option>
                                                    <option value="o3">Joseph Constantin</option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div><br>
                            <input type="button" id="editCorr" class="btn" name="editCorr" value="Edit Correctors" style="display: none;">
                            <input type="reset" id="deleteCorr" class="btn" name="deleteCorr" value="Delete Correctors" style="display: none;">
                            <input type="reset" id="applyCorr" class="btn" name="applyCorr" value="Apply Changes" style="display: none;">
                </div>
                </details>

                <details class="dropdown-menu">
                        <summary>View Correctors by the course id</summary>
                        <form id="searchCorrector" action="searchCorrector.php" method="post">
                            <div class="dropdown-content">
                            <div class="form-group">
                                <label for="courseId">Enter the course ID</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="text" id="courseId" name="courseId" placeholder="Enter the course ID" style="width: 200px;">
                                    <label for="correctorLang">Language</label>
                                    <select id="correctorLang">
                                        <option value="correctorEng">E</option>
                                        <option value="correctorFr">F</option>
                                    </select>
                                    <input type="button"  id="searchID" name="searchID" value="Search">
                                </div><br>
                                <!--The corrector information need to fetched from the backend-->
                                <p>Corrector1 ID: </p><br>
                                <p>Corrector1 Name: </p><br>
                                <p>Corrector2 ID: </p><br>
                                <p>Corrector2 Name: </p><br>
                                </div>
                        <input type="button" id="edit" name="editCorrectors" class="btn" value="Edit Correctors">
                        </div>
                        </form>
                </details>
                <details class="dropdown-menu">
                    <summary>Excel Format</summary>
                    <form id="exportExcel" action="exportExcel.php" method="post">
                        <div class="dropdown-content">
                            <div class="form-group">
                                <search>
                                    <label for="session">Session</label>
                                    <select id="session" name="sessionId">
                                        <option value="sem1">Semester 1</option>
                                        <option value="sem2">Semester 2</option>
                                        <option value="sess2">Session 2</option>
                                    </select>
                                    <label for="excelMajor">Major</label>
                                    <select id="excelMajor" name="excelMajor">
                                        <option value="all">All</option>
                                        <option value="opt1">Option1</option>
                                        <option value="opt2">Option2</option>
                                    </select>
                                    <label for="excelLevel">Level</label>
                                    <select name="excelLevel" id="excelLevel">
                                        <option value="all">All</option>
                                        <option value="L1">L1</option>
                                        <option value="L2">L2</option>
                                        <option value="L3">L3</option>
                                        <option value="M1">M1</option>
                                    </select>
                                </search>
                            </div>
                            <label for="format">Choose the format</label>
                            <input type="button" id="format" class="btn" name="tawzi3" value="توزيع اللجان الفاحصة">
                            <input type="button" id="format" class="btn" name="ta3in" value="تعيين اللجان الفاحصة">
                            <input type="button" id="format" class="btn" name="edbarat" value="مجموع اضبارات التصحيح">
                        </div>
                    </form>
                </details>
        </section>

        <section id="content-edit-admins" class="tab-content">
            <h1>Edit Admins</h1>
                <details class="dropdown-menu">
                    <summary>Search Professor</summary>
                        <form id="searchProfessor" action="searchProfessor.php" method="post">
                            <div class="dropdown-content">
                                <div class="form-group">
                                    <label for="professorId">Enter the ID</label>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                        <input type="text" id="professorId" name="professorId" placeholder="Enter the professor ID" style="width: 200px;">
                                        <input type="button"  id="searchID" name="searchID" value="Search">
                                        </div><br>
                                        <!--The professor information need to fetched from the backend-->
                                        <p>Professor ID: </p><br>
                                        <p>Professor Name: </p><br>
                                </div>
                                <input type="button" id="makeAdmin" name="makeAdmin" class="btn" value="Make Admin">
                                <input type="button" id="removeAdmin" name="removeAdmin" class="btn" value="Remove Admin">
                            </div>
                        </form>
                </details>
        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const findButtons = document.querySelectorAll('input[type="button"][value="Find"]');
            findButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const dropdown = button.closest('.dropdown-content');
                    const table = dropdown?.querySelector('.table-container');
                    if (table) {
                        table.style.display = 'block';
                    }

                    // If this Find button belongs to the Correctors section, show the edit/delete buttons
                    const correctorsSection = button.closest('#content-correctors');
                    if (correctorsSection) {
                        const editBtn = correctorsSection.querySelector('#editCorr');
                        const deleteBtn = correctorsSection.querySelector('#deleteCorr');
                        if (editBtn) editBtn.style.display = 'inline-block';
                        if (deleteBtn) deleteBtn.style.display = 'inline-block';
                    }
                });
            });

            // Handle Edit Correctors button click
            const editBtn = document.querySelector('#editCorr');
            if (editBtn) {
                editBtn.addEventListener('click', () => {
                    const correctorsSection = editBtn.closest('#content-correctors');
                    if (correctorsSection) {
                        const selectElements = correctorsSection.querySelectorAll('.correctorSelect');
                        selectElements.forEach(select => {
                            select.disabled = false;
                        });
                        // Show Apply Changes button
                        const applyBtn = correctorsSection.querySelector('#applyCorr');
                        if (applyBtn) applyBtn.style.display = 'inline-block';
                    }
                });
            }

            // Handle Apply Changes button click
            const applyBtn = document.querySelector('#applyCorr');
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    const correctorsSection = applyBtn.closest('#content-correctors');
                    if (correctorsSection) {
                        const selectElements = correctorsSection.querySelectorAll('.correctorSelect');
                        selectElements.forEach(select => {
                            select.disabled = true;
                        });
                        // Hide Apply Changes button
                        applyBtn.style.display = 'none';
                    }
                });
            }

            // Handle Cancel button click
            const cancelBtn = document.querySelector('#cancelButton');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    const correctorsSection = cancelBtn.closest('#content-correctors');
                    if (correctorsSection) {
                        const table = correctorsSection.querySelector('.table-container');
                        const editBtn = correctorsSection.querySelector('#editCorr');
                        const deleteBtn = correctorsSection.querySelector('#deleteCorr');
                        const applyBtn = correctorsSection.querySelector('#applyCorr');
                        if (table) table.style.display = 'none';
                        if (editBtn) editBtn.style.display = 'none';
                        if (deleteBtn) deleteBtn.style.display = 'none';
                        if (applyBtn) applyBtn.style.display = 'none';
                    }
                });
            }

            // Handle Delete Correctors button click
            const deleteBtn = document.querySelector('#deleteCorr');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => {
                    const correctorsSection = deleteBtn.closest('#content-correctors');
                    if (correctorsSection) {
                        const secondCorrSelects = correctorsSection.querySelectorAll('select[name="secondCorr"]');
                        const thirdCorrSelects = correctorsSection.querySelectorAll('select[name="thirdCorr"]');
                        secondCorrSelects.forEach(select => {
                            select.value = '';
                        });
                        thirdCorrSelects.forEach(select => {
                            select.value = '';
                        });
                    }
                });
            }
        });
    </script>

</body>
</html>