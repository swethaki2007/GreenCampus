<?php
session_start();
require_once "db.php";

$is_logged_in = isset($_SESSION["user_id"]);
$user_role = $_SESSION["role"] ?? "";
$dashboard_link = ($user_role === "admin") ? "admin_dashboard.php" : "dashboard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About GreenCampus | Smart Campus Sustainability</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4faf6;
            color: #1f2937;
            line-height: 1.6;
        }
        /* Top Navigation */
        .site-nav {
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 16px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .site-logo {
            font-size: 22px;
            font-weight: 800;
            color: #166534;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
            list-style: none;
        }
        .nav-links a {
            text-decoration: none;
            color: #4b5563;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            color: #16a34a;
        }
        .nav-btn {
            background: #16a34a;
            color: white !important;
            padding: 9px 20px;
            border-radius: 25px;
            font-weight: 600 !important;
            transition: background 0.3s;
        }
        .nav-btn:hover {
            background: #15803d;
        }

        /* Hero Banner */
        .about-hero {
            background: linear-gradient(135deg, #052e16 0%, #14532d 50%, #166534 100%);
            color: white;
            text-align: center;
            padding: 80px 20px 70px;
            position: relative;
            overflow: hidden;
        }
        .about-hero h1 {
            font-size: clamp(32px, 5vw, 52px);
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
        }
        .about-hero h1 span {
            color: #86efac;
        }
        .about-hero p {
            max-width: 700px;
            margin: 0 auto;
            font-size: 17px;
            color: #dcfce7;
            font-weight: 300;
        }

        /* Content Container */
        .container {
            max-width: 1150px;
            margin: 50px auto;
            padding: 0 25px;
        }

        /* Section Headings */
        .section-title-center {
            text-align: center;
            margin-bottom: 45px;
        }
        .section-title-center h2 {
            font-size: 32px;
            color: #14532d;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .section-title-center p {
            color: #6b7280;
            font-size: 16px;
        }

        /* Mission & Vision Grid */
        .mv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        .mv-card {
            background: white;
            padding: 40px 30px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-top: 5px solid #16a34a;
            transition: transform 0.3s;
        }
        .mv-card:hover {
            transform: translateY(-6px);
        }
        .mv-icon {
            font-size: 42px;
            margin-bottom: 18px;
            display: inline-block;
        }
        .mv-card h3 {
            font-size: 24px;
            color: #14532d;
            margin-bottom: 12px;
        }
        .mv-card p {
            color: #4b5563;
            font-size: 15px;
            line-height: 1.7;
        }

        /* What Students Can Do Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }
        .action-card {
            background: white;
            padding: 30px 25px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.04);
            border: 1px solid #e5e7eb;
            text-align: center;
            transition: all 0.3s;
        }
        .action-card:hover {
            border-color: #86efac;
            box-shadow: 0 12px 30px rgba(22,163,74,0.12);
            transform: translateY(-5px);
        }
        .action-icon {
            font-size: 38px;
            width: 70px;
            height: 70px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        .action-card h4 {
            font-size: 18px;
            color: #166534;
            margin-bottom: 10px;
        }
        .action-card p {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Why GreenCampus Matters */
        .why-box {
            background: white;
            border-radius: 20px;
            padding: 45px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            margin-bottom: 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }
        .why-text h2 {
            font-size: 30px;
            color: #14532d;
            margin-bottom: 15px;
            line-height: 1.3;
        }
        .why-text p {
            color: #4b5563;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .why-points {
            list-style: none;
            margin-top: 20px;
        }
        .why-points li {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 15px;
            color: #374151;
        }
        .why-points span {
            color: #16a34a;
            font-weight: bold;
            font-size: 18px;
        }
        .why-stats {
            background: linear-gradient(135deg, #dcfce7, #f0fdf4);
            padding: 35px;
            border-radius: 16px;
            border: 1px solid #bbf7d0;
            text-align: center;
        }
        .why-stats h3 {
            color: #14532d;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .stat-item {
            margin-bottom: 20px;
        }
        .stat-item b {
            font-size: 32px;
            color: #166534;
            display: block;
        }
        .stat-item span {
            color: #4b5563;
            font-size: 14px;
        }

        /* Call To Action */
        .cta-banner {
            background: linear-gradient(135deg, #14532d, #16a34a);
            color: white;
            text-align: center;
            padding: 60px 30px;
            border-radius: 20px;
            margin-bottom: 70px;
            box-shadow: 0 15px 35px rgba(20,83,45,0.2);
        }
        .cta-banner h2 {
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cta-banner p {
            font-size: 17px;
            color: #dcfce7;
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .cta-btn {
            background: white;
            color: #14532d;
            padding: 14px 35px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .cta-btn:hover {
            transform: translateY(-3px) scale(1.03);
            background: #f0fdf4;
        }

        /* Footer */
        .site-footer {
            background: #052e16;
            color: #9ca3af;
            text-align: center;
            padding: 40px 20px;
            font-size: 14px;
        }
        .site-footer a {
            color: #86efac;
            text-decoration: none;
        }

        @media (max-width: 850px) {
            .why-box {
                grid-template-columns: 1fr;
            }
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="site-nav">
        <a href="landing.html" class="site-logo">
            🌱 Green<span>Campus</span>
        </a>
        <ul class="nav-links">
            <li><a href="landing.html">Home</a></li>
            <li><a href="about.php" class="active">About</a></li>
            <li><a href="projects.php">Projects</a></li>
            <li><a href="campaigns.php">Volunteering</a></li>
            <?php if ($is_logged_in): ?>
                <li><a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="nav-btn">My Dashboard</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php" class="nav-btn">Join Us</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- HERO -->
    <section class="about-hero">
        <h1>Empowering Students to Build a <br><span>Greener Tomorrow</span> 🌱</h1>
        <p>GreenCampus is a smart campus beautification and environmental engagement system created to inspire real student-led sustainability.</p>
    </section>

    <!-- MAIN CONTENT -->
    <main class="container">

        <!-- MISSION & VISION -->
        <div class="mv-grid">
            <div class="mv-card">
                <span class="mv-icon">🎯</span>
                <h3>Our Mission</h3>
                <p>To engage, inspire, and unite university students in impactful campus improvement initiatives, tree-planting activities, recycling programs, and collaborative sustainability projects that leave a lasting positive mark.</p>
            </div>
            <div class="mv-card">
                <span class="mv-icon">🌏</span>
                <h3>Our Vision</h3>
                <p>To cultivate an environmentally conscious academic community where sustainability is a shared daily habit, transforming university grounds into vibrant, clean, and eco-friendly spaces for everyone.</p>
            </div>
        </div>

        <!-- WHAT STUDENTS CAN DO -->
        <div class="section-title-center">
            <h2>What Students Can Do</h2>
            <p>Simple actions lead to big environmental impacts. Here is how you can participate:</p>
        </div>

        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">🏗️</div>
                <h4>Join Green Projects</h4>
                <p>Collaborate on active campus sustainability projects including botanical gardens and renewable energy initiatives.</p>
            </div>

            <div class="action-card">
                <div class="action-icon">🤝</div>
                <h4>Volunteer for Campaigns</h4>
                <p>Sign up for clean-up drives, campus tree planting, and recycling awareness sessions with friends.</p>
            </div>

            <div class="action-card">
                <div class="action-icon">💡</div>
                <h4>Submit Improvement Ideas</h4>
                <p>Share innovative green ideas directly with university administration for review, approval, and funding.</p>
            </div>

            <div class="action-card">
                <div class="action-icon">📊</div>
                <h4>Track Real Impact</h4>
                <p>Monitor trees planted, kilograms of waste diverted, and your personal volunteer service hours.</p>
            </div>
        </div>

        <!-- WHY IT MATTERS -->
        <div class="why-box">
            <div class="why-text">
                <h2>Why GreenCampus Matters</h2>
                <p>University campuses generate thousands of kilograms of waste every month. GreenCampus bridges the gap between students and sustainability administrators to foster accountability and active participation.</p>
                <ul class="why-points">
                    <li><span>✔</span> Promotes eco-friendly habits across student housing and lecture halls</li>
                    <li><span>✔</span> Recognizes and values student-driven environmental contributions</li>
                    <li><span>✔</span> Enhances campus aesthetics, biodiversity, and clean air quality</li>
                    <li><span>✔</span> Builds teamwork, leadership, and community responsibility</li>
                </ul>
            </div>
            <div class="why-stats">
                <h3>🌱 Campus Collective Impact</h3>
                <div class="stat-item">
                    <b>500+</b>
                    <span>Trees & Plants Planted</span>
                </div>
                <div class="stat-item">
                    <b>1,200+ KG</b>
                    <span>Plastic & Paper Recycled</span>
                </div>
                <div class="stat-item">
                    <b>100%</b>
                    <span>Student Driven Initiatives</span>
                </div>
            </div>
        </div>

        <!-- CALL TO ACTION -->
        <div class="cta-banner">
            <h2>Your Campus. Your Ideas. Your Impact.</h2>
            <p>Be part of the green movement today. Register for GreenCampus and start making a tangible difference.</p>
            <?php if ($is_logged_in): ?>
                <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="cta-btn">Go to Dashboard →</a>
            <?php else: ?>
                <a href="register.php" class="cta-btn">Join GreenCampus Now 🌱</a>
            <?php endif; ?>
        </div>

    </main>

    <!-- FOOTER -->
    <footer class="site-footer">
        <p>© 2026 GreenCampus | Smart Campus Beautification & Engagement System</p>
        <p style="margin-top: 8px;">
            <a href="landing.html">Home</a> • 
            <a href="about.php">About</a> • 
            <a href="projects.php">Projects</a> • 
            <a href="campaigns.php">Volunteering</a> • 
            <a href="login.php">Login</a>
        </p>
    </footer>

</body>
</html>
