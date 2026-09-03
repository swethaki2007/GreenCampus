<?php
session_start();
require_once "db.php";

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Redirect admin to admin dashboard
if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
    header("Location: admin_dashboard.php");
    exit();
}

$student_name = $_SESSION["name"] ?? "Student";
$student_email = $_SESSION["email"] ?? "";
$student_role = $_SESSION["role"] ?? "student";

// Resolve student_id accurately from students table
$student_id = $_SESSION["student_id"] ?? 0;
if ($student_id === 0 && !empty($student_email)) {
    $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->bind_param("s", $student_email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $student_id = $row["id"];
        $_SESSION["student_id"] = $student_id;
    }
    $stmt->close();
}

$alert_message = "";
$alert_type = "success";

// Handle Idea Submission directly from Dashboard
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "submit_idea") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");

    if (empty($title) || empty($description) || empty($category)) {
        $alert_message = "Please fill in all fields to submit your idea.";
        $alert_type = "error";
    } elseif ($student_id <= 0) {
        $alert_message = "Student profile not found. Please re-login.";
        $alert_type = "error";
    } else {
        $status = "pending";
        $stmt = $conn->prepare("INSERT INTO ideas (student_id, title, description, category, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $student_id, $title, $description, $category, $status);

        if ($stmt->execute()) {
            $alert_message = "Your idea has been submitted successfully! 🌱 (Status: Pending Review)";
            $alert_type = "success";
        } else {
            $alert_message = "Failed to submit idea: " . $stmt->error;
            $alert_type = "error";
        }
        $stmt->close();
    }
}

// Handle Join Campaign directly from Dashboard
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "join_campaign" && isset($_POST["campaign_id"])) {
    $camp_id = intval($_POST["campaign_id"]);

    if ($student_id <= 0) {
        $alert_message = "Student record not found. Please re-login.";
        $alert_type = "error";
    } else {
        // Check if already registered
        $chk = $conn->prepare("SELECT id FROM registrations WHERE student_id = ? AND campaign_id = ?");
        $chk->bind_param("ii", $student_id, $camp_id);
        $chk->execute();
        $chk_res = $chk->get_result();

        if ($chk_res->num_rows > 0) {
            $alert_message = "You are already registered for this campaign.";
            $alert_type = "error";
        } else {
            // Check volunteer quota
            $cnt_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM registrations WHERE campaign_id = ?");
            $cnt_stmt->bind_param("i", $camp_id);
            $cnt_stmt->execute();
            $cnt_data = $cnt_stmt->get_result()->fetch_assoc();
            $current_regs = intval($cnt_data["total"] ?? 0);
            $cnt_stmt->close();

            $max_stmt = $conn->prepare("SELECT max_volunteers FROM campaigns WHERE id = ?");
            $max_stmt->bind_param("i", $camp_id);
            $max_stmt->execute();
            $max_data = $max_stmt->get_result()->fetch_assoc();
            $max_vols = intval($max_data["max_volunteers"] ?? 0);
            $max_stmt->close();

            if ($max_vols > 0 && $current_regs >= $max_vols) {
                $alert_message = "Sorry, this campaign has reached maximum volunteer capacity.";
                $alert_type = "error";
            } else {
                $ins = $conn->prepare("INSERT INTO registrations (student_id, campaign_id) VALUES (?, ?)");
                $ins->bind_param("ii", $student_id, $camp_id);
                if ($ins->execute()) {
                    $alert_message = "Successfully registered for the campaign! 🌱";
                    $alert_type = "success";
                } else {
                    $alert_message = "Registration failed: " . $ins->error;
                    $alert_type = "error";
                }
                $ins->close();
            }
        }
        $chk->close();
    }
}

// 1. Fetch Dynamic Stats
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()["count"] ?? 0;
$active_projects = $conn->query("SELECT COUNT(*) AS count FROM projects WHERE status = 'active' OR status = 'Active'")->fetch_assoc()["count"] ?? 0;
$completed_projects = $conn->query("SELECT COUNT(*) AS count FROM projects WHERE status = 'completed' OR status = 'Completed'")->fetch_assoc()["count"] ?? 0;
$total_campaigns = $conn->query("SELECT COUNT(*) AS count FROM campaigns")->fetch_assoc()["count"] ?? 0;

$my_ideas_count = 0;
if ($student_id > 0) {
    $my_ideas_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM ideas WHERE student_id = ?");
    $my_ideas_stmt->bind_param("i", $student_id);
    $my_ideas_stmt->execute();
    $my_ideas_count = $my_ideas_stmt->get_result()->fetch_assoc()["count"] ?? 0;
    $my_ideas_stmt->close();
}

$my_regs_count = 0;
if ($student_id > 0) {
    $my_reg_stmt = $conn->prepare("SELECT COUNT(*) AS count FROM registrations WHERE student_id = ?");
    $my_reg_stmt->bind_param("i", $student_id);
    $my_reg_stmt->execute();
    $my_regs_count = $my_reg_stmt->get_result()->fetch_assoc()["count"] ?? 0;
    $my_reg_stmt->close();
}

// 2. Fetch Featured Project
$featured_proj = $conn->query("SELECT * FROM projects ORDER BY progress DESC, created_at DESC LIMIT 1")->fetch_assoc();

// 3. Fetch Upcoming Campaigns
$camp_sql = "
    SELECT
        c.*,
        (SELECT COUNT(*) FROM registrations WHERE campaign_id = c.id) AS registered_count,
        (SELECT COUNT(*) FROM registrations WHERE campaign_id = c.id AND student_id = ?) AS is_user_registered
    FROM campaigns c
    ORDER BY c.campaign_date ASC
";
$camp_stmt = $conn->prepare($camp_sql);
$camp_stmt->bind_param("i", $student_id);
$camp_stmt->execute();
$campaigns_result = $camp_stmt->get_result();

// 4. Fetch All Projects
$projects_result = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");

// 5. Fetch Ideas
$ideas_sql = "
    SELECT ideas.*, students.name AS student_name
    FROM ideas
    JOIN students ON ideas.student_id = students.id
    ORDER BY ideas.created_at DESC
";
$ideas_result = $conn->query($ideas_sql);

// 6. Fetch Student's Registered Campaigns
$my_campaigns_sql = "
    SELECT r.id AS reg_id, r.registered_at, c.title, c.location, c.campaign_date, c.description
    FROM registrations r
    JOIN campaigns c ON r.campaign_id = c.id
    WHERE r.student_id = ?
    ORDER BY r.registered_at DESC
";
$my_camps_stmt = $conn->prepare($my_campaigns_sql);
$my_camps_stmt->bind_param("i", $student_id);
$my_camps_stmt->execute();
$my_campaigns_result = $my_camps_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenCampus | Student Dashboard</title>
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
        .badge-registered {
            background: #dcfce7;
            color: #15803d;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }
        .badge-full {
            background: #e5e7eb;
            color: #6b7280;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }
        .category-tag {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }
        .form-card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
        }
        .form-card h3 {
            margin-top: 0;
            color: #14532d;
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
            color: #374151;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group textarea {
            height: 90px;
            resize: vertical;
        }
        .btn-submit-idea {
            background: #16a34a;
            color: white;
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-submit-idea:hover {
            background: #15803d;
        }
        /* Featured & Announcement Boxes */
        .dashboard-grid-2 {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
            margin-top: 30px;
        }
        .widget-box {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
        }
        .widget-box h3 {
            color: #14532d;
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tip-item {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #4b5563;
        }
        .tip-icon {
            font-size: 20px;
        }
        .announcement-box {
            background: linear-gradient(135deg, #dcfce7, #f0fdf4);
            border: 1px solid #bbf7d0;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .announcement-box b {
            color: #14532d;
            display: block;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .announcement-box p {
            margin: 0;
            color: #374151;
            font-size: 13px;
        }
        @media (max-width: 900px) {
            .dashboard-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar" id="sidebar">
        <h2 class="logo"><a href="dashboard.php" style="color:white; text-decoration:none;">🌱 GreenCampus</a></h2>

        <p class="menu-title">MAIN</p>
        <div class="menu-section">
            <button class="menu-item active" onclick="showPage('dashboard', this)">
                <span>🏠</span> Dashboard
            </button>
            <button class="menu-item" onclick="window.location.href='about.php'">
                <span>📖</span> About GreenCampus
            </button>
            <button class="menu-item" onclick="showPage('reports', this)">
                <span>📊</span> Impact Reports
            </button>
        </div>

        <p class="menu-title">ACTIVITIES</p>
        <div class="menu-section">
            <button class="menu-item" onclick="showPage('projects', this)">
                <span>🏗️</span> Projects
            </button>
            <button class="menu-item" onclick="showPage('campaigns', this)">
                <span>🤝</span> Volunteer
            </button>
            <button class="menu-item" onclick="showPage('ideas', this)">
                <span>💡</span> Submit an Idea
            </button>
            <button class="menu-item" onclick="window.location.href='my_activities.php'">
                <span>📋</span> My Activities
            </button>
        </div>

        <p class="menu-title">ACCOUNT</p>
        <div class="menu-section">
            <button class="menu-item" onclick="window.location.href='profile.php'">
                <span>👤</span> Profile
            </button>
        </div>

        <button class="menu-item logout" onclick="logout()">
            <span>🚪</span> Logout
        </button>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <!-- HEADER -->
        <header class="topbar">
            <button class="mobile-btn" onclick="toggleSidebar()">☰</button>
            <div>
                <h2>Welcome, <?php echo htmlspecialchars($student_name); ?>! 🌱</h2>
                <p>GreenCampus Student Portal | Let's build a greener university together.</p>
            </div>

            <div class="profile" style="cursor: pointer;" onclick="window.location.href='profile.php'">
                <div class="avatar">
                    <?php echo strtoupper(substr($student_name, 0, 1)); ?>
                </div>
                <div>
                    <b><?php echo htmlspecialchars($student_name); ?></b>
                    <small>Student • Profile ⚙️</small>
                </div>
            </div>
        </header>

        <?php if (!empty($alert_message)): ?>
            <div class="alert-banner <?php echo htmlspecialchars($alert_type); ?>">
                <?php echo htmlspecialchars($alert_message); ?>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: DASHBOARD -->
        <section id="dashboard" class="page active">
            <h1>Dashboard Overview</h1>
            <p class="subtitle">Real-time status of your participation and campus green projects.</p>

            <!-- 5 SUMMARY CARDS -->
            <div class="stats">
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
                        <p>Available Volunteer Drives</p>
                    </div>
                </div>

                <div class="stat">
                    <span>🤝</span>
                    <div>
                        <h3><?php echo $my_regs_count; ?></h3>
                        <p>My Joined Activities</p>
                    </div>
                </div>

                <div class="stat">
                    <span>💡</span>
                    <div>
                        <h3><?php echo $my_ideas_count; ?></h3>
                        <p>Ideas Submitted</p>
                    </div>
                </div>

                <div class="stat">
                    <span>🏆</span>
                    <div>
                        <h3><?php echo $completed_projects; ?></h3>
                        <p>Completed Activities</p>
                    </div>
                </div>
            </div>

            <!-- FEATURED PROJECT & ANNOUNCEMENTS / TIPS GRID -->
            <div class="dashboard-grid-2">
                
                <!-- FEATURED PROJECT -->
                <div class="widget-box">
                    <h3>🌟 Featured GreenCampus Project</h3>
                    <?php if ($featured_proj): ?>
                        <div style="background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px solid #f3f4f6;">
                            <span class="category-tag"><?php echo htmlspecialchars($featured_proj["category"]); ?></span>
                            <h2 style="color: #14532d; font-size: 20px; margin: 10px 0 8px;"><?php echo htmlspecialchars($featured_proj["title"]); ?></h2>
                            <p style="color: #4b5563; font-size: 14px; margin-bottom: 16px;"><?php echo htmlspecialchars($featured_proj["description"]); ?></p>
                            
                            <div class="progress-label" style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">
                                <span>Overall Project Progress</span>
                                <span><?php echo intval($featured_proj["progress"]); ?>%</span>
                            </div>
                            <div class="progress-bar" style="height: 10px; background: #e5e7eb; border-radius: 10px; overflow: hidden; margin-bottom: 15px;">
                                <div style="width: <?php echo min(100, max(0, intval($featured_proj["progress"]))); ?>%; height: 100%; background: #16a34a;"></div>
                            </div>
                            <button onclick="showPage('projects')" style="background: #16a34a; color:white; border:none; padding:8px 18px; border-radius:6px; font-weight:600; cursor:pointer;">Explore All Projects</button>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280;">No active projects available currently.</p>
                    <?php endif; ?>

                    <h3 style="margin-top: 25px;">📢 Recent Announcements</h3>
                    <div class="announcement-box">
                        <b>🌿 University Clean-Up & Tree Planting Month</b>
                        <p>Join fellow students this Saturday for campus beautification. Volunteer hours will be officially credited!</p>
                    </div>
                    <div class="announcement-box" style="background: #eff6ff; border-color: #bfdbfe;">
                        <b style="color: #1e40af;">💡 Green Idea Submissions Open</b>
                        <p>Have an innovation for plastic reduction or solar energy? Submit your idea for administrative review!</p>
                    </div>
                </div>

                <!-- ENVIRONMENTAL TIPS & PARTICIPATION -->
                <div class="widget-box">
                    <h3>🌱 Daily Environmental Tips</h3>
                    <div class="tip-item">
                        <span class="tip-icon">🚰</span>
                        <div>
                            <strong style="color: #14532d;">Use Refillable Water Bottles</strong>
                            <p style="margin: 3px 0 0;">Save up to 150 single-use plastic bottles per semester by utilizing campus hydration stations.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">💡</span>
                        <div>
                            <strong style="color: #14532d;">Switch Off Lights in Empty Rooms</strong>
                            <p style="margin: 3px 0 0;">Help reduce campus carbon footprint by powering down projectors and lights after study sessions.</p>
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📄</span>
                        <div>
                            <strong style="color: #14532d;">Go Digital with Notes</strong>
                            <p style="margin: 3px 0 0;">Utilize digital note-taking and double-sided printing to conserve forest biodiversity.</p>
                        </div>
                    </div>

                    <h3 style="margin-top: 25px;">⚡ Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button onclick="showPage('ideas')" style="background: #16a34a; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: left; padding-left: 15px;">
                            💡 Submit a Campus Idea →
                        </button>
                        <button onclick="showPage('campaigns')" style="background: #0f766e; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: left; padding-left: 15px;">
                            🤝 Join Volunteer Campaigns →
                        </button>
                        <button onclick="window.location.href='my_activities.php'" style="background: #374151; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; text-align: left; padding-left: 15px;">
                            📋 View My Activities History →
                        </button>
                    </div>
                </div>

            </div>
        </section>

        <!-- SECTION 2: IDEAS -->
        <section id="ideas" class="page">
            <div class="page-head">
                <div>
                    <h1>💡 Submit & Explore Campus Ideas</h1>
                    <p class="subtitle">Submit your green ideas and see suggestions from fellow students.</p>
                </div>
            </div>

            <!-- SUBMIT IDEA FORM -->
            <div class="form-card">
                <h3>🌱 Submit a New Campus Idea</h3>
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="action" value="submit_idea">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="idea_title">Idea Title</label>
                            <input type="text" id="idea_title" name="title" placeholder="e.g., More Recycling Bins in Science Building" required>
                        </div>
                        <div class="form-group">
                            <label for="idea_category">Category</label>
                            <select id="idea_category" name="category" required>
                                <option value="">Select a category</option>
                                <option value="Waste Management">Waste Management</option>
                                <option value="Recycling">Recycling</option>
                                <option value="Energy Saving">Energy Saving</option>
                                <option value="Water Conservation">Water Conservation</option>
                                <option value="Campus Gardening">Campus Gardening</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="idea_desc">Description</label>
                        <textarea id="idea_desc" name="description" placeholder="Describe your environmental idea and its benefits..." required></textarea>
                    </div>
                    <button type="submit" class="btn-submit-idea">Submit Idea 🌱</button>
                </form>
            </div>

            <!-- IDEAS TABLE -->
            <div class="table-box">
                <h3 style="padding: 20px 20px 0; margin: 0; color: #14532d;">Submitted Campus Ideas</h3>
                <?php if ($ideas_result && $ideas_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Idea Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($idea = $ideas_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($idea["student_name"]); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($idea["title"]); ?></strong><br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($idea["description"]); ?></small>
                                    </td>
                                    <td>
                                        <span class="category-tag"><?php echo htmlspecialchars($idea["category"]); ?></span>
                                    </td>
                                    <td>
                                        <span class="status <?php echo strtolower($idea["status"]); ?>">
                                            <?php echo htmlspecialchars($idea["status"]); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date("d M Y", strtotime($idea["created_at"]))); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No ideas submitted yet. Be the first to share one!</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 3: PROJECTS -->
        <section id="projects" class="page">
            <div class="page-head">
                <div>
                    <h1>🏗️ Campus Projects</h1>
                    <p class="subtitle">Ongoing environmental and sustainability initiatives across campus.</p>
                </div>
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

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                    <span class="status <?php echo strtolower($proj["status"]); ?>">
                                        <?php echo htmlspecialchars($proj["status"]); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No projects available yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 4: CAMPAIGNS -->
        <section id="campaigns" class="page">
            <div class="page-head">
                <div>
                    <h1>📢 Volunteer Campaigns</h1>
                    <p class="subtitle">Join upcoming volunteering activities and make a direct impact.</p>
                </div>
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
                                <p>👥 <strong>Volunteers:</strong> <?php echo intval($camp["registered_count"]); ?> / <?php echo intval($camp["max_volunteers"]); ?></p>
                            </div>

                            <div>
                                <?php if ($camp["is_user_registered"] > 0): ?>
                                    <span class="badge-registered">✅ Registered</span>
                                <?php elseif (intval($camp["registered_count"]) >= intval($camp["max_volunteers"])): ?>
                                    <span class="badge-full">🚫 Full</span>
                                <?php else: ?>
                                    <form method="POST" action="dashboard.php" style="display:inline;">
                                        <input type="hidden" name="action" value="join_campaign">
                                        <input type="hidden" name="campaign_id" value="<?php echo $camp["id"]; ?>">
                                        <button type="submit">Join Campaign 🌱</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="padding: 30px; text-align: center; color: #777;">No campaigns scheduled at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 5: REPORTS -->
        <section id="reports" class="page">
            <div class="page-head">
                <div>
                    <h1>📊 Impact Reports</h1>
                    <p class="subtitle">See the environmental impact made by the GreenCampus community.</p>
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
                    <h2><?php echo ($active_projects + 2) * 120; ?> KG</h2>
                    <p>Waste Recycled</p>
                </div>

                <div class="report">
                    <span>🤝</span>
                    <h2><?php echo $total_students * 2 + 10; ?></h2>
                    <p>Volunteer Service Hours</p>
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
<?php
$camp_stmt->close();
$my_camps_stmt->close();
?>