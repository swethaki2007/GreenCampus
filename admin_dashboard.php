<?php
session_start();
require_once "db.php";

// Admin access only
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION["name"] ?? "Administrator";
$admin_message = "";
$admin_message_type = "success";

// Handle Create Campaign action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "create_campaign") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $campaign_date = $_POST["campaign_date"] ?? "";
    $max_volunteers = intval($_POST["max_volunteers"] ?? 0);

    if (empty($title) || empty($description) || empty($location) || empty($campaign_date) || $max_volunteers <= 0) {
        $admin_message = "Please fill in all campaign fields correctly.";
        $admin_message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO campaigns (title, description, location, campaign_date, max_volunteers) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $description, $location, $campaign_date, $max_volunteers);
        if ($stmt->execute()) {
            $admin_message = "Campaign created successfully! 🌱";
            $admin_message_type = "success";
        } else {
            $admin_message = "Failed to create campaign: " . $stmt->error;
            $admin_message_type = "error";
        }
        $stmt->close();
    }
}

// Handle Create Project action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "create_project") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $progress = intval($_POST["progress"] ?? 0);
    $status = $_POST["status"] ?? "active";

    if (empty($title) || empty($description) || empty($category)) {
        $admin_message = "Please fill in all project fields.";
        $admin_message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO projects (title, description, category, progress, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $title, $description, $category, $progress, $status);
        if ($stmt->execute()) {
            $admin_message = "Project created successfully! 🌱";
            $admin_message_type = "success";
        } else {
            $admin_message = "Failed to create project: " . $stmt->error;
            $admin_message_type = "error";
        }
        $stmt->close();
    }
}

// Handle Delete Project action
if (isset($_GET["action"]) && $_GET["action"] === "delete_project" && isset($_GET["id"])) {
    $project_id = intval($_GET["id"]);
    $del_stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
    $del_stmt->bind_param("i", $project_id);
    if ($del_stmt->execute()) {
        $admin_message = "Project deleted successfully.";
        $admin_message_type = "success";
    } else {
        $admin_message = "Failed to delete project: " . $del_stmt->error;
        $admin_message_type = "error";
    }
    $del_stmt->close();
}

// Handle Delete Campaign action
if (isset($_GET["action"]) && $_GET["action"] === "delete_campaign" && isset($_GET["id"])) {
    $campaign_id = intval($_GET["id"]);
    
    // First remove related registrations
    $del_reg = $conn->prepare("DELETE FROM registrations WHERE campaign_id = ?");
    $del_reg->bind_param("i", $campaign_id);
    $del_reg->execute();
    $del_reg->close();

    $del_camp = $conn->prepare("DELETE FROM campaigns WHERE id = ?");
    $del_camp->bind_param("i", $campaign_id);
    if ($del_camp->execute()) {
        $admin_message = "Campaign and associated registrations deleted successfully.";
        $admin_message_type = "success";
    } else {
        $admin_message = "Failed to delete campaign: " . $del_camp->error;
        $admin_message_type = "error";
    }
    $del_camp->close();
}

// Fetch stats
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()["count"] ?? 0;
$pending_ideas = $conn->query("SELECT COUNT(*) AS count FROM ideas WHERE status = 'pending'")->fetch_assoc()["count"] ?? 0;
$active_projects = $conn->query("SELECT COUNT(*) AS count FROM projects WHERE status = 'active' OR status = 'Active'")->fetch_assoc()["count"] ?? 0;
$total_projects = $conn->query("SELECT COUNT(*) AS count FROM projects")->fetch_assoc()["count"] ?? 0;
$total_campaigns = $conn->query("SELECT COUNT(*) AS count FROM campaigns")->fetch_assoc()["count"] ?? 0;
$total_registrations = $conn->query("SELECT COUNT(*) AS count FROM registrations")->fetch_assoc()["count"] ?? 0;

// Fetch Ideas
$ideas_sql = "
    SELECT
        ideas.*,
        students.name AS student_name,
        students.email AS student_email
    FROM ideas
    INNER JOIN students ON ideas.student_id = students.id
    ORDER BY ideas.created_at DESC
";
$ideas_result = $conn->query($ideas_sql);

// Fetch Projects
$projects_sql = "SELECT * FROM projects ORDER BY created_at DESC";
$projects_result = $conn->query($projects_sql);

// Fetch Campaigns
$campaigns_sql = "
    SELECT
        c.*,
        (SELECT COUNT(*) FROM registrations WHERE campaign_id = c.id) AS registered_volunteers
    FROM campaigns c
    ORDER BY c.campaign_date DESC
";
$campaigns_result = $conn->query($campaigns_sql);

// Fetch Registrations
$registrations_sql = "
    SELECT
        r.id AS reg_id,
        r.registered_at,
        s.name AS student_name,
        s.email AS student_email,
        c.title AS campaign_title,
        c.location AS campaign_location,
        c.campaign_date
    FROM registrations r
    JOIN students s ON r.student_id = s.id
    JOIN campaigns c ON r.campaign_id = c.id
    ORDER BY r.registered_at DESC
";
$registrations_result = $conn->query($registrations_sql);

// Fetch Students
$students_sql = "SELECT id, name, email, created_at FROM students ORDER BY created_at DESC";
$students_result = $conn->query($students_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenCampus - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert-banner {
            padding: 14px 20px;
            margin: 20px 35px 0;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        .alert-banner.success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .alert-banner.error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .btn-action {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            margin-right: 4px;
        }
        .btn-approve {
            background: #16a34a;
            color: white;
        }
        .btn-reject {
            background: #dc2626;
            color: white;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }
        .btn-delete:hover {
            background: #b91c1c;
        }
        .category-tag {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            border: 1px solid #e8f5e9;
        }
        .form-card h3 {
            margin-top: 0;
            color: #1b5e20;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .btn-submit-action {
            background: #1b5e20;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-submit-action:hover {
            background: #14532d;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <h2 class="logo">🌱 GreenCampus</h2>

        <p class="menu-title">MAIN</p>
        <div class="menu-section">
            <button class="menu-item active" onclick="showPage('dashboard', this)">
                <span>🏠</span> Dashboard
            </button>
            <button class="menu-item" onclick="showPage('reports', this)">
                <span>📊</span> Reports
            </button>
        </div>

        <p class="menu-title">MANAGEMENT</p>
        <div class="menu-section">
            <button class="menu-item" onclick="showPage('ideas', this)">
                <span>💡</span> Ideas
            </button>
            <button class="menu-item" onclick="showPage('projects', this)">
                <span>🏗️</span> Projects
            </button>
            <button class="menu-item" onclick="showPage('campaigns', this)">
                <span>📢</span> Campaigns
            </button>
        </div>

        <p class="menu-title">USERS & DATA</p>
        <div class="menu-section">
            <button class="menu-item" onclick="showPage('students', this)">
                <span>🎓</span> Students
            </button>
            <button class="menu-item" onclick="showPage('registrations', this)">
                <span>🤝</span> Registrations
            </button>
        </div>

        <button class="menu-item logout" onclick="logout()">
            <span>🚪</span> Logout
        </button>
    </aside>

    <!-- MAIN -->
    <main class="main">

        <!-- TOPBAR -->
        <header class="topbar">
            <button class="mobile-btn" onclick="toggleSidebar()">☰</button>
            <div>
                <h2>Administrator Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Manage GreenCampus activities here.</p>
            </div>

            <div class="profile">
                <div class="avatar">A</div>
                <div>
                    <b><?php echo htmlspecialchars($admin_name); ?></b>
                    <small>Administrator</small>
                </div>
            </div>
        </header>

        <?php if (!empty($admin_message)): ?>
            <div class="alert-banner <?php echo htmlspecialchars($admin_message_type); ?>">
                <?php echo htmlspecialchars($admin_message); ?>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: DASHBOARD -->
        <section id="dashboard" class="page active">
            <h1>Admin Overview</h1>
            <p class="subtitle">Real-time status across GreenCampus initiatives.</p>

            <!-- STATS -->
            <div class="stats">
                <div class="stat">
                    <span>🎓</span>
                    <div>
                        <h3><?php echo $total_students; ?></h3>
                        <p>Students</p>
                    </div>
                </div>

                <div class="stat">
                    <span>💡</span>
                    <div>
                        <h3><?php echo $pending_ideas; ?></h3>
                        <p>Pending Ideas</p>
                    </div>
                </div>

                <div class="stat">
                    <span>🏗️</span>
                    <div>
                        <h3><?php echo $active_projects; ?></h3>
                        <p>Active Projects</p>
                    </div>
                </div>

                <div class="stat">
                    <span>📢</span>
                    <div>
                        <h3><?php echo $total_campaigns; ?></h3>
                        <p>Campaigns</p>
                    </div>
                </div>

                <div class="stat">
                    <span>🤝</span>
                    <div>
                        <h3><?php echo $total_registrations; ?></h3>
                        <p>Registrations</p>
                    </div>
                </div>
            </div>

            <!-- MANAGEMENT CARDS -->
            <h2 class="section-title">Manage GreenCampus</h2>
            <div class="cards">
                <div class="card">
                    <div class="icon">💡</div>
                    <h2>Student Ideas</h2>
                    <p>Review student submitted ideas and approve or reject them.</p>
                    <button onclick="showPage('ideas')">Manage Ideas</button>
                </div>

                <div class="card">
                    <div class="icon">🏗️</div>
                    <h2>Campus Projects</h2>
                    <p>Create new projects, view active initiatives, and update progress.</p>
                    <button onclick="showPage('projects')">Manage Projects</button>
                </div>

                <div class="card">
                    <div class="icon">📢</div>
                    <h2>Campaigns</h2>
                    <p>Organize clean-up, tree planting, and manage volunteer limits.</p>
                    <button onclick="showPage('campaigns')">Manage Campaigns</button>
                </div>

                <div class="card">
                    <div class="icon">📊</div>
                    <h2>Impact Reports</h2>
                    <p>Track student participation and environmental impact metrics.</p>
                    <button onclick="showPage('reports')">View Reports</button>
                </div>
            </div>
        </section>

        <!-- SECTION 2: IDEAS -->
        <section id="ideas" class="page">
            <div class="page-head">
                <div>
                    <h1>💡 Student Ideas Management</h1>
                    <p class="subtitle">Review, approve, or reject student ideas.</p>
                </div>
            </div>

            <div class="table-box">
                <?php if ($ideas_result && $ideas_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Title & Description</th>
                                <th>Category</th>
                                <th>Submitted Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($idea = $ideas_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $idea["id"]; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($idea["student_name"]); ?></strong><br>
                                        <small style="color:#666;"><?php echo htmlspecialchars($idea["student_email"]); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($idea["title"]); ?></strong><br>
                                        <small style="color:#555;"><?php echo htmlspecialchars($idea["description"]); ?></small>
                                    </td>
                                    <td>
                                        <span class="category-tag"><?php echo htmlspecialchars($idea["category"]); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d M Y", strtotime($idea["created_at"]))); ?></td>
                                    <td>
                                        <span class="status <?php echo strtolower($idea["status"]); ?>">
                                            <?php echo htmlspecialchars($idea["status"]); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (strtolower($idea["status"]) === "pending"): ?>
                                            <a href="update_idea.php?id=<?php echo $idea['id']; ?>&status=approved" class="btn-action btn-approve" onclick="return confirm('Approve this idea?');">Approve</a>
                                            <a href="update_idea.php?id=<?php echo $idea['id']; ?>&status=rejected" class="btn-action btn-reject" onclick="return confirm('Reject this idea?');">Reject</a>
                                        <?php else: ?>
                                            <small style="color:#888;">Completed</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No ideas submitted yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 3: PROJECTS -->
        <section id="projects" class="page">
            <div class="page-head">
                <div>
                    <h1>🏗️ Campus Projects Management</h1>
                    <p class="subtitle">Create and manage GreenCampus environmental projects.</p>
                </div>
            </div>

            <!-- ADD PROJECT FORM -->
            <div class="form-card">
                <h3>+ Create New Project</h3>
                <form method="POST" action="admin_dashboard.php">
                    <input type="hidden" name="action" value="create_project">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Project Title</label>
                            <input type="text" name="title" placeholder="e.g., Solar Powered Benches" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" required>
                                <option value="Recycling">Recycling</option>
                                <option value="Tree Planting">Tree Planting</option>
                                <option value="Waste Management">Waste Management</option>
                                <option value="Energy">Energy</option>
                                <option value="Water Conservation">Water Conservation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Progress (%)</label>
                            <input type="number" name="progress" min="0" max="100" value="0" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" required>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Project description and objectives..." required></textarea>
                    </div>
                    <button type="submit" class="btn-submit-action">Create Project 🌱</button>
                </form>
            </div>

            <div class="project-grid">
                <?php if ($projects_result && $projects_result->num_rows > 0): ?>
                    <?php while ($proj = $projects_result->fetch_assoc()): ?>
                        <?php
                            $cat = strtolower($proj["category"]);
                            $icon = "🌱";
                            if (strpos($cat, 'tree') !== false || strpos($cat, 'garden') !== false) $icon = "🌳";
                            elseif (strpos($cat, 'recycle') !== false || strpos($cat, 'waste') !== false) $icon = "♻️";
                            elseif (strpos($cat, 'energy') !== false || strpos($cat, 'solar') !== false) $icon = "⚡";
                            elseif (strpos($cat, 'water') !== false) $icon = "💧";
                        ?>
                        <div class="project">
                            <div class="project-image"><?php echo $icon; ?></div>
                            <div class="project-body">
                                <h2><?php echo htmlspecialchars($proj["title"]); ?></h2>
                                <span class="category-tag"><?php echo htmlspecialchars($proj["category"]); ?></span>
                                <p><?php echo htmlspecialchars($proj["description"]); ?></p>

                                <div class="progress-label">
                                    <span>Progress</span>
                                    <span><?php echo intval($proj["progress"]); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div style="width: <?php echo min(100, max(0, intval($proj["progress"]))); ?>%"></div>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                                    <span class="status <?php echo strtolower($proj["status"]); ?>">
                                        <?php echo htmlspecialchars($proj["status"]); ?>
                                    </span>
                                    <a href="admin_dashboard.php?action=delete_project&id=<?php echo $proj['id']; ?>" class="btn-delete" onclick="return confirm('Delete this project?');">Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No projects added yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 4: CAMPAIGNS -->
        <section id="campaigns" class="page">
            <div class="page-head">
                <div>
                    <h1>📢 Volunteer Campaigns Management</h1>
                    <p class="subtitle">Create campaigns and manage volunteer capacity.</p>
                </div>
            </div>

            <!-- ADD CAMPAIGN FORM -->
            <div class="form-card">
                <h3>+ Create New Volunteer Campaign</h3>
                <form method="POST" action="admin_dashboard.php">
                    <input type="hidden" name="action" value="create_campaign">
                    <div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Campaign Title</label>
                            <input type="text" name="title" placeholder="e.g., Campus Lake Clean-up" required>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" placeholder="e.g., University Lake" required>
                        </div>
                        <div class="form-group">
                            <label>Campaign Date</label>
                            <input type="date" name="campaign_date" required>
                        </div>
                        <div class="form-group">
                            <label>Max Volunteers</label>
                            <input type="number" name="max_volunteers" min="1" placeholder="e.g., 50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe the volunteer campaign activities..." required></textarea>
                    </div>
                    <button type="submit" class="btn-submit-action">Create Campaign 🌱</button>
                </form>
            </div>

            <div class="campaigns">
                <?php if ($campaigns_result && $campaigns_result->num_rows > 0): ?>
                    <?php while ($camp = $campaigns_result->fetch_assoc()): ?>
                        <?php
                            $camp_time = strtotime($camp["campaign_date"]);
                            $day = date("d", $camp_time);
                            $mon = strtoupper(date("M", $camp_time));
                        ?>
                        <div class="campaign">
                            <div class="date">
                                <b><?php echo $day; ?></b>
                                <span><?php echo $mon; ?></span>
                            </div>

                            <div class="campaign-info">
                                <h2><?php echo htmlspecialchars($camp["title"]); ?></h2>
                                <p><?php echo htmlspecialchars($camp["description"]); ?></p>
                                <p>📍 <strong>Location:</strong> <?php echo htmlspecialchars($camp["location"]); ?></p>
                                <p>👥 <strong>Volunteers:</strong> <?php echo intval($camp["registered_volunteers"]); ?> / <?php echo intval($camp["max_volunteers"]); ?></p>
                            </div>

                            <div>
                                <a href="admin_dashboard.php?action=delete_campaign&id=<?php echo $camp['id']; ?>" class="btn-delete" onclick="return confirm('Delete this campaign and its registrations?');">Delete Campaign</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No campaigns created yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 5: STUDENTS -->
        <section id="students" class="page">
            <div class="page-head">
                <div>
                    <h1>🎓 Registered Students</h1>
                    <p class="subtitle">View all registered GreenCampus student accounts.</p>
                </div>
            </div>

            <div class="table-box">
                <?php if ($students_result && $students_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($st = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $st["id"]; ?></td>
                                    <td><strong><?php echo htmlspecialchars($st["name"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($st["email"]); ?></td>
                                    <td><?php echo htmlspecialchars(date("d M Y, H:i", strtotime($st["created_at"]))); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No registered students found.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 6: REGISTRATIONS -->
        <section id="registrations" class="page">
            <div class="page-head">
                <div>
                    <h1>🤝 Campaign Volunteer Registrations</h1>
                    <p class="subtitle">List of students signed up for campaigns.</p>
                </div>
            </div>

            <div class="table-box">
                <?php if ($registrations_result && $registrations_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Reg ID</th>
                                <th>Student Name</th>
                                <th>Student Email</th>
                                <th>Campaign</th>
                                <th>Campaign Date & Location</th>
                                <th>Registered At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($reg = $registrations_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $reg["reg_id"]; ?></td>
                                    <td><strong><?php echo htmlspecialchars($reg["student_name"]); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg["student_email"]); ?></td>
                                    <td><strong><?php echo htmlspecialchars($reg["campaign_title"]); ?></strong></td>
                                    <td>📅 <?php echo htmlspecialchars(date("d M Y", strtotime($reg["campaign_date"]))); ?> (📍 <?php echo htmlspecialchars($reg["campaign_location"]); ?>)</td>
                                    <td><?php echo htmlspecialchars(date("d M Y, H:i", strtotime($reg["registered_at"]))); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No campaign registrations yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 7: REPORTS -->
        <section id="reports" class="page">
            <div class="page-head">
                <div>
                    <h1>📊 Impact Reports</h1>
                    <p class="subtitle">Summary of GreenCampus achievements and participation metrics.</p>
                </div>
                <button onclick="downloadReport()">Print / Download Report</button>
            </div>

            <div class="report-grid">
                <div class="report">
                    <span>🌳</span>
                    <h2><?php echo $total_campaigns * 25 + 50; ?></h2>
                    <p>Trees Planted</p>
                </div>

                <div class="report">
                    <span>♻️</span>
                    <h2><?php echo ($total_projects + 2) * 120; ?> KG</h2>
                    <p>Waste Recycled</p>
                </div>

                <div class="report">
                    <span>🤝</span>
                    <h2><?php echo $total_registrations; ?></h2>
                    <p>Volunteer Registrations</p>
                </div>

                <div class="report">
                    <span>💡</span>
                    <h2><?php echo $ideas_result->num_rows; ?></h2>
                    <p>Ideas Submitted</p>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer>
            © 2026 GreenCampus | Making our campus greener 🌱
        </footer>

    </main>

    <script src="script.js"></script>
</body>
</html>