<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure user is logged in
if (!isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();
$message = '';
$error = '';

// Fetch equipment list
$equipment_list = [];
try {
    $stmt = $pdo->query("SELECT id, name, type, status FROM equipment ORDER BY name");
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching equipment list: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Sanitize and validate inputs
        $date_time = date('Y-m-d H:i:s', strtotime($_POST['date_time']));
        $shift = filter_input(INPUT_POST, 'shift', FILTER_SANITIZE_STRING);
        $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
        $equipment_id = filter_input(INPUT_POST, 'equipment_id', FILTER_VALIDATE_INT);
        
        // Create JSON objects for parameters and readings
        $parameters = json_encode([
            'temperature' => $_POST['temp_reading'],
            'pressure' => $_POST['pressure_reading'],
            'flow_rate' => $_POST['flow_reading']
        ]);
        
        $readings = json_encode([
            'efficiency' => $_POST['efficiency'],
            'power_output' => $_POST['power_output']
        ]);

        // Handle file upload
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            $attachment_path = $upload_dir . uniqid() . '_' . basename($_FILES['attachment']['name']);
            move_uploaded_file($_FILES['attachment']['tmp_name'], $attachment_path);
        }

        // Insert log entry
        $stmt = $pdo->prepare("
            INSERT INTO logs (
                user_id, date_time, shift, category, equipment_id,
                parameters, readings, incidents, actions_taken,
                notes, attachments
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $user['id'],
            $date_time,
            $shift,
            $category,
            $equipment_id,
            $parameters,
            $readings,
            $_POST['incidents'],
            $_POST['actions_taken'],
            $_POST['notes'],
            $attachment_path
        ]);

        // If this is a compliance-related log, create compliance record
        if ($category === 'compliance') {
            $stmt = $pdo->prepare("
                INSERT INTO compliance_records (
                    log_id, regulation_code, compliance_status,
                    review_date, reviewed_by, comments
                ) VALUES (
                    ?, ?, 'pending_review', CURDATE(), ?, ?
                )
            ");
            $stmt->execute([
                $pdo->lastInsertId(),
                $_POST['regulation_code'],
                $user['id'],
                $_POST['compliance_notes']
            ]);
        }

        $pdo->commit();
        $message = "Log entry successfully saved!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving log: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Log Entry - Daily Work Logbook System</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <header>
        <h1>Log Entry Form</h1>
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 
            (<?php echo htmlspecialchars($user['role']); ?>)
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="form-section">
        <form method="POST" enctype="multipart/form-data" id="logForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="date_time">Date and Time:</label>
                    <input type="text" id="date_time" name="date_time" required>
                </div>

                <div class="form-group">
                    <label for="shift">Shift:</label>
                    <select name="shift" id="shift" required>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                        <option value="night">Night</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Entry Category:</label>
                    <select name="category" id="category" required>
                        <option value="routine">Routine Check</option>
                        <option value="incident">Incident Report</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="compliance">Compliance Check</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="equipment_id">Equipment:</label>
                    <select name="equipment_id" id="equipment_id" required>
                        <option value="">Select Equipment</option>
                        <?php foreach ($equipment_list as $equipment): ?>
                            <option value="<?php echo $equipment['id']; ?>">
                                <?php echo htmlspecialchars($equipment['name']); ?> 
                                (<?php echo htmlspecialchars($equipment['type']); ?>) - 
                                <?php echo htmlspecialchars($equipment['status']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="readings-section">
                    <h3>Equipment Readings</h3>
                    <div class="form-group">
                        <label for="temp_reading">Temperature (°C):</label>
                        <input type="number" step="0.1" name="temp_reading" id="temp_reading">
                    </div>
                    <div class="form-group">
                        <label for="pressure_reading">Pressure (PSI):</label>
                        <input type="number" step="0.1" name="pressure_reading" id="pressure_reading">
                    </div>
                    <div class="form-group">
                        <label for="flow_reading">Flow Rate (L/min):</label>
                        <input type="number" step="0.1" name="flow_reading" id="flow_reading">
                    </div>
                </div>

                <div class="performance-section">
                    <h3>Performance Metrics</h3>
                    <div class="form-group">
                        <label for="efficiency">Efficiency (%):</label>
                        <input type="number" step="0.1" name="efficiency" id="efficiency">
                    </div>
                    <div class="form-group">
                        <label for="power_output">Power Output (kW):</label>
                        <input type="number" step="0.1" name="power_output" id="power_output">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="incidents">Incidents or Observations:</label>
                    <textarea name="incidents" id="incidents" rows="4"></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="actions_taken">Actions Taken:</label>
                    <textarea name="actions_taken" id="actions_taken" rows="4"></textarea>
                </div>

                <div class="form-group full-width">
                    <label for="notes">Additional Notes:</label>
                    <textarea name="notes" id="notes" rows="4"></textarea>
                </div>

                <div id="compliance-section" style="display: none;">
                    <h3>Compliance Information</h3>
                    <div class="form-group">
                        <label for="regulation_code">Regulation Code:</label>
                        <input type="text" name="regulation_code" id="regulation_code">
                    </div>
                    <div class="form-group">
                        <label for="compliance_notes">Compliance Notes:</label>
                        <textarea name="compliance_notes" id="compliance_notes" rows="4"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="attachment">Attach Files:</label>
                    <input type="file" name="attachment" id="attachment">
                    <small>Supported formats: PDF, JPG, PNG (max 5MB)</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Submit Log</button>
                <button type="reset" class="btn-secondary">Clear Form</button>
            </div>
        </form>
    </section>

    <script>
        // Initialize datetime picker
        flatpickr("#date_time", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            defaultDate: new Date()
        });

        // Show/hide compliance section based on category
        document.getElementById('category').addEventListener('change', function() {
            const complianceSection = document.getElementById('compliance-section');
            complianceSection.style.display = this.value === 'compliance' ? 'block' : 'none';
        });

        // Form validation
        document.getElementById('logForm').addEventListener('submit', function(e) {
            const category = document.getElementById('category').value;
            if (category === 'compliance') {
                const regulationCode = document.getElementById('regulation_code').value;
                if (!regulationCode) {
                    e.preventDefault();
                    alert('Please enter a regulation code for compliance entries.');
                }
            }
        });
    </script>
</body>
</html>
    