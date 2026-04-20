<?php
    session_start();
    include("database.php");
    

    if(!isset($_SESSION['email'])){
        header("location: login.php");
        exit();
    }
    
    $email = $_SESSION["email"];

    //Check if stills admin
    $sql_admin = "SELECT isAdmin,prof_file_nb FROM professor WHERE prof_email = ?";
    $stmt_a = mysqli_prepare($conn,$sql_admin);
    mysqli_stmt_bind_param($stmt_a,"s",$email);
    mysqli_stmt_execute($stmt_a);

    $res_a = mysqli_stmt_get_result($stmt_a);
    $admin = mysqli_fetch_assoc($res_a);

    //If not admin
    if(!$admin || $admin["isAdmin"]!=1){
        header("location: login.php");
        exit;
    }

    //handle if the admin removed himself
    if($_SERVER['REQUEST_METHOD'] == 'POST'){

        $currentAdmin = $_SESSION["current_admin"];
        $adminFile = $admin["prof_file_nb"];

        if($currentAdmin == $adminFile){
            header("location: login.php");
            exit;
        }
        
    }

    $sql_all_profs = "SELECT p.prof_file_nb, p.prof_first_name, p.prof_last_name,p.prof_birth_date, p.prof_email, p.prof_phone, p.isAdmin, p.prof_category FROM professor p JOIN professor a ON a.dep_id = p.dep_id where a.prof_email = ? ";
    $stmt_all = mysqli_prepare($conn, $sql_all_profs);
    mysqli_stmt_bind_param($stmt_all,'s',$email);
    mysqli_stmt_execute($stmt_all);
    $res_all = mysqli_stmt_get_result($stmt_all);
    $all_professors = [];
    while($row = mysqli_fetch_assoc($res_all)){
        $all_professors[] = $row;
    }
    mysqli_stmt_close($stmt_all);


    $professors_full = $all_professors;
    $professors = [];
    foreach($professors_full as $prof){
        $professors[$prof["prof_file_nb"]] = $prof["prof_first_name"] . " " . $prof["prof_last_name"];
    }

    //this query to fetch the course of the professor for the view professor
    $sql_teaching = "SELECT t.course_code,t.course_lang,t.prof_file_nb
                     FROM teaching t
                     JOIN professor p ON p.prof_file_nb = t.prof_file_nb
                     JOIN professor a ON a.dep_id = p.dep_id
                     WHERE a.prof_email = ? and t.isActive = 1";  
    $stmt_t = mysqli_prepare($conn,$sql_teaching);
    mysqli_stmt_bind_param($stmt_t,"s",$email);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    $teaching_courses = [];
    while($row = mysqli_fetch_assoc($res_t)){
        $course = $row["course_code"] . " (".$row["course_lang"].")";
        if (!isset($teaching_courses[$row["prof_file_nb"]])) {
            $teaching_courses[$row["prof_file_nb"]] = [];
        }
        if (!in_array($course, $teaching_courses[$row["prof_file_nb"]])) {
            $teaching_courses[$row["prof_file_nb"]][] = $course;
        }
    }
    
    foreach($professors_full as &$prof){
        $prof["courses"] = ""; // Initialize default value
        if(isset($teaching_courses[$prof["prof_file_nb"]])){
            $prof["courses"] = implode(", ", $teaching_courses[$prof["prof_file_nb"]]);
        }
    }
    unset($prof); // Clean up reference

    $sql_majors = "SELECT m.major_id, m.major_name FROM major m where 1";
    $stmt_m = mysqli_prepare($conn, $sql_majors);
    mysqli_stmt_execute($stmt_m);
    $res_m = mysqli_stmt_get_result($stmt_m);

    $majors = [];
    while($row = mysqli_fetch_assoc($res_m)){
        $majors[$row["major_id"]] = $row["major_name"];
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

    <?php 
        $activeTab = $_GET["tab"] ?? "professors";
        $isProfessors = $activeTab === "professors";
        $isCourses = $activeTab === "courses";
        $isCorrectors = $activeTab === "correctors";
        $isEditAdmins = $activeTab === "edit-admins";
    ?>
    <input type="radio" name="nav" id="tab-professors"<?php echo $isProfessors ? " checked" : ""; ?>>
    <input type="radio" name="nav" id="tab-courses"<?php echo $isCourses ? " checked" : ""; ?>>
    <input type="radio" name="nav" id="tab-correctors"<?php echo $isCorrectors ? " checked" : ""; ?>>
    <input type="radio" name="nav" id="tab-edit-admins"<?php echo $isEditAdmins ? " checked" : ""; ?>>

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
            <div class="form-group" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <label for="professorSearchBy">Filter by</label>
                <select id="professorSearchBy" name="professorSearchBy" style="width: 220px;">
                    <option value="all">All</option>
                    <option value="id">ID</option>
                    <option value="name">Name</option>
                    <option value="course">Course</option>
                    <option value="category">Category</option>
                </select>
                <input type="text" id="professorFilterValue" placeholder="Enter filter value" style="width: 260px;" disabled>
                <select id="professorCategoryFilter" style="width: 260px; display: none;">
                    <option value="متعاقد بالساعة">متعاقد بالساعة</option>
                    <option value="متفرغ">متفرغ</option>
                    <option value="ملاك">ملاك</option>
                </select>
                <button type="button" id="clearProfessorFilter" class="btn">Clear</button>
            </div>
                    <div class="table-container" style="margin-top: 10px;">
                        <table id="professorsTable" border="1" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Birth Date</th>
                                    <th>Role</th>
                                    <th>Category</th>
                                    <th>Courses Taught</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach($professors_full as $prof){
                                    $role = $prof['isAdmin'] ? 'Admin' : 'Professor';
                                    $category = !empty($prof['prof_category']) ? htmlspecialchars($prof['prof_category']) : 'متعاقد بالساعة';
                                    $isCurrentUser = ($prof['prof_email'] === $email);
                                    $removeBtn = $isCurrentUser ? "<button type='button' class='btn' disabled style='background-color:#ccc;cursor:not-allowed;'>Current Admin</button>" : "<button type='button' class='remove-prof-btn btn' data-prof-id='" . htmlspecialchars($prof['prof_file_nb']) . "'>Remove</button>";

                                    echo "<tr data-prof-id='" . htmlspecialchars($prof['prof_file_nb']) . "'>
                                        <td class='prof-id'>" . htmlspecialchars($prof['prof_file_nb']) . "</td>
                                        <td class='prof-name'>" . htmlspecialchars($prof['prof_first_name'] . ' ' . $prof['prof_last_name']) . "</td>
                                        <td class='prof-email'>" . htmlspecialchars($prof['prof_email']) . "</td>
                                        <td class='prof-phone'>" . htmlspecialchars($prof['prof_phone']) . "</td>
                                        <td class='prof-birth'>" . htmlspecialchars($prof['prof_birth_date']) . "</td>
                                        <td class='prof-role'>" . htmlspecialchars($role) . "</td>
                                        <td class='prof-category'>" . $category . "</td>
                                        <td class='prof-courses'>" . htmlspecialchars($prof["courses"]) . "</td>
                                        <td>$removeBtn</td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
        </section>

        <section id="content-courses" class="tab-content">
            <h1>Courses</h1>
            <details class="dropdown-menu">
                <summary>Add Course</summary>
                    <div class="dropdown-content">

                        <form id="addCourse" action="addCourse.php" method="post">
                            <div class="form-group">
                                 <?php 
                                    if (isset($_SESSION["error"])) {
                                        
                                        echo '<p style="color: red; font-weight: bold;">' . $_SESSION["error"] . '</p>';
                                        
                                        unset($_SESSION["error"]);
                                    }
                                ?>
                                <label for="courseCode">Enter the code of the course</label>
                                <input type="text" id="courseCode" name="courseCode" maxlength="7" placeholder="I3341,M2250,P1101..." required><br>
                                <label for="courseName">Enter the name of the course</label>
                                <input type="text" id="courseName" name="courseName" placeholder="Enter the name" required><br>
                                <label for="courseCredit">Enter the credits of the course</label>
                                <input type="number" min="3" max="6" name="courseCredit" id="courseCredit" required><br>
                                <label for="courseHours">Enter the hours of the course</label>
                                <input type="number" min="36" max="72" name="courseHours" id="courseHours" required><br>
                                <label for="courseLang">Enter the language of the course</label>
                                <label class="checkbox-label" id="courseEng"><input type="checkbox" id="courseEng" name="courseEng"> English</label>
                                <label for="courseFr" class="checkbox-label"><input type="checkbox" id="courseFr" name="courseFr">French</label>
                                <label for="courseProf">Choose the professor of the course</label>
                                <select name="courseProf" id="courseProf">
                                   <?php
                                        //Preparing the dropdown list for choosing the name of the professor
                                        foreach($_SESSION["professors"] as $file=>$name){
                                            echo "<option value='$file'>$name</option>";
                                        }
                
                                   ?>
                                </select><br>
                                <label for="courseMajor">Choose the major</label>
                                <select name="courseMajor" id="courseMajor">
                                    <?php
                                        foreach($_SESSION["majors"] as $id=>$name){
                                            echo "<option value='$id'>$name</option>";
                                        }
                                    ?>
                                </select><br>
                                <label for="courseLevel">Choose the level</label>
                                <select id="courseLevel" name="courseLevel">
                                    <option value="L1">L1</option>
                                    <option value="L2">L2</option>
                                    <option value="L3">L3</option>
                                    <option value="M1">M1</option>
                                </select><br>
                                <label for="courseSemester">Choose the semester</label>
                                <select name="courseSemester" id="courseSemester">
                                    <option value="1">Semester 1</option>
                                    <option value="2">Semester 2</option>
                                </select>
                                <br>
                                <label for="courseYear">Enter the university year</label>
                                <input type="text" id="courseYear" name="courseYear" maxlength="15" required><br>
                                <label for="courseCategory">Choose the category</label>
                                <select name="courseCategory" id="courseCategory">
                                    <option value="mandatory">Mandatory</option>
                                    <option value="optional">Optional</option>
                                    <option value="common">Common</option>
                                </select><br>

                            </div>
                            
                            <input type="submit" name="submitCourse" class="btn" value="Submit">
                            <input type="reset" name="cancelCourse" class="btn" value="Cancel">
                        </form>
                    </div>
                </details>
                <details class="dropdown-menu">
                <summary>Search Course</summary>
                <form id="searchCourseForm" action="searchCourse.php" method="post" onsubmit="return false;">
                    <div class="dropdown-content">
                        <div class="form-group">
                            <div>
                                <label for="searchCode">Course Code</label>
                                <input type="text" id="searchCode" name="searchCode" placeholder="I3350,P1100...." maxlength="7">
                                <label for="searchCourseLang">Course Language</label>
                                <select name="searchCourseLang" id="searchCourseLang">
                                    <option value="E">E</option>
                                    <option value="F">F</option>
                                </select>
                                <label for="searchCourseMajor">Course Major</label>
                                <select name="searchCourseMajor" id="searchCourseMajor">
                                    <?php foreach($_SESSION["majors"] as $id=>$name){ echo "<option value='$id'>$name</option>"; } ?>
                                </select>
                                <input type="button" id="searchCourseBtn" name="searchBtn" class="btn" value="Search">
                            </div><br>

                            <input type="hidden" id="hiddenCourseCode" name="course_code" value="">
                            <input type="hidden" id="hiddenCourseLang" name="course_lang" value="">
                            <input type="hidden" id="hiddenCourseMajor" name="old_major_id" value="">
                            <input type="hidden" id="hiddenCourseIsActive" value="">

                            <label for="resCourseCode">Course Code: </label>
                            <input type="text" id="resCourseCode" name="resCourseCode" value="" disabled>
                            <br>
                            <label for="resCourseLang">Course Language: </label>
                            <select id="resCourseLang" name="resCourseLang" disabled>
                                <option value="E">E</option>
                                <option value="F">F</option>
                            </select>
                            <br>
                            <label for="resCourseName">Course Name: </label>
                            <input type="text" id="resCourseName" name="resCourseName" value="" disabled>
                            <br>
                            <label for="resCourseCredit">Course Credits: </label>
                            <input type="number" id="resCourseCredit" min="3" max="6" name="resCourseCredit" value="" disabled>
                            <br>
                            <label for="resCourseHours">Course Hours: </label>
                            <input type="number" id="resCourseHours" min="36" max="72" name="resCourseHours" value="" disabled>
                            <br>
                            <label for="resCourseYear">University Year: </label>
                            <input type="text" id="resCourseYear" name="resCourseYear" maxlength="15" value="" disabled>
                            <br>
                            <label for="resCourseLevel">Course Level: </label>
                            <select id="resCourseLevel" name="resCourseLevel" disabled>
                                <option value="L1">L1</option>
                                <option value="L2">L2</option>
                                <option value="L3">L3</option>
                                <option value="M1">M1</option>
                            </select>
                            <br>
                            <label for="resCourseSemester">Course Semester:</label>
                            <select name="resCourseSemester" id="resCourseSemester" disabled>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                            <br>
                            <label for="resCourseMajor">Course Major: </label>
                            <select name="resCourseMajor" id="resCourseMajor" disabled>
                                <?php foreach($_SESSION["majors"] as $id=>$name){ echo "<option value='$id'>$name</option>"; } ?>
                            </select>
                            <br>
                            <label for="resCourseProf">Course Professor: </label>
                            <select name="resCourseProf" id="resCourseProf" disabled>
                                <?php foreach($_SESSION["professors"] as $file=>$name){ echo "<option value='$file'>$name</option>"; } ?>
                            </select>
                            <br>
                            <label for="resCourseCategory">Course Category</label>
                            <select name="resCourseCategory" id="resCourseCategory" disabled>
                                <option value="mandatory">Mandatory</option>
                                <option value="optional">Optional</option>
                                <option value="common">Common</option>
                            </select>
                        </div>
                        <input type="button" id="resEditCourse" class="btn" value="Edit Course">
                        <input type="button" id="resConfirmCourse" class="btn" value="Confirm changes" style="display: none;">
                        <input type="button" id="resDisableCourse" class="btn" value="Disable Course">
                        <input type="button" id="resCancelSearch" class="btn" value="Cancel">
                    </div>
                </form>
            </details>
                <?php
                    $viewMajorCoursesLoaded = array_key_exists("view_major_courses", $_SESSION);
                    $viewMajorCourses = $_SESSION["view_major_courses"] ?? [];
                    $vmf = $_SESSION["view_major_filter"] ?? [];

                    // to keep the filter after the rendering
                    $vmfSel = function ($key, $value) use ($vmf) {
                        return isset($vmf[$key]) && (string) $vmf[$key] === (string) $value ? " selected" : "";
                    };
                ?>
                <details class="dropdown-menu"<?php echo $viewMajorCoursesLoaded ? " open" : ""; ?>>
                    <summary>View Courses of The Major</summary>
                    <form id="viewMajorCourses" action="viewCourses.php" method="post">
                        <div class="dropdown-content">
                            <div class="form-group">
                                <search>
                                <label for="majorName">Major Name</label>
                                <select id="majorName" name="major">
                                    <?php
                                        foreach ($_SESSION["majors"] as $id => $name) {
                                            $selectedMajor = $vmfSel("major" ,$id );
                                            echo "<option value='" . $id . "'{$selectedMajor}>" . $name . "</option>";
                                        }
                                    ?>
                                </select>
                                <label for="majorLevel">Major Level</label>
                                <select id="majorLevel" name="majorLevel">
                                    <option value="L1"<?php echo $vmfSel("majorLevel", "L1"); ?>>L1</option>
                                    <option value="L2"<?php echo $vmfSel("majorLevel", "L2"); ?>>L2</option>
                                    <option value="L3"<?php echo $vmfSel("majorLevel", "L3"); ?>>L3</option>
                                    <option value="M1"<?php echo $vmfSel("majorLevel", "M1"); ?>>M1</option>
                                </select> 
                                <label for="majorSemester">Semester</label>
                                <select id="majorSemester" name="majorSemester">
                                    <option value="1"<?php echo $vmfSel("majorSemester", "1"); ?>>Semester 1</option>
                                    <option value="2"<?php echo $vmfSel("majorSemester", "2"); ?>>Semester 2</option>
                                </select>
                                <label for="majorLang">Major Language</label>
                                <select id="majorLang" name="majorLang">
                                    <option value="E"<?php echo $vmfSel("majorLang", "E"); ?>>English</option>
                                    <option value="F"<?php echo $vmfSel("majorLang", "F"); ?>>French</option>
                                </select>
                                </search>
                            </div>
                            <?php
                                if (!empty($_SESSION["view_major_error"])) {
                                    echo '<p style="color:#b91c1c;font-weight:600;">' . htmlspecialchars($_SESSION["view_major_error"], ENT_QUOTES, "UTF-8") . '</p>';
                                    unset($_SESSION["view_major_error"]);
                                }
                            ?>
                            <input type="submit" id="findViewMajorBtn" class="btn" name="findView" value="Find">
                            <input type="submit" id="deleteViewMajorBtn" class="btn" name="deleteView" value="Delete"><br>
                            <br><div class="table-container" style="<?php echo $viewMajorCoursesLoaded ? "" : "display: none;"; ?>">
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
                                        <?php
                                        if ($viewMajorCoursesLoaded && count($viewMajorCourses) === 0) {
                                            echo '<tr><td colspan="5" style="text-align:center;color:#64748b;">No courses match these filters (check major, level, semester, and language).</td></tr>';
                                        }
                                        foreach ($viewMajorCourses as $r) {
                                            $profName = trim(($r["prof_first_name"] ?? "") . " " . ($r["prof_last_name"] ?? ""));
                                            echo "<tr>";
                                            echo "<td>" . $r["course_code"] . "</td>";
                                            echo "<td>" . $r["course_name"]. "</td>";
                                            echo "<td>" . $r["course_category"]. "</td>";
                                            echo "<td>" . $r["course_credit_nb"] . "</td>";
                                            echo "<td>" . $profName . "</td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </details>
        </section>

        <section id="content-correctors" class="tab-content">
            <h1>Correctors</h1>
        
        <?php
                    $correctorsLoaded = array_key_exists("insert_correctors_data", $_SESSION);
                    $correctors = $_SESSION["insert_correctors_data"] ?? [];
                    $icf = $_SESSION["insert_correctors_filter"] ?? [];
                    $icfSel = function ($key, $value) use ($icf) {
                        return isset($icf[$key]) && (string) $icf[$key] === (string) $value ? " selected" : "";
                    };
        ?>
        <details id="insertCorrectorsDetails" class="dropdown-menu" <?php echo $correctorsLoaded ? 'open' : ''; ?>>
                <summary>Insert Correctors</summary>
                <div class="dropdown-content">
                <form id="insertCorrectors" action="insertCorrectors.php" method="post">
                         <div class="form-group">
                            <search>
                                <label for="corrSession">Session</label>
                                <select name="corrSession" id="corrSession">
                                    <option value="sem1"<?php echo $icfSel("corrSession", "sem1"); ?>>Semester 1</option>
                                    <option value="sem2"<?php echo $icfSel("corrSession", "sem2"); ?>>Semester 2</option>
                                    <option value="sess2"<?php echo $icfSel("corrSession", "sess2"); ?>>Session 2</option>
                                </select>
                                <label for="corrMajor">Major</label>
                                <select name="corrMajor" id="corrMajor">
                                    <?php
                                        foreach ($_SESSION["majors"] as $id => $name) {
                                            $selectedMajor = $icfSel("corrMajor", $id);
                                            echo "<option value='" . $id . "'{$selectedMajor}>" . $name . "</option>";
                                        }
                                    ?>
                                </select>
                                <label for="corrLevel">Level</label>
                                <select name="corrLevel" id="corrLevel">
                                    <option value="L1"<?php echo $icfSel("corrLevel", "L1"); ?>>L1</option>
                                    <option value="L2"<?php echo $icfSel("corrLevel", "L2"); ?>>L2</option>
                                    <option value="L3"<?php echo $icfSel("corrLevel", "L3"); ?>>L3</option>
                                    <option value="M1"<?php echo $icfSel("corrLevel", "M1"); ?>>M1</option>
                                </select>
                                <label for="corrLang">Language</label>
                                <select name="corrLang" id="corrLang">
                                    <option value="all"<?php echo $icfSel("corrLang", "all"); ?>>All</option>
                                    <option value="E"<?php echo $icfSel("corrLang", "E"); ?>>English</option>
                                    <option value="F"<?php echo $icfSel("corrLang", "F"); ?>>French</option>
                                </select>
                            </search><br>
                    </div>
                    
                    <input type="submit" id="findButton" name="findBtn" class="btn" value="Find">
                    <input type="button" id="cancelButton" name="cancelBtn" class="btn" value="Cancel"><br><br>
                </form>

                    <?php if (isset($_SESSION["insert_correctors_error"])): ?>
                        <p style="color: red; margin-top: 10px;"><?php echo htmlspecialchars($_SESSION["insert_correctors_error"]); ?></p>
                        <?php unset($_SESSION["insert_correctors_error"]); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION["insert_correctors_success"])): ?>
                        <p style="color: green; margin-top: 10px;"><?php echo htmlspecialchars($_SESSION["insert_correctors_success"]); ?></p>
                        <?php unset($_SESSION["insert_correctors_success"]); ?>
                    <?php endif; ?>

                    <form id="correctorsForm" action="insertCorrectors.php" method="post">
                     <div class="table-container" style="display: <?php echo $correctorsLoaded ? 'block' : 'none'; ?>;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Language</th>
                                            <th>First Corrector</th>
                                            <th>Second Corrector</th>
                                            <th>Third Corrector</th>
                                        </tr>
                                    </thead>
                                    <tbody id="correctorsTableBody">
                                        <?php
                                        if ($correctorsLoaded && count($correctors) === 0) {
                                            echo '<tr><td colspan="5" style="text-align:center;color:#64748b;">No courses match these filters (check major, level, session, and language).</td></tr>';
                                        } else {
                                            foreach ($correctors as $r) {
                                                $enabled = isset($_POST["editCorr"]) ? "" : "disabled";
                                                $profName = trim(($r["prof_first_name"] ?? "") . " " . ($r["prof_last_name"] ?? ""));
                                                $courseCode = $r["course_code"];
                                                $courseLang = $r["course_lang"];
                                                $firstCorrectorId = isset($r["first_corrector_id"]) ? (string)$r["first_corrector_id"] : null;
                                                $secondSelected = isset($r["second_corrector"]) ? (string)$r["second_corrector"] : "";
                                                $thirdSelected = isset($r["third_corrector"]) ? (string)$r["third_corrector"] : "";
                                                echo "<tr>";
                                                echo "<td>" . $courseCode . "</td>";
                                                echo "<td>" . $r["course_name"] . "</td>"; 
                                                echo "<td>" . $r["course_lang"] . "</td>"; 
                                                echo "<td>" . $profName . "</td>";
                                                echo "<td><select name='second_corrector[" . $courseCode . "][" . $r["course_lang"] . "]' $enabled  class='corrector-select'>";
                                                if ($secondSelected !== "" && isset($_SESSION["professors"][$secondSelected])) {
                                                    $selectedName = $_SESSION["professors"][$secondSelected];
                                                    echo "<option value='" . $secondSelected . "' selected>" . $selectedName . "</option>";
                                                    echo "<option value=''>null</option>";
                                                } else {
                                                    echo "<option value='' selected>null</option>";
                                                }
                                                foreach ($_SESSION["professors"] as $id => $name) {
                                                    $idStr = (string)$id;
                                                    if ($idStr === $secondSelected || $idStr === $firstCorrectorId || $idStr === $thirdSelected) {
                                                        continue;
                                                    }
                                                    echo "<option value='" . $idStr . "'>" . $name . "</option>";
                                                }
                                                echo "</select></td>";
                                                echo "<td><select name='third_corrector[" . $courseCode . "][" . $r["course_lang"] . "]' $enabled  class='corrector-select'>";
                                                if ($thirdSelected !== "" && isset($_SESSION["professors"][$thirdSelected])) {
                                                    $selectedName = $_SESSION["professors"][$thirdSelected];
                                                    echo "<option value='" . $thirdSelected . "' selected>" . $selectedName . "</option>";
                                                    echo "<option value=''>null</option>";
                                                } else {
                                                    echo "<option value='' selected>null</option>";
                                                }
                                                foreach ($_SESSION["professors"] as $id => $name) {
                                                    $idStr = (string)$id;
                                                    if ($idStr === $thirdSelected || $idStr === $firstCorrectorId || $idStr === $secondSelected) {
                                                        continue;
                                                    }
                                                    echo "<option value='" . $idStr . "'>" . $name . "</option>";
                                                }
                                                echo "</select></td>";
                                                echo "</tr>";
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div><br>
                            <input type="submit" id="applyCorr" class="btn" name="applyCorr" value="Apply Changes" style="display: none;">
                            <input type="button" id="editCorr" class="btn" value="Edit Correctors" style="display: <?php echo ($correctorsLoaded && count($correctors) > 0) ? 'inline-block' : 'none'; ?>;">
                            <input type="button" id="deleteCorr" class="btn" value="Delete Correctors" style="display: <?php echo ($correctorsLoaded && count($correctors) > 0) ? 'inline-block' : 'none'; ?>;">
                             </form>
                </div>
        </details>
                            
                <?php 
                    $numbersLoaded = array_key_exists("insert_numbers_data", $_SESSION);
                    $numbersList = $_SESSION["insert_numbers_data"] ?? [];
                    $inf = $_SESSION["insert_numbers_filter"] ?? [];
                    $infSel = function ($key, $value) use ($inf) {
                        return isset($inf[$key]) && (string) $inf[$key] === (string) $value ? " selected" : "";
                    };
                ?>
                <details id="insertNumbersDetails" class="dropdown-menu" <?php echo $numbersLoaded ? 'open' : ''; ?>>
                    <summary>Insert copies number of each corrector</summary>
                    <div class="dropdown-content">
                        <form id="insertNumbers" action="insertNumbers.php" method="post">
                            <div class="form-group">
                                <search>
                                    <label for="numberSession">Session</label>
                                    <select name="numberSession" id="numberSession">
                                        <option value="sem1"<?php echo $infSel("numberSession", "sem1"); ?>>Semester 1</option>
                                        <option value="sem2"<?php echo $infSel("numberSession", "sem2"); ?>>Semester 2</option>
                                        <option value="sess2"<?php echo $infSel("numberSession", "sess2"); ?>>Session 2</option>
                                    </select>
                                    <label for="numberExam">Exam</label>
                                    <select name="numberExam" id="numberExam">
                                        <option value="P" <?php echo $infSel("numberExam", "P") ?>>Partial</option>
                                        <option value="F" <?php echo $infSel("numberExam", "F") ?>>Final</option>
                                    </select>
                                    <label for="numberMajor">Major</label>
                                    <select name="numberMajor" id="numberMajor">
                                        <?php
                                            foreach ($_SESSION["majors"] as $id => $name) {
                                                $selectedMajor = $infSel("numberMajor", $id);
                                                echo "<option value='" . $id . "'{$selectedMajor}>" . $name . "</option>";
                                            }
                                        ?>
                                    </select>
                                    <label for="numberLevel">Level</label>
                                    <select name="numberLevel" id="numberLevel">
                                        <option value="L1"<?php echo $infSel("numberLevel", "L1"); ?>>L1</option>
                                        <option value="L2"<?php echo $infSel("numberLevel", "L2"); ?>>L2</option>
                                        <option value="L3"<?php echo $infSel("numberLevel", "L3"); ?>>L3</option>
                                        <option value="M1"<?php echo $infSel("numberLevel", "M1"); ?>>M1</option>
                                    </select>
                                    <label for="numberLang">Language</label>
                                    <select name="numberLang" id="numberLang">
                                        <option value="all"<?php echo $infSel("numberLang", "all"); ?>>All</option>
                                        <option value="E"<?php echo $infSel("numberLang", "E"); ?>>English</option>
                                        <option value="F"<?php echo $infSel("numberLang", "F"); ?>>French</option>
                                    </select>
                                </search><br>
                            </div>
                            <input type="submit" id="findNumber" name="findNumber" class="btn" value="Find">
                            <input type="button" id="cancelNumber" name="cancelNumber" class="btn" value="Cancel"><br><br>
                        </form>

                        <?php if (isset($_SESSION["insert_numbers_error"])): ?>
                            <p style="color: red; margin-top: 10px;"><?php echo htmlspecialchars($_SESSION["insert_numbers_error"]); ?></p>
                            <?php unset($_SESSION["insert_numbers_error"]); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION["insert_numbers_success"])): ?>
                            <p style="color: green; margin-top: 10px;"><?php echo htmlspecialchars($_SESSION["insert_numbers_success"]); ?></p>
                            <?php unset($_SESSION["insert_numbers_success"]); ?>
                        <?php endif; ?>

                        <form id="numbersForm" action="insertNumbers.php" method="post">
                            <div class="table-container" style="display: <?php echo $numbersLoaded ? 'block' : 'none'; ?>;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Language</th>
                                            <th>First Corrector</th>
                                            <th>1st Corr Copies</th>
                                            <th>Second Corrector</th>
                                            <th>2nd Corr Copies</th>
                                        </tr>
                                    </thead>
                                    <tbody id="numbersTableBody">
                                        <?php
                                        if ($numbersLoaded && count($numbersList) === 0) {
                                            echo '<tr><td colspan="7" style="text-align:center;color:#64748b;">No courses match these filters.</td></tr>';
                                        } else {
                                            $isFinal = (($inf['numberSession'] ?? '') === 'sess2' || ($inf['numberExam'] ?? '') === 'F');
                                            foreach ($numbersList as $r) {
                                                $courseCode = $r["course_code"];
                                                $courseLang = $r["course_lang"];
                                                $profName = trim(($r["prof_first_name"] ?? "") . " " . ($r["prof_last_name"] ?? ""));
                                                if ($profName === "") {
                                                    // Fallback if joined data missing (though fetchNumberRows should handle it)
                                                    $profName = $_SESSION["professors"][$r["prof_file_nb"]] ?? "Unknown";
                                                }
                                                $secondName = isset($r["second_corrector"]) ? ($_SESSION["professors"][$r["second_corrector"]] ?? "Unknown") : "None";
                                                
                                                $val1 = $isFinal ? $r['final_first_corrector'] : $r['partial_first_corrector'];
                                                $val2 = $isFinal ? $r['final_second_corrector'] : $r['partial_second_corrector'];

                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($courseCode) . "</td>";
                                                echo "<td>" . htmlspecialchars($r["course_name"] ?? "") . "</td>"; 
                                                echo "<td>" . htmlspecialchars($courseLang) . "</td>"; 
                                                echo "<td>" . htmlspecialchars($profName) . "</td>";
                                                echo "<td><input type='number' name='first_numbers[" . $courseCode . "][" . $courseLang . "]' value='" . $val1 . "' class='number-input premium-number-input' disabled></td>";
                                                echo "<td>" . htmlspecialchars($secondName) . "</td>";
                                                echo "<td><input type='number' name='second_numbers[" . $courseCode . "][" . $courseLang . "]' value='" . $val2 . "' class='number-input premium-number-input' disabled></td>";
                                                echo "</tr>";
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div><br>
                            <input type="submit" id="applyNumbers" class="btn" name="applyNumbers" value="Apply Changes" style="display: none;">
                            <input type="button" id="editNumbers" class="btn" value="Edit Numbers" style="display: <?php echo ($numbersLoaded && count($numbersList) > 0) ? 'inline-block' : 'none'; ?>;">
                            <input type="button" id="deleteNumbers" class="btn" value="Delete Numbers" style="display: <?php echo ($numbersLoaded && count($numbersList) > 0) ? 'inline-block' : 'none'; ?>;">
                        </form>
                    </div>
                </details>
                <?php
                    $viewCorrectors = array_key_exists("view_correctors_data", $_SESSION);
                    $correctorsList = $_SESSION["view_correctors_data"] ?? [];
                    $corr = (is_array($correctorsList) && count($correctorsList) > 0) ? $correctorsList[0] : [];
                    $vcf = $_SESSION["view_correctors_filter"] ?? [];
                    $vcfSel = function ($key, $value) use ($vcf) {
                        return isset($vcf[$key]) && (string) $vcf[$key] === (string) $value ? " selected" : "";
                    };
                    $viewCorrectorsLoaded = array_key_exists("view_correctors_loaded", $_SESSION);
                ?>
                <details class="dropdown-menu"<?php echo $viewCorrectorsLoaded ? " open" : ""; ?>>
                        <summary>View Correctors by the course id</summary>
                        <form id="searchCorrector" action="viewCorrectors.php" method="post">
                            <div class="dropdown-content">
                            <div class="form-group">
                                <label for="courseId">Enter the course ID</label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="text" id="courseId" name="courseId" placeholder="Enter the course ID" style="width: 200px;" value="<?php echo htmlspecialchars($vcf['courseId'] ?? ''); ?>">
                                    <label for="correctorMajor">Major</label>
                                    <select name="correctorMajor" id="correctorMajor">
                                        <?php
                                            foreach ($_SESSION["majors"] as $id => $name) {
                                                $selectedMajor = $vcfSel("correctorMajor", $id);
                                                echo "<option value='" . $id . "'{$selectedMajor}>" . $name . "</option>";
                                            }
                                        ?>
                                    </select>
                                    <label for="correctorLang">Language</label>
                                    <select id="correctorLang" name="correctorLang">
                                        <option value="E" <?php echo $vcfSel("correctorLang","E") ?> >E</option>
                                        <option value="F" <?php echo $vcfSel("correctorLang","F") ?> >F</option>
                                    </select>
                                    <label for="correctorSession">Session</label>
                                    <select name="correctorSession" id="correctorSession">
                                        <option value="sem1"<?php echo $vcfSel("correctorSession", "sem1"); ?>>Semester 1</option>
                                        <option value="sem2"<?php echo $vcfSel("correctorSession", "sem2"); ?>>Semester 2</option>
                                        <option value="sess2"<?php echo $vcfSel("correctorSession", "sess2"); ?>>Session 2</option>
                                    </select>

                                    <input type="submit"  id="searchID" name="searchID" value="Search">
                                </div><br>
                                <?php 

                                    if ($viewCorrectors && count($correctorsList) === 0) {
                                            echo '<p style="text-align:center;color:#64748b;">No courses match these filters (check the course id, session, and language).</p>';
                                    } elseif ($viewCorrectors && count($correctorsList) > 0) {
                                        $courseCode = $corr["course_code"] ?? "";
                                        $courseName = $corr["course_name"] ?? "";
                                        $file1 = isset($corr["prof_file_nb"]) ? $corr["prof_file_nb"] : null;
                                        $prof1 = ($file1 !== null && $file1 !== "" && isset($professors[$file1])) ? $professors[$file1] : "No corrector assigned";
                                        $file2 = isset($corr["second_corrector_file_nb"]) ? $corr["second_corrector_file_nb"] : null;
                                        $prof2 = ($file2 !== null && $file2 !== "" && isset($professors[$file2])) ? $professors[$file2] : "No corrector assigned";
                                        $file3 = isset($corr["third_corrector_file_nb"]) ? $corr["third_corrector_file_nb"] : null;
                                        $prof3 = ($file3 !== null && $file3 !== "" && isset($professors[$file3])) ? $professors[$file3] : "No corrector assigned";

                                        echo "<p>Course Code: $courseCode </p><br>";
                                        echo "<p>Course Name: $courseName </p><br>";
                                        echo "<p>Corrector1 ID: " . (($file1 !== null && $file1 !== "") ? $file1 : "Not assigned") . " </p><br>";
                                        echo "<p>Corrector1 Name: $prof1 </p><br>";
                                        echo "<p>Corrector2 ID: " . (($file2 !== null && $file2 !== "") ? $file2 : "Not assigned") . " </p><br>";
                                        echo "<p>Corrector2 Name: $prof2 </p><br>";
                                        echo "<p>Corrector3 ID: " . (($file3 !== null && $file3 !== "") ? $file3 : "Not assigned") . " </p><br>";
                                        echo "<p>Corrector3 Name: $prof3 </p><br>";
                                    }

                                ?>
                                </div>
                        <input type="submit" id="cancelCorrectors" name="cancelCorrectors" class="btn" value="Cancel Search">
                        </div>
                        </form>
                </details>
                <details class="dropdown-menu">
                    <summary>Excel Format</summary>
                    <?php
                        $eef = $_SESSION["excel_export_filter"] ?? [];
                        $eefSel = function ($key, $value) use ($eef) {
                            return isset($eef[$key]) && (string) $eef[$key] === (string) $value ? " selected" : "";
                        };
                    ?>
                    <form id="exportExcel" action="exportExcel.php" method="post">
                        <div class="dropdown-content">
                            <?php
                                if (!empty($_SESSION["excel_export_error"])) {
                                    echo '<p style="color:#b91c1c;font-weight:600;">' . htmlspecialchars($_SESSION["excel_export_error"], ENT_QUOTES, "UTF-8") . '</p>';
                                    unset($_SESSION["excel_export_error"]);
                                }
                            ?>
                            <div class="form-group">
                                <search>
                                    <label for="session">Session</label>
                                    <select id="session" name="sessionId">
                                         <option value="sem1"<?php echo $eefSel("sessionId", "sem1"); ?>>Semester 1</option>
                                        <option value="sem2"<?php echo $eefSel("sessionId", "sem2"); ?>>Semester 2</option>
                                        <option value="sess2"<?php echo $eefSel("sessionId", "sess2"); ?>>Session 2</option>
                                    </select>
                                    <label for="excelMajor">Major</label>
                                    <select id="excelMajor" name="excelMajor">
                                        <option value="all" <?php echo $eefSel("excelMajor","all") ?>>All</option>
                                        <?php foreach ($_SESSION["majors"] as $id => $name) {
                                            $selectedMajor = $eefSel("excelMajor", $id);
                                            echo "<option value='" . $id . "'{$selectedMajor}>" . $name . "</option>";
                                        } ?>
                                    </select>
                                    <label for="excelLevel">Level</label>
                                    <select name="excelLevel" id="excelLevel">
                                        <option value="all" <?php echo $eefSel("excelLevel","all") ?>>All</option>
                                        <option value="L1" <?php echo $eefSel("excelLevel","L1") ?>>L1</option>
                                        <option value="L2" <?php echo $eefSel("excelLevel","L2") ?>>L2</option>
                                        <option value="L3" <?php echo $eefSel("excelLevel","L3") ?>>L3</option>
                                        <option value="M1" <?php echo $eefSel("excelLevel","M1") ?>>M1</option>
                                    </select>
                                </search>
                            </div>
                            <label for="format">Choose the format</label>
                            <input type="submit" id="format" class="btn" name="tawzi3" value="توزيع اللجان الفاحصة">
                            <input type="submit" id="format" class="btn" name="ta3in" value="تعيين اللجان الفاحصة">
                            <input type="submit" id="format" class="btn" name="edbarat" value="مجموع اضبارات التصحيح">
                            <input type="submit" id="format" class="btn" name="cancelExcel" value="Cancel">
                        </div>
                    </form>
                </details>
        </section>

        <section id="content-edit-admins" class="tab-content">
            <h1>Edit Admins</h1>
            <?php if (!empty($_SESSION['admin_status_message'])): ?>
                <?php $isError = strpos($_SESSION['admin_status_message'], 'Error:') === 0; ?>
                <div style="margin-bottom: 16px; padding: 12px 14px; background: <?php echo $isError ? '#fef2f2' : '#f0fdf4'; ?>; border: 1px solid <?php echo $isError ? '#fca5a5' : '#86efac'; ?>; color: <?php echo $isError ? '#dc2626' : '#166534'; ?>; border-radius: 8px; font-weight: 600;">
                    <?php echo htmlspecialchars($_SESSION['admin_status_message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['admin_status_message']); ?>
                </div>
            <?php endif; ?>
            <div class="form-group" style="width: 100%; max-width: 650px; margin-bottom: 20px;">
                <label for="professorDropdown">Select Professor</label>
                <select id="professorDropdown" name="professorDropdown" style="width: 100%;">
                    <option value="">-- Select a Professor --</option>
                     <?php
                                        //Preparing the dropdown list for choosing the name of the professor
                                         foreach($all_professors as $prof){
                                            echo "<option value='".htmlspecialchars($prof['prof_file_nb'])."'>".htmlspecialchars($prof['prof_first_name']." ".$prof['prof_last_name'])."</option>";
                                        }
    
                                   ?>
                </select>
            </div>
            <div id="selectedProfessorInfo" style="margin-bottom: 20px; padding: 12px; background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; color: #1f2937; font-size: 16px;">Selected Professor: None</div>
            <div class="form-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <form id="makeAdminForm" action="MakeAdmin.php" method="post" style="display: inline;">
                    <input type="hidden" id="prof_file_nb_make" name="prof_file_nb" value="">
                    <input type="submit" class="btn" value="Make Admin" id="makeAdminBtn" disabled>
                </form>
                <form id="removeAdminForm" action="RemoveAdmin.php" method="post" style="display: inline;">
                    <input type="hidden" id="prof_file_nb_remove" name="prof_file_nb" value="">
                    <input type="submit" class="btn" value="Remove Admin" id="removeAdminBtn" disabled>
                </form>
            </div>
        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Edit Admins Logic ---
            const professorDropdown = document.getElementById('professorDropdown');
            const profFileNbMake = document.getElementById('prof_file_nb_make');
            const profFileNbRemove = document.getElementById('prof_file_nb_remove');
            const makeAdminBtn = document.getElementById('makeAdminBtn');
            const removeAdminBtn = document.getElementById('removeAdminBtn');
            const selectedProfessorInfo = document.getElementById('selectedProfessorInfo');

            const updateSelectionInfo = (profFileNb, profLabel) => {
                if (!selectedProfessorInfo) return;
                selectedProfessorInfo.textContent = profFileNb ? `Selected Professor: ${profLabel}` : 'Selected Professor: None';
            };

            if (professorDropdown) {
                professorDropdown.addEventListener('change', function() {
                    const profFileNb = this.value;
                    const enabled = profFileNb !== '';
                    const profLabel = this.options[this.selectedIndex]?.text || '';

                    if (profFileNbMake) profFileNbMake.value = enabled ? profFileNb : '';
                    if (profFileNbRemove) profFileNbRemove.value = enabled ? profFileNb : '';
                    if (makeAdminBtn) makeAdminBtn.disabled = !enabled;
                    if (removeAdminBtn) removeAdminBtn.disabled = !enabled;
                    updateSelectionInfo(profFileNb, profLabel);
                });
            }

            // --- Professors Filter Logic ---
            const filterBy = document.getElementById('professorSearchBy');
            const filterInput = document.getElementById('professorFilterValue');
            const categorySelect = document.getElementById('professorCategoryFilter');
            const applyBtn = document.getElementById('applyProfessorFilter');
            const clearBtn = document.getElementById('clearProfessorFilter');
            const tableBody = document.querySelector('#professorsTable tbody');

            const normalize = (value) => (value || '').toString().toLowerCase();

            const filterRows = () => {
                if (!tableBody) return;
                const criteria = filterBy ? filterBy.value : 'all';
                let term = '';
                if (criteria === 'category') {
                    term = categorySelect ? normalize(categorySelect.value) : '';
                } else {
                    term = normalize(filterInput ? filterInput.value.trim() : '');
                }

                Array.from(tableBody.rows).forEach((row) => {
                    let show = true;
                    if (criteria !== 'all' && term !== '') {
                        switch (criteria) {
                            case 'id':
                                show = normalize(row.querySelector('.prof-id')?.textContent).includes(term);
                                break;
                            case 'name':
                                show = normalize(row.querySelector('.prof-name')?.textContent).includes(term);
                                break;
                            case 'category':
                                show = normalize(row.querySelector('.prof-category')?.textContent) === term;
                                break;
                            case 'course':
                                show = normalize(row.querySelector('.prof-courses')?.textContent).includes(term);
                                break;
                            default:
                                show = normalize(row.textContent).includes(term);
                        }
                    }
                    row.style.display = show ? '' : 'none';
                });
            };

            const updateFilterInputState = () => {
                if (!filterBy || !filterInput || !categorySelect) return;
                
                if (filterBy.value === 'all') {
                    filterInput.style.display = 'inline-block';
                    filterInput.disabled = true;
                    filterInput.value = '';
                    categorySelect.style.display = 'none';
                } else if (filterBy.value === 'category') {
                    filterInput.style.display = 'none';
                    categorySelect.style.display = 'inline-block';
                } else {
                    filterInput.style.display = 'inline-block';
                    filterInput.disabled = false;
                    categorySelect.style.display = 'none';
                    
                    if (filterBy.value === 'name') {
                        filterInput.placeholder = 'Enter professor name';
                        filterInput.dir = 'auto'; 
                    } else if (filterBy.value === 'course') {
                        filterInput.placeholder = 'Enter course name';
                        filterInput.dir = 'ltr';
                    } else if (filterBy.value === 'id') {
                        filterInput.placeholder = 'Enter professor ID';
                        filterInput.dir = 'ltr';
                    }
                }
            };

            if (filterBy) {
                filterBy.addEventListener('change', updateFilterInputState);
                updateFilterInputState();
            }

            if (filterInput) {
                filterInput.addEventListener('input', filterRows);
            }

            if (categorySelect) {
                categorySelect.addEventListener('change', filterRows);
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', filterRows);
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (filterBy) filterBy.value = 'all';
                    if (filterInput) filterInput.value = '';
                    updateFilterInputState();
                    filterRows();
                });
            }

            // --- Remove Professor Logic ---
            if (tableBody) {
                tableBody.addEventListener('click', function (event) {
                    const button = event.target.closest('.remove-prof-btn');
                    if (!button) return;
                    const row = button.closest('tr');
                    const profId = button.dataset.profId || '';
                    const profName = (row?.querySelector('.prof-name')?.textContent || '').trim();
                    const courses = (row?.querySelector('.prof-courses')?.textContent || '').trim();

                    if (courses !== '') {
                        alert(`Cannot remove ${profName} (${profId}): This professor is still assigned to courses. Please re-assign these courses first.`);
                        return;
                    }

                    if (confirm(`Are you sure you want to completely delete ${profName} (${profId}) from the database? This action cannot be undone.`)) {
                        const fd = new FormData();
                        fd.append('prof_file_nb', profId);
                        fetch('removeProfessor.php', { method: 'POST', body: fd })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    alert(data.message || 'Professor deleted.');
                                    row?.remove();
                                    const opts = document.querySelectorAll(`option[value="${profId}"]`);
                                    opts.forEach(opt => opt.remove());
                                } else {
                                    alert(data.message || 'Failed to delete professor.');
                                }
                            })
                            .catch(err => {
                                console.error('Error deleting professor:', err);
                                alert('An error occurred while deleting the professor.');
                            });
                    }
                });
            }
        });
    </script>

    <script src="AdminPage.js?v=1.1">  
    </script>

    <?php
        // Clear the view_correctors_loaded flag after rendering
        if (isset($_SESSION["view_correctors_loaded"])) {
            unset($_SESSION["view_correctors_loaded"]);
        }
    ?>

</body>
</html>