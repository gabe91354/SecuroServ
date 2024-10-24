<?php
session_start();
include 'db.php';

// Redirect to index.php if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle option submission
if (isset($_POST['submit_option'])) {
    $option_choice = $_POST['option'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, option_choice) VALUES (?, ?)");
    $stmt->execute([$user_id, $option_choice]);
}

// Handle edit action
if (isset($_POST['edit_action'])) {
    $log_id = $_POST['log_id'];
    $option_choice = $_POST['option_choice'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE user_logs SET option_choice = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$option_choice, $user_id, $log_id]);
}

// Handle delete action
if (isset($_POST['delete_action'])) {
    $log_id = $_POST['log_id'];

    $stmt = $conn->prepare("DELETE FROM user_logs WHERE id = ?");
    $stmt->execute([$log_id]);
}

// Pagination logic
$limit = 5; // Number of entries to show per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Get current page number
$offset = ($page - 1) * $limit; // Calculate offset for SQL query

// Fetch total number of logs
$total_logs_stmt = $conn->query("SELECT COUNT(*) FROM user_logs");
$total_logs = $total_logs_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit); // Total number of pages

// Fetch logs for the current page
$logs_stmt = $conn->prepare("
    SELECT ul.id, u.full_name, ul.option_choice, ul.timestamp, 
           (SELECT u2.full_name FROM users u2 WHERE u2.id = ul.updated_by) AS updated_by_name, 
           ul.updated_at 
    FROM user_logs ul 
    JOIN users u ON ul.user_id = u.id
    LIMIT :limit OFFSET :offset
");
$logs_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$logs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logs_stmt->execute();
$logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current user data for welcome message
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecuroServ - Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 600px;
        }
        h1, h2 {
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        input, select {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            padding: 10px;
            border: none;
            border-radius: 4px;
            background-color: #5cb85c;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background-color: #4cae4c;
        }
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }
        .pagination a.active {
            background-color: #5cb85c;
            color: white;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>

        <h2>Your Options</h2>
        <form method="POST">
            <select name="option" required>
                <option value="" disabled selected>Select an option</option>
                <option value="Acquire Special Cargo">Acquire Special Cargo</option>
                <option value="Transport Goods">Transport Goods</option>
                <option value="Defend Assets">Defend Assets</option>
                <option value="Manage Operations">Manage Operations</option>
            </select>
            <button type="submit" name="submit_option">Submit Option</button>
        </form>

        <h2>Logs</h2>
        <table>
            <tr>
                <th>Full Name</th>
                <th>Option Choice</th>
                <th>Updated By</th>
                <th>Timestamp</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="text" name="option_choice" value="<?php echo htmlspecialchars($log['option_choice']); ?>" required>
                        <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                        <button type="submit" name="edit_action" class="edit-btn">Edit</button>
                    </form>
                </td>
                <td><?php echo htmlspecialchars($log['updated_by_name']); ?></td>
                <td><?php echo htmlspecialchars($log['updated_at']); ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                        <button type="submit" name="delete_action" class="delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Pagination Links -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="home.php?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="home.php?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="home.php?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
        </div>

        <a href="logout.php">Logout</a>
    </div>

</body>
</html>
