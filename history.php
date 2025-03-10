
<?php
$conn = new mysqli('localhost', 'root', '', 'logbook_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$logs = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['date'])) {
    $date = $_GET['date'];
    $sql = "SELECT * FROM logs WHERE date = '$date'";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Log History - Daily Work Logbook System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header><h1>Log History</h1></header>

<section class="history-section">
    <form method="GET">
        <input type="date" name="date" required>
        <button type="submit">Search Logs</button>
    </form>

    <div>
        <?php if (!empty($logs)): ?>
            <ul>
            <?php foreach ($logs as $log): ?>
                <li><?= $log['date'] ?> - <?= $log['shift'] ?> - <?= $log['equipment_status'] ?></li>
            <?php endforeach; ?>
            </ul>
        <?php elseif (isset($_GET['date'])): ?>
            <p>No logs found for this date.</p>
        <?php endif; ?>
    </div>
</section>
</body>
</html>
    