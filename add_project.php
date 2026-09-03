<?php

session_start();
require_once "db.php";

// Only admins can add projects
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $category = trim($_POST["category"]);
    $progress = intval($_POST["progress"]);
    $status = $_POST["status"];

    if (
        empty($title) ||
        empty($description) ||
        empty($category)
    ) {

        $message = "Please fill in all fields.";

    } elseif ($progress < 0 || $progress > 100) {

        $message = "Progress must be between 0 and 100.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO projects
            (title, description, category, progress, status)
            VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssis",
            $title,
            $description,
            $category,
            $progress,
            $status
        );

        if ($stmt->execute()) {

            $message = "Project created successfully! 🌱";

        } else {

            $message = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Add Project - GreenCampus</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f8f2;
}

.navbar {
    background: #1b5e20;
    color: white;

    padding: 18px 40px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar h2 {
    margin: 0;
}

.navbar a {
    color: white;
    text-decoration: none;

    background: #c62828;

    padding: 9px 16px;

    border-radius: 6px;
}

.container {
    width: 90%;
    max-width: 650px;

    margin: 40px auto;
}

.form-box {
    background: white;

    padding: 30px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.1);
}

h1 {
    color: #2e7d32;
    text-align: center;
}

label {
    display: block;

    margin-top: 18px;

    font-weight: bold;
}

input,
textarea,
select {
    width: 100%;

    padding: 12px;

    margin-top: 7px;

    border: 1px solid #ccc;

    border-radius: 6px;

    box-sizing: border-box;

    font-size: 15px;
}

textarea {
    height: 140px;

    resize: vertical;
}

button {
    width: 100%;

    padding: 13px;

    margin-top: 25px;

    background: #2e7d32;

    color: white;

    border: none;

    border-radius: 6px;

    font-size: 16px;

    cursor: pointer;
}

button:hover {
    background: #1b5e20;
}

.message {
    text-align: center;

    padding: 12px;

    margin-bottom: 15px;

    color: #2e7d32;

    font-weight: bold;
}

.back {
    display: block;

    text-align: center;

    margin-top: 20px;

    color: #2e7d32;

    text-decoration: none;
}

</style>

</head>

<body>

<nav class="navbar">

    <h2>🌱 GreenCampus Admin</h2>

    <a href="logout.php">Logout</a>

</nav>

<div class="container">

<div class="form-box">

<h1>🌱 Create New Project</h1>

<?php if (!empty($message)): ?>

<div class="message">
    <?php echo htmlspecialchars($message); ?>
</div>

<?php endif; ?>

<form method="POST">

<label>Project Title</label>

<input
    type="text"
    name="title"
    placeholder="Enter project title"
    required
>

<label>Description</label>

<textarea
    name="description"
    placeholder="Describe the project..."
    required
></textarea>

<label>Category</label>

<select name="category" required>

<option value="">
    Select a category
</option>

<option value="Recycling">
    Recycling
</option>

<option value="Tree Planting">
    Tree Planting
</option>

<option value="Waste Management">
    Waste Management
</option>

<option value="Energy">
    Energy
</option>

<option value="Water Conservation">
    Water Conservation
</option>

<option value="Other">
    Other
</option>

</select>

<label>Progress (%)</label>

<input
    type="number"
    name="progress"
    min="0"
    max="100"
    value="0"
    required
>

<label>Status</label>

<select name="status" required>

<option value="active">
    Active
</option>

<option value="completed">
    Completed
</option>

<option value="pending">
    Pending
</option>

</select>

<button type="submit">
    Create Project 🌱
</button>

</form>

<a class="back" href="admin_dashboard.php">
    ← Back to Admin Dashboard
</a>

</div>

</div>

</body>

</html>