document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Notification Permission Request ---
    if ("Notification" in window) {
        if (Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    }

    // --- 2. Utility: Show Browser Notification ---
    function showBrowserNotification(title, message) {
        if ("Notification" in window && Notification.permission === "granted") {
            new Notification(title, {
                body: message,
                icon: "ULFS.ico"
            });
        }
    }

    // --- 3. Add Course Form (Standard AJAX) ---
    const addForm = document.getElementById('addCourse');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('submitCourse', 'true');

            fetch('addCourse.php', { method: 'POST', body: formData })
                .then(response => response.text())
                .then(data => {
                    if (data.includes("Done")) {
                        showBrowserNotification("Course Added", "The course has been successfully saved.");
                        this.reset();
                    } else { 
                        alert("Message: " + data); 
                    }
                })
                .catch(error => console.error('Error:', error));
        });
    }

    // --- 4. Search & Edit Course ---
    const searchCourseForm = document.getElementById('searchCourseForm');
    const searchCourseBtn = document.getElementById('searchCourseBtn');
    const resEditCourse = document.getElementById('resEditCourse');
    const resConfirmCourse = document.getElementById('resConfirmCourse');
    const resDisableCourse = document.getElementById('resDisableCourse');
    const resCancelSearch = document.getElementById('resCancelSearch');

    const editableResultIds = [
        'resCourseName', 'resCourseCredit', 'resCourseHours', 'resCourseYear',
        'resCourseLevel', 'resCourseSemester', 'resCourseMajor', 'resCourseProf', 'resCourseCategory'
    ];
    const lockedResultIds = ['resCourseCode', 'resCourseLang'];

    function getEl(id) {
        return document.getElementById(id);
    }

    function setFieldDisabled(el, disabled) {
        if (!el) return;
        el.disabled = disabled;
    }

    function updateToggleActiveButton() {
        if (!resDisableCourse) return;
        const raw = getEl('hiddenCourseIsActive').value;
        if (raw === '') {
            resDisableCourse.value = 'Disable Course';
            return;
        }
        const active = parseInt(raw, 10) === 1;
        resDisableCourse.value = active ? 'Disable Course' : 'Enable Course';
    }

    function setViewModeResults() {
        [...lockedResultIds, ...editableResultIds].forEach((id) => setFieldDisabled(getEl(id), true));
        if (resEditCourse) resEditCourse.style.display = '';
        if (resConfirmCourse) resConfirmCourse.style.display = 'none';
    }

    function setEditModeResults() {
        lockedResultIds.forEach((id) => setFieldDisabled(getEl(id), true));
        editableResultIds.forEach((id) => setFieldDisabled(getEl(id), false));
        if (resEditCourse) resEditCourse.style.display = 'none';
        if (resConfirmCourse) resConfirmCourse.style.display = '';
    }

    function applyCoursePayload(data) {
        getEl('hiddenCourseCode').value = data.course_code;
        getEl('hiddenCourseLang').value = data.course_lang;
        if (getEl('hiddenCourseMajor')) getEl('hiddenCourseMajor').value = data.major_id;
        getEl('resCourseCode').value = data.course_code;
        getEl('resCourseLang').value = data.course_lang;
        getEl('resCourseName').value = data.course_name;
        getEl('resCourseCredit').value = data.course_credit_nb;
        getEl('resCourseHours').value = data.course_hours_nb;
        getEl('resCourseYear').value = data.uni_year || '';
        getEl('resCourseLevel').value = data.course_level;
        getEl('resCourseSemester').value = String(data.course_semester_nb);
        getEl('resCourseMajor').value = String(data.major_id);
        getEl('resCourseProf').value = String(data.prof_file_nb);
        getEl('resCourseCategory').value = data.course_category;
        if (data.isActive !== undefined && data.isActive !== null) {
            const n = parseInt(data.isActive, 10);
            if (!Number.isNaN(n)) {
                getEl('hiddenCourseIsActive').value = String(n);
            }
        }
        updateToggleActiveButton();
    }

    function clearSearchCourseResults() {
        getEl('hiddenCourseCode').value = '';
        getEl('hiddenCourseLang').value = '';
        if (getEl('hiddenCourseMajor')) getEl('hiddenCourseMajor').value = '';
        getEl('resCourseCode').value = '';
        getEl('resCourseLang').value = 'E';
        getEl('resCourseName').value = '';
        getEl('resCourseCredit').value = '';
        getEl('resCourseHours').value = '';
        getEl('resCourseYear').value = '';
        getEl('resCourseLevel').value = 'L1';
        getEl('resCourseSemester').value = '1';
        if (getEl('resCourseMajor').options.length) getEl('resCourseMajor').selectedIndex = 0;
        if (getEl('resCourseProf').options.length) getEl('resCourseProf').selectedIndex = 0;
        getEl('resCourseCategory').value = 'mandatory';
        getEl('hiddenCourseIsActive').value = '';
        updateToggleActiveButton();
        setViewModeResults();
    }

    if (searchCourseBtn && searchCourseForm) {
        searchCourseForm.addEventListener('submit', (e) => {
            e.preventDefault();
            searchCourseBtn.click();
        });

        searchCourseBtn.addEventListener('click', () => {
            const formData = new FormData(searchCourseForm);
            formData.set('searchBtn', 'true');

            fetch('searchCourse.php', { method: 'POST', body: formData })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status === 'success') {
                        applyCoursePayload(data);
                        setViewModeResults();
                        showBrowserNotification('Course found', 'Course data loaded.');
                    } else {
                        showBrowserNotification('Search', data.message || 'Course not found.');
                        alert(data.message || 'Course not found.');
                        clearSearchCourseResults();
                    }
                })
                .catch((err) => {
                    console.error('Search course error:', err);
                    showBrowserNotification('Search failed', 'Could not load course.');
                    alert('Could not load course.');
                });
        });
    }

    if (resEditCourse) {
        resEditCourse.addEventListener('click', () => {
            if (!getEl('hiddenCourseCode').value) {
                alert('Search for a course first.');
                return;
            }
            setEditModeResults();
        });
    }

    if (resConfirmCourse) {
        resConfirmCourse.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('course_code', getEl('hiddenCourseCode').value);
            fd.append('course_lang', getEl('hiddenCourseLang').value);
            if (getEl('hiddenCourseMajor')) fd.append('old_major_id', getEl('hiddenCourseMajor').value);
            fd.append('course_name', getEl('resCourseName').value);
            fd.append('course_credit_nb', getEl('resCourseCredit').value);
            fd.append('course_hours_nb', getEl('resCourseHours').value);
            fd.append('course_semester_nb', getEl('resCourseSemester').value);
            fd.append('course_level', getEl('resCourseLevel').value);
            fd.append('major_id', getEl('resCourseMajor').value);
            fd.append('course_category', getEl('resCourseCategory').value);
            fd.append('prof_file_nb', getEl('resCourseProf').value);
            fd.append('uni_year', getEl('resCourseYear').value);

            fetch('editCourse.php', { method: 'POST', body: fd })
                .then((r) => r.json())
                .then((data) => {
                    if (data.status === 'success') {
                        showBrowserNotification('Course updated', data.message || 'Changes saved.');
                        applyCoursePayload({
                            course_code: getEl('hiddenCourseCode').value,
                            course_lang: getEl('hiddenCourseLang').value,
                            course_name: getEl('resCourseName').value,
                            course_credit_nb: parseInt(getEl('resCourseCredit').value, 10),
                            course_hours_nb: parseInt(getEl('resCourseHours').value, 10),
                            course_level: getEl('resCourseLevel').value,
                            course_semester_nb: getEl('resCourseSemester').value,
                            major_id: getEl('resCourseMajor').value,
                            course_category: getEl('resCourseCategory').value,
                            prof_file_nb: getEl('resCourseProf').value,
                            uni_year: getEl('resCourseYear').value,
                            isActive: (() => {
                                const v = getEl('hiddenCourseIsActive').value;
                                const n = parseInt(v, 10);
                                return Number.isNaN(n) ? undefined : n;
                            })()
                        });
                        setViewModeResults();
                    } else {
                        alert(data.message || 'Update failed.');
                    }
                })
                .catch((e) => {
                    console.error(e);
                    alert('Update failed.');
                });
        });
    }

    if (resDisableCourse) {
        resDisableCourse.addEventListener('click', () => {
            const code = getEl('hiddenCourseCode').value;
            const lang = getEl('hiddenCourseLang').value;
            const major = getEl('hiddenCourseMajor') ? getEl('hiddenCourseMajor').value : '';

            if (!code || !major) {
                alert('Search for a course first.');
                return;
            }

            const currentlyActive = parseInt(getEl('hiddenCourseIsActive').value, 10) === 1;
            if (currentlyActive) {
                if (!confirm('Disable this course? It will be marked inactive.')) return;
            } else {
                if (!confirm('Enable this course? It will be active again.')) return;
            }

            const fd = new FormData();
            fd.append('action', currentlyActive ? 'disable' : 'enable');
            fd.append('course_code', code);
            fd.append('course_lang', lang);
            fd.append('major_id', major);

            fetch('editCourse.php', { method: 'POST', body: fd })
                .then((r) => r.text().then((text) => ({ status: r.status, text })))
                .then(({ status, text }) => {
                    let data;
                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (err) {
                        console.error('editCourse.php returned non-JSON:', text);
                        alert(
                            'Server did not return JSON (HTTP ' + status + '). ' +
                            'Check that editCourse.php exists in the same folder as AdminPage.php and see the browser Network tab for the raw response.'
                        );
                        return;
                    }
                    if (data.status === 'success') {
                        const next = data.isActive !== undefined ? data.isActive : (currentlyActive ? 0 : 1);
                        getEl('hiddenCourseIsActive').value = String(next);
                        updateToggleActiveButton();
                        if (next === 1) {
                            showBrowserNotification('Course enabled', data.message || 'Course is active again.');
                        } else {
                            showBrowserNotification('Course disabled', data.message || 'Course is now inactive.');
                        }
                    } else {
                        console.error('editCourse.php returned error payload', data, 'raw text:', text);
                        const msg = data.message || (text ? 'Invalid payload: ' + text : 'unknown error');
                        alert('Could not update course status: ' + msg);
                    }
                })
                .catch((e) => {
                    console.error(e);
                    alert('Network error while updating course status.');
                });
        });
    }

    if (resCancelSearch) {
        resCancelSearch.addEventListener('click', () => {
            getEl('searchCode').value = '';
            getEl('searchCourseLang').value = 'E';
            if (getEl('searchCourseMajor')) getEl('searchCourseMajor').selectedIndex = 0;
            clearSearchCourseResults();
        });
    }

    // --- 5. Correctors "Find" Logic ---
    const findButtons = document.querySelectorAll('input[type="button"][value="Find"]');
    findButtons.forEach(button => {
        button.addEventListener('click', () => {
            const dropdown = button.closest('.dropdown-content');
            const table = dropdown?.querySelector('.table-container');
            if (table) table.style.display = 'block';

            const correctorsSection = button.closest('#content-correctors');
            if (correctorsSection) {
                const editBtn = correctorsSection.querySelector('#editCorr');
                const deleteBtn = correctorsSection.querySelector('#deleteCorr');
                if (editBtn) editBtn.style.display = 'inline-block';
                if (deleteBtn) deleteBtn.style.display = 'inline-block';
            }
        });
    });

    // --- 6. Correctors Edit/Delete/Apply Logic ---
    const editCorrBtn = document.getElementById('editCorr');
    const deleteCorrBtn = document.getElementById('deleteCorr');
    const applyCorrBtn = document.getElementById('applyCorr');
    const cancelBtn = document.getElementById('cancelButton');

    function getCorrectorSelects() {
        return Array.from(document.querySelectorAll('.corrector-select'));
    }

    function setCorrectorSelectsDisabled(disabled) {
        getCorrectorSelects().forEach(sel => {
            sel.disabled = disabled;
        });
    }

    function syncCorrectorPairOptions() {
        const rows = Array.from(document.querySelectorAll('#correctorsTableBody tr'));
        rows.forEach(row => {
            const selects = row.querySelectorAll('select.corrector-select');
            if (selects.length !== 2) return;
            const secondSel = selects[0];
            const thirdSel = selects[1];
            const secondVal = secondSel.value;
            const thirdVal = thirdSel.value;

            Array.from(secondSel.options).forEach(opt => {
                if (opt.value === '') return;
                opt.disabled = opt.value === thirdVal;
            });
            Array.from(thirdSel.options).forEach(opt => {
                if (opt.value === '') return;
                opt.disabled = opt.value === secondVal;
            });
        });
    }

    function bindCorrectorPairGuards() {
        const rows = Array.from(document.querySelectorAll('#correctorsTableBody tr'));
        rows.forEach(row => {
            const selects = row.querySelectorAll('select.corrector-select');
            if (selects.length !== 2) return;
            const secondSel = selects[0];
            const thirdSel = selects[1];
            if (secondSel.dataset.guardBound === '1' && thirdSel.dataset.guardBound === '1') {
                return;
            }

            secondSel.addEventListener('change', () => {
                if (secondSel.value !== '' && secondSel.value === thirdSel.value) {
                    thirdSel.value = '';
                }
                syncCorrectorPairOptions();
            });

            thirdSel.addEventListener('change', () => {
                if (thirdSel.value !== '' && thirdSel.value === secondSel.value) {
                    secondSel.value = '';
                }
                syncCorrectorPairOptions();
            });

            secondSel.dataset.guardBound = '1';
            thirdSel.dataset.guardBound = '1';
        });

        syncCorrectorPairOptions();
    }

    function setViewModeCorrectors() {
        setCorrectorSelectsDisabled(true);
        bindCorrectorPairGuards();
        const hasRows = getCorrectorSelects().length > 0;
        if (editCorrBtn) editCorrBtn.style.display = hasRows ? 'inline-block' : 'none';
        if (deleteCorrBtn) deleteCorrBtn.style.display = hasRows ? 'inline-block' : 'none';
        if (applyCorrBtn) applyCorrBtn.style.display = 'none';
        const tableContainer = document.querySelector('#content-correctors .table-container');
        if (tableContainer) tableContainer.style.display = 'block';
    }

    function setEditModeCorrectors() {
        setCorrectorSelectsDisabled(false);
        bindCorrectorPairGuards();
        if (editCorrBtn) editCorrBtn.style.display = 'none';
        if (deleteCorrBtn) deleteCorrBtn.style.display = 'inline-block';
        if (applyCorrBtn) applyCorrBtn.style.display = 'inline-block';
    }

    function populateCorrectorsTable(courses, professors) {
        const tbody = document.getElementById('correctorsTableBody');
        tbody.innerHTML = '';
        if (courses.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#64748b;">No courses match these filters (check major, level, session, and language).</td></tr>';
            return;
        }
        courses.forEach(r => {
            const profName = ((r.prof_first_name || '') + ' ' + (r.prof_last_name || '')).trim();
            const courseCode = r.course_code;
            const firstCorrectorId = r.first_corrector_id !== undefined && r.first_corrector_id !== null ? String(r.first_corrector_id) : '';
            const secondSelected = r.second_corrector !== undefined && r.second_corrector !== null ? String(r.second_corrector) : '';
            const thirdSelected = r.third_corrector !== undefined && r.third_corrector !== null ? String(r.third_corrector) : '';

            const secondOptions = [];
            if (secondSelected && professors[secondSelected]) {
                secondOptions.push(`<option value='${secondSelected}' selected>${professors[secondSelected]}</option>`);
                secondOptions.push(`<option value=''>null</option>`);
            } else {
                secondOptions.push(`<option value='' selected>null</option>`);
            }
            Object.entries(professors).forEach(([id, name]) => {
                if (id === secondSelected || id === firstCorrectorId || id === thirdSelected) return;
                secondOptions.push(`<option value='${id}'>${name}</option>`);
            });

            const thirdOptions = [];
            if (thirdSelected && professors[thirdSelected]) {
                thirdOptions.push(`<option value='${thirdSelected}' selected>${professors[thirdSelected]}</option>`);
                thirdOptions.push(`<option value=''>null</option>`);
            } else {
                thirdOptions.push(`<option value='' selected>null</option>`);
            }
            Object.entries(professors).forEach(([id, name]) => {
                if (id === thirdSelected || id === firstCorrectorId || id === secondSelected) return;
                thirdOptions.push(`<option value='${id}'>${name}</option>`);
            });

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${courseCode}</td>
                <td>${r.course_name}</td>
                <td>${r.course_lang}</td>
                <td>${profName}</td>
                <td><select name='second_corrector[${courseCode}][${r.course_lang}]' disabled class='corrector-select'>${secondOptions.join('')}</select></td>
                <td><select name='third_corrector[${courseCode}][${r.course_lang}]' disabled class='corrector-select'>${thirdOptions.join('')}</select></td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Find: AJAX (session updated server-side; table filled without full page reload)
    const insertCorrectorsForm = document.getElementById('insertCorrectors');
    if (insertCorrectorsForm) {
        insertCorrectorsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(insertCorrectorsForm);
            formData.set('findBtn', 'true');

            fetch('insertCorrectors.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
                credentials: 'same-origin',
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        populateCorrectorsTable(data.courses, data.professors);
                        const details = document.getElementById('insertCorrectorsDetails');
                        if (details) details.open = true;
                        const tableContainer = document.querySelector('#content-correctors .table-container');
                        if (tableContainer) tableContainer.style.display = 'block';
                        setViewModeCorrectors();
                        bindCorrectorPairGuards();
                        showBrowserNotification('Correctors', 'Courses loaded successfully.');
                    } else {
                        showBrowserNotification('Correctors', data.message || 'Failed to load courses.');
                        alert(data.message || 'Failed to load courses.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showBrowserNotification('Correctors', 'Error loading courses.');
                    alert('Error loading courses.');
                });
        });
    }

    if (editCorrBtn) {
        editCorrBtn.addEventListener('click', () => {
            setEditModeCorrectors();
        });
    }

    if (deleteCorrBtn) {
        deleteCorrBtn.addEventListener('click', () => {
            getCorrectorSelects().forEach(sel => sel.value = '');
            setEditModeCorrectors();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', (event) => {
            event.preventDefault();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'insertCorrectors.php';
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'cancelBtn';
            hidden.value = '1';
            form.appendChild(hidden);
            document.body.appendChild(form);
            form.submit();
        });
    }

    bindCorrectorPairGuards();

    // --- 7. Insert Numbers Logic ---
    const insertNumbersForm = document.getElementById('insertNumbers');
    const numbersTableBody = document.getElementById('numbersTableBody');
    const editNumbersBtn = document.getElementById('editNumbers');
    const deleteNumbersBtn = document.getElementById('deleteNumbers');
    const applyNumbersBtn = document.getElementById('applyNumbers');
    const cancelNumberBtn = document.getElementById('cancelNumber');

    function getNumberInputs() {
        return Array.from(document.querySelectorAll('.number-input'));
    }

    function setNumberInputsDisabled(disabled) {
        getNumberInputs().forEach(input => input.disabled = disabled);
    }

    function setViewModeNumbers() {
        setNumberInputsDisabled(true);
        if (applyNumbersBtn) applyNumbersBtn.style.display = 'none';
        if (editNumbersBtn) editNumbersBtn.style.display = 'inline-block';
        if (deleteNumbersBtn) deleteNumbersBtn.style.display = 'inline-block';
    }

    function setEditModeNumbers() {
        setNumberInputsDisabled(false);
        if (applyNumbersBtn) applyNumbersBtn.style.display = 'inline-block';
        if (editNumbersBtn) editNumbersBtn.style.display = 'none';
        if (deleteNumbersBtn) deleteNumbersBtn.style.display = 'inline-block';
    }

    function populateNumbersTable(courses, professors, isFinal) {
        if (!numbersTableBody) return;
        numbersTableBody.innerHTML = '';
        if (courses.length === 0) {
            numbersTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#64748b;">No courses match these filters.</td></tr>';
            return;
        }
        courses.forEach(r => {
            const courseCode = r.course_code;
            const courseLang = r.course_lang;
            const profName = ((r.prof_first_name || '') + ' ' + (r.prof_last_name || '')).trim() || professors[r.prof_file_nb] || 'Unknown';
            const secondName = r.second_corrector ? (professors[r.second_corrector] || 'Unknown') : 'None';
            const val1 = isFinal ? r.final_first_corrector : r.partial_first_corrector;
            const val2 = isFinal ? r.final_second_corrector : r.partial_second_corrector;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${courseCode}</td>
                <td>${r.course_name}</td>
                <td>${courseLang}</td>
                <td>${profName}</td>
                <td><input type='number' name='first_numbers[${courseCode}][${courseLang}]' value='${val1}' class='number-input premium-number-input' disabled></td>
                <td>${secondName}</td>
                <td><input type='number' name='second_numbers[${courseCode}][${courseLang}]' value='${val2}' class='number-input premium-number-input' disabled></td>
            `;
            numbersTableBody.appendChild(tr);
        });
    }

    if (insertNumbersForm) {
        insertNumbersForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(insertNumbersForm);
            formData.set('findNumber', 'true');

            fetch('insertNumbers.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
                credentials: 'same-origin',
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const session = formData.get('numberSession');
                        const exam = formData.get('numberExam');
                        const isFinal = (session === 'sess2' || exam === 'F');
                        populateNumbersTable(data.courses, data.professors, isFinal);
                        
                        const details = document.getElementById('insertNumbersDetails');
                        if (details) details.open = true;
                        const tableContainer = document.querySelector('#numbersForm .table-container');
                        if (tableContainer) tableContainer.style.display = 'block';
                        
                        setViewModeNumbers();
                        if (editNumbersBtn) editNumbersBtn.style.display = 'inline-block';
                        if (deleteNumbersBtn) deleteNumbersBtn.style.display = 'inline-block';
                        
                        showBrowserNotification('Numbers', 'Data loaded successfully.');
                    } else {
                        alert(data.message || 'Failed to load numbers.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error loading numbers.');
                });
        });
    }

    if (editNumbersBtn) {
        editNumbersBtn.addEventListener('click', () => {
            setEditModeNumbers();
        });
    }

    if (deleteNumbersBtn) {
        deleteNumbersBtn.addEventListener('click', () => {
            getNumberInputs().forEach(input => input.value = 0);
            setEditModeNumbers();
        });
    }

    if (cancelNumberBtn) {
        cancelNumberBtn.addEventListener('click', (event) => {
            event.preventDefault();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'insertNumbers.php';
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'cancelNumber';
            hidden.value = '1';
            form.appendChild(hidden);
            document.body.appendChild(form);
            form.submit();
        });
    }

    // --- 8. View Correctors by Course ID (AJAX) ---
    const searchCorrectorForm = document.getElementById('searchCorrector');
    if (searchCorrectorForm) {
        searchCorrectorForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const courseId = document.getElementById('courseId')?.value || '';
            const correctorLang = document.getElementById('correctorLang')?.value || 'E';
            const correctorMajor = document.getElementById('correctorMajor')?.value || '';
            const correctorSession = document.getElementById('correctorSession')?.value || 'sem1';
            
            const formData = new FormData();
            formData.append('courseId', courseId);
            formData.append('correctorLang', correctorLang);
            formData.append('correctorMajor', correctorMajor);
            formData.append('correctorSession', correctorSession);
            formData.append('searchID', 'true');
            
            fetch('viewCorrectors.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showBrowserNotification('Correctors', 'Search completed. Refreshing...');
                        // Redirect with tab parameter to ensure it stays on the correctors tab
                        location.href = 'AdminPage.php?tab=correctors';
                    } else {
                        showBrowserNotification('Error', data.message || 'Search failed');
                        alert(data.message || 'Search failed');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showBrowserNotification('Error', 'Failed to search correctors');
                });
        });
    }

});