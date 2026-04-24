<?php
    session_start();
    include("database.php");

    if (!isset($_SESSION["email"])||isset($_POST["logout"])) {
        header("location: login.php");
        unset($_SESSION["email"]);
        exit();
    }

    $email = $_SESSION["email"];

    $sql_prof = "SELECT prof_file_nb, prof_first_name, prof_last_name,prof_father_name, prof_birth_date, prof_address, prof_phone, prof_email, dep_id, prof_category FROM professor WHERE prof_email = ?";
    $stmt_prof = mysqli_prepare($conn, $sql_prof);
    mysqli_stmt_bind_param($stmt_prof, "s", $email);
    mysqli_stmt_execute($stmt_prof);
    $result_prof = mysqli_stmt_get_result($stmt_prof);
    $professor = mysqli_fetch_assoc($result_prof);

    if (!$professor) {
        die("Professor not found");
    }

    
    $sql_dep = "SELECT dep_name FROM department WHERE dep_id = ?";
    $stmt_dep = mysqli_prepare($conn, $sql_dep);
    mysqli_stmt_bind_param($stmt_dep, "s", $professor['dep_id']);
    mysqli_stmt_execute($stmt_dep);
    $result_dep = mysqli_stmt_get_result($stmt_dep);
    $department = mysqli_fetch_assoc($result_dep);

    $sql_courses = "SELECT c.course_code, c.course_name, c.course_credit_nb,c.course_level, c.course_lang, c.course_semester_nb, m.major_name
                    FROM teaching t
                    JOIN course c ON t.course_code = c.course_code AND t.course_lang = c.course_lang
                    JOIN major m ON c.major_id = m.major_id
                    WHERE t.prof_file_nb = AND t.isActive = 1?
                    ORDER BY t.uni_year DESC, c.course_name";
    $stmt_courses = mysqli_prepare($conn, $sql_courses);
    mysqli_stmt_bind_param($stmt_courses, "i", $professor['prof_file_nb']);
    mysqli_stmt_execute($stmt_courses);
    $result_courses = mysqli_stmt_get_result($stmt_courses);

    $birth_date = date("d-M-Y", strtotime($professor['prof_birth_date']));

    $dep_options = [
        'css' => 'Computer Science and Statistics',
        'math' => 'Math',
        'pe' => 'Physics and Electronics',
        'bio' => 'Biology',
        'bioch' => 'Biochemistry',
        'chem' => 'Chemistry'
    ];
    $dep_display = isset($dep_options[$professor['dep_id']]) ? $dep_options[$professor['dep_id']] : $professor['dep_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Page</title>
    <link rel="stylesheet" href="ProfPage.css">
    <link rel="icon" href="ULFS.ico">
</head>
<body>

    <input type="radio" name="nav" id="tab-profile" checked>
    <input type="radio" name="nav" id="tab-courses">
    

    <aside class="sidebar">
        <div class="logo-section">
            <h2>Lebanese University</h2>
            <p>Faculty of Science II</p>
        </div>
        
        <nav>
            <label for="tab-profile" class="nav-item label-profile">
                <span class="icon">👤</span> Profile
            </label>
            <label for="tab-courses" class="nav-item label-courses">
                <span class="icon">📚</span> My Courses
            </label>
        </nav>
    </aside>

    <main class="main-content">
        <header class="welcome-header" style="display: flex; justify-content: space-between; align-items: center;">
            
            <div class="user-info">
                Welcome Dr. <span><?php echo htmlspecialchars($professor['prof_first_name'] . ' ' . $professor['prof_last_name']); ?></span>
            </div>

            <form method="post" style="margin: 0;" id="logoutForm">
                <input type="hidden" name="logout" value="1">
                <button type="button" name="logout" id="logout-button" value="logout" 
                    style="background-color: #d32f2f; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;"
                    onclick="confirmLogout()">
                    logout
                </button>
            </form>

        </header>

        <section id="content-profile" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>Profile Information</h3>
                    <button type="button" id="editProfile" class="btn">Edit Information</button>
                    <button type="submit" form="profile-form" id="applyProfileChanges" class="btn" style="display: none;">Apply Changes</button>
                </div>
                
                <div class="form-container">
                <form id="profile-form" action="prof_profile.php" method="POST" class="profile-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($professor['prof_first_name']); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($professor['prof_last_name']); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>Father Name</label>
                        <input type="text" name="father_name" value="<?php echo htmlspecialchars($professor['prof_father_name']); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>Birth Date</label>
                        <input type="text" name="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($professor['prof_address'] ?? ''); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($professor['prof_phone'] ?? ''); ?>" disabled class="profileInput">
                    </div>
                    <div class="form-group">
                        <label>File Number</label>
                        <input type="text" name="file_number" value="<?php echo htmlspecialchars($professor['prof_file_nb']); ?>" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" disabled class="profileInput">
                        <option value="math" <?php echo ($professor['dep_id'] == 'math') ? 'selected' : ''; ?>>Math</option>
                        <option value="css" <?php echo ($professor['dep_id'] == 'css') ? 'selected' : ''; ?>>Computer Science and Statistics</option>
                        <option value="pe" <?php echo ($professor['dep_id'] == 'pe') ? 'selected' : ''; ?>>Physics and Electronics</option>
                        <option value="bio" <?php echo ($professor['dep_id'] == 'bio') ? 'selected' : ''; ?>>Biology</option>
                        <option value="bioch" <?php echo ($professor['dep_id'] == 'bioch') ? 'selected' : ''; ?>>Biochemistry</option>
                        <option value="chem" <?php echo ($professor['dep_id'] == 'chem') ? 'selected' : ''; ?>>Chemistry</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" disabled class="profileInput">
                        <option value="متعاقد بالساعة" <?php echo ($professor['prof_category'] == 'متعاقد بالساعة') ? 'selected' : ''; ?>>متعاقد بالساعة</option>
                        <option value="ملاك" <?php echo ($professor['prof_category'] == 'ملاك') ? 'selected' : ''; ?>>ملاك</option>
                        <option value="متفرغ" <?php echo ($professor['prof_category'] == 'متفرغ') ? 'selected' : ''; ?>>متفرغ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($professor['prof_email']); ?>" disabled class="profileInput">
                    </div>
                </form>
                </div>
            </div>
        </section>

        <section id="content-courses" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h3>My Courses</h3>
                    <form id="exportExcel" action="Prof_export_course.php" method="post"><button type="submit" class="btn btn-secondary" id="ProfexcelExport" name="exportExcel" >Excel Export</button></form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Semester</th>
                                <th>Language</th>
                                <th>Major</th>
                                <th>Level</th>
                                <th>Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = mysqli_fetch_assoc($result_courses)): ?>
                            <tr>
                                <td><a href="#" class="code-link"><?php echo htmlspecialchars($course['course_code']); ?></a></td>
                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_semester_nb']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_lang']); ?></td>
                                <td><?php echo htmlspecialchars($course['major_name']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_level']); ?></td>
                                <td><?php echo htmlspecialchars($course['course_credit_nb']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editBtn = document.querySelector('#editProfile');
            if (editBtn) {
                editBtn.addEventListener('click', () => {
                    const profileForm = document.querySelector('#profile-form');
                    if (profileForm) {
                        const profileInputs = profileForm.querySelectorAll('.profileInput');
                        profileInputs.forEach(input => {
                            input.disabled = false;
                        });
                        const applyBtn = document.querySelector('#applyProfileChanges');
                        if (applyBtn) {
                            applyBtn.style.display = 'inline-block';
                            editBtn.style.display = 'none';
                        }
                    }
                });
            }

            const applyBtn = document.querySelector('#applyProfileChanges');
            if (applyBtn) {
                applyBtn.addEventListener('click', (e) => {
                    e.preventDefault();

                    const profileForm = document.querySelector('#profile-form');
                    if (profileForm) {
                        const formData = new FormData(profileForm);

                        fetch('Edit_prof_profile.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(data => {
                            if (data.includes("successfully")) {
                                alert("Success in updating the profile");
                                const profileInputs = profileForm.querySelectorAll('.profileInput');
                                profileInputs.forEach(input => {
                                    input.disabled = true;
                                });
                                applyBtn.style.display = 'none';
                                editBtn.style.display = 'inline-block';
                            } else {
                                alert("Error: The profile did not update. Something went wrong" );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert("An error occurred while saving changes.");
           
                     });
                    }
                });
            }
        });
    </script>

    <script>
        // Global logout confirmation function
        function confirmLogout() {
            if (confirm('Are you sure you want to logout?')) {
                // Submit the logout form by ID
                document.getElementById('logoutForm').submit();
            }
        }
    </script>

    

</body>
</html>