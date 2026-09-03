// ===============================
// CHANGE BETWEEN PAGES (TABS)
// ===============================

function showPage(pageName, button) {

    // Get all pages
    var pages = document.querySelectorAll(".page");

    // Hide every page
    pages.forEach(function(page) {
        page.classList.remove("active");
    });

    // Show the page we selected
    var targetPage = document.getElementById(pageName);
    if (targetPage) {
        targetPage.classList.add("active");
    }

    // Get all menu buttons
    var menuItems = document.querySelectorAll(".menu-item");

    // Remove active style from all buttons
    menuItems.forEach(function(item) {
        item.classList.remove("active");
    });

    // Add active style to the button we clicked
    if (button) {
        button.classList.add("active");
    }

    // Close sidebar on mobile
    var sidebar = document.getElementById("sidebar");
    if (sidebar) {
        sidebar.classList.remove("show");
    }
}

// ===============================
// MOBILE SIDEBAR TOGGLE
// ===============================

function toggleSidebar() {
    var sidebar = document.getElementById("sidebar");
    if (sidebar) {
        sidebar.classList.toggle("show");
    }
}

// ===============================
// LOGOUT CONFIRMATION
// ===============================

function logout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}

// ===============================
// QUICK REDIRECTIONS FOR ACTIONS
// ===============================

function addIdea() {
    window.location.href = "submit_idea.php";
}

function addProject() {
    window.location.href = "add_project.php";
}

function addCampaign() {
    window.location.href = "add_campaign.php";
}

// ===============================
// DOWNLOAD / PRINT REPORT
// ===============================

function downloadReport() {
    window.print();
}