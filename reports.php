<?php
$conn = new mysqli('localhost', 'root', '', 'logbook_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$reports = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];

    $sql = "SELECT * FROM logs WHERE date BETWEEN '$start_date' AND '$end_date' ORDER BY date ASC";
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Compliance Reports - Daily Work Logbook System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header><h1>Compliance Reports</h1></header>

<section class="report-section">
    <form method="GET">
        <label>Start Date:</label>
        <input type="date" name="start_date" required>
        
        <label>End Date:</label>
        <input type="date" name="end_date" required>

        <button type="submit">Generate Report</button>
    </form>

    <div>
        <?php if (!empty($reports)): ?>
            <h3>Report from <?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?></h3>
            <table border="1" cellpadding="5" cellspacing="0">
                <tr>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Equipment Status</th>
                    <th>Incidents</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['date']) ?></td>
                        <td><?= htmlspecialchars($report['shift']) ?></td>
                        <td><?= htmlspecialchars($report['equipment_status']) ?></td>
                        <td><?= htmlspecialchars($report['incidents']) ?></td>
                        <td><?= htmlspecialchars($report['notes']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php elseif (isset($_GET['start_date']) && isset($_GET['end_date'])): ?>
            <p>No logs found for the selected date range.</p>
        <?php endif; ?>
    </div>
</section>
</body>
</html>
