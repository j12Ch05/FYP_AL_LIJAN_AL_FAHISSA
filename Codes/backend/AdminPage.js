document.getElementById('addCourse').addEventListener('submit', function(e) {
            // 1. Prevent the default "jump" to addCourse.php
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            
            // Add the submit key so the PHP isset() triggers
            formData.append('submitCourse', 'true');

            // 2. Send the data in the background
            fetch('addCourse.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // 3. Handle the response
                if (data.includes("Done")) {
                    alert("Success: " + data);
                    form.reset(); // Clears the form fields
                } else {
                    alert("Message: " + data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred while saving.");
            });
        });
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

 // 1. Select the specific search form and its buttons
const searchForm = document.getElementById('searchCourse');
const editBtn = document.getElementById('editCourse');
const confirmBtn = document.getElementById('confirmCourse');
const cancelBtn = document.getElementById('cancelSearch');

// 2. Select all inputs/selects EXCEPT the initial search bar inputs
// We want the "fetched" info to be disabled, but the "Search" button to stay active
const infoInputs = searchForm.querySelectorAll('input:not(#searchbtn):not(#searchCode), select:not(#courseLang)');

// Function to toggle the state
function toggleSearchInputs(isDisabled) {
    infoInputs.forEach(input => {
        input.disabled = isDisabled;
    });
}

// 3. Initial State: Information fields are locked
toggleSearchInputs(true);

// 4. Edit Button Logic
editBtn.addEventListener('click', function() {
    toggleSearchInputs(false); // Enable fields
    
    // Swap buttons
    editBtn.style.display = 'none';
    confirmBtn.style.display = 'inline-block';
});

// 5. Cancel Button Logic
cancelBtn.addEventListener('click', function() {
    toggleSearchInputs(true); // Disable fields
    
    // Swap buttons back
    editBtn.style.display = 'inline-block';
    confirmBtn.style.display = 'none';
});