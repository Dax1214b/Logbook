<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Ensure user is logged in
if (!isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$user = getCurrentUser();

// Fetch equipment performance data for the last 30 days
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date_time) as log_date,
            equipment_id,
            parameters,
            readings
        FROM logs 
        WHERE date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY date_time
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();

    // Process data for charts
    $dates = [];
    $temperatures = [];
    $pressures = [];
    $efficiencies = [];
    $powerOutputs = [];

    foreach ($logs as $log) {
        $params = json_decode($log['parameters'], true);
        $readings = json_decode($log['readings'], true);
        
        $dates[] = $log['log_date'];
        $temperatures[] = $params['temperature'] ?? null;
        $pressures[] = $params['pressure'] ?? null;
        $efficiencies[] = $readings['efficiency'] ?? null;
        $powerOutputs[] = $readings['power_output'] ?? null;
    }

    // Fetch equipment status distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM equipment
        GROUP BY status
    ");
    $stmt->execute();
    $equipmentStatus = $stmt->fetchAll();

    // Fetch incident categories for the last 30 days
    $stmt = $pdo->prepare("
        SELECT category, COUNT(*) as count
        FROM logs
        WHERE date_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY category
    ");
    $stmt->execute();
    $incidentCategories = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - Daily Work Logbook System</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div class="header-left">
                <h1><i class="bi bi-graph-up"></i> Operations Dashboard</h1>
                <div class="user-info">
                    <i class="bi bi-person-circle"></i>
                    Welcome, <?php echo htmlspecialchars($user['full_name']); ?> 
                    <span class="role-badge"><?php echo htmlspecialchars($user['role']); ?></span>
                </div>
            </div>
            <nav>
                <a href="logs.php"><i class="bi bi-journal-plus"></i> Log Entry</a>
                <a href="history.php"><i class="bi bi-clock-history"></i> Log History</a>
                <a href="reports.php"><i class="bi bi-file-earmark-text"></i> Compliance Reports</a>
                <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            </nav>
        </div>
    </header>

    <div class="dashboard-container">
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <i class="bi bi-gear-fill"></i>
                <div class="summary-info">
                    <h3>Total Equipment</h3>
                    <p class="summary-value"><?php echo array_sum(array_column($equipmentStatus, 'count')); ?></p>
                </div>
            </div>
            <div class="summary-card">
                <i class="bi bi-check-circle-fill"></i>
                <div class="summary-info">
                    <h3>Operational</h3>
                    <p class="summary-value"><?php 
                        echo array_reduce($equipmentStatus, function($carry, $item) {
                            return $carry + ($item['status'] === 'operational' ? $item['count'] : 0);
                        }, 0);
                    ?></p>
                </div>
            </div>
            <div class="summary-card">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div class="summary-info">
                    <h3>Maintenance</h3>
                    <p class="summary-value"><?php 
                        echo array_reduce($equipmentStatus, function($carry, $item) {
                            return $carry + ($item['status'] === 'maintenance' ? $item['count'] : 0);
                        }, 0);
                    ?></p>
                </div>
            </div>
            <div class="summary-card">
                <i class="bi bi-x-circle-fill"></i>
                <div class="summary-info">
                    <h3>Offline</h3>
                    <p class="summary-value"><?php 
                        echo array_reduce($equipmentStatus, function($carry, $item) {
                            return $carry + ($item['status'] === 'offline' ? $item['count'] : 0);
                        }, 0);
                    ?></p>
                </div>
            </div>
        </div>

        <!-- Main Charts -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2><i class="bi bi-pie-chart-fill"></i> Equipment Status Overview</h2>
                <div class="card-actions">
                    <button class="btn-icon" onclick="refreshChart('equipmentStatusChart')">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            <canvas id="equipmentStatusChart"></canvas>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h2><i class="bi bi-thermometer-half"></i> Temperature Trends</h2>
                <div class="card-actions">
                    <select class="time-range" onchange="updateTimeRange('temperatureChart', this.value)">
                        <option value="7">Last 7 Days</option>
                        <option value="14">Last 14 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                    </select>
                </div>
            </div>
            <canvas id="temperatureChart"></canvas>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h2><i class="bi bi-speedometer"></i> Pressure Trends</h2>
                <div class="card-actions">
                    <select class="time-range" onchange="updateTimeRange('pressureChart', this.value)">
                        <option value="7">Last 7 Days</option>
                        <option value="14">Last 14 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                    </select>
                </div>
            </div>
            <canvas id="pressureChart"></canvas>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h2><i class="bi bi-lightning-charge"></i> Equipment Performance</h2>
                <div class="card-actions">
                    <button class="btn-icon" onclick="toggleMetric('performanceChart')">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
            </div>
            <canvas id="performanceChart"></canvas>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h2><i class="bi bi-exclamation-diamond"></i> Incident Distribution</h2>
                <div class="card-actions">
                    <button class="btn-icon" onclick="changeChartType('incidentChart')">
                        <i class="bi bi-bar-chart"></i>
                    </button>
                </div>
            </div>
            <canvas id="incidentChart"></canvas>
        </div>
    </div>

    <script>
        // Enhanced Chart Configurations
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.8)';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.titleFont.size = 14;
        Chart.defaults.plugins.tooltip.titleFont.weight = '600';

        // Equipment Status Chart
        const equipmentStatusChart = new Chart(document.getElementById('equipmentStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($equipmentStatus, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($equipmentStatus, 'count')); ?>,
                    backgroundColor: [
                        '#22c55e', // operational
                        '#eab308', // maintenance
                        '#ef4444', // fault
                        '#64748b'  // offline
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });

        // Temperature Trend Chart with Gradient
        const tempCtx = document.getElementById('temperatureChart').getContext('2d');
        const tempGradient = tempCtx.createLinearGradient(0, 0, 0, 300);
        tempGradient.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
        tempGradient.addColorStop(1, 'rgba(239, 68, 68, 0)');

        new Chart(tempCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Temperature (°C)',
                    data: <?php echo json_encode($temperatures); ?>,
                    borderColor: '#ef4444',
                    backgroundColor: tempGradient,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ef4444',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Pressure Trend Chart with Gradient
        const pressureCtx = document.getElementById('pressureChart').getContext('2d');
        const pressureGradient = pressureCtx.createLinearGradient(0, 0, 0, 300);
        pressureGradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
        pressureGradient.addColorStop(1, 'rgba(59, 130, 246, 0)');

        new Chart(pressureCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Pressure (PSI)',
                    data: <?php echo json_encode($pressures); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: pressureGradient,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Performance Chart
        new Chart(document.getElementById('performanceChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{
                    label: 'Efficiency (%)',
                    data: <?php echo json_encode($efficiencies); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    yAxisID: 'y',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Power Output (kW)',
                    data: <?php echo json_encode($powerOutputs); ?>,
                    borderColor: '#eab308',
                    backgroundColor: 'rgba(234, 179, 8, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Efficiency (%)'
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Power Output (kW)'
                        },
                        grid: {
                            drawOnChartArea: false,
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Incident Categories Chart
        new Chart(document.getElementById('incidentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($incidentCategories, 'category')); ?>,
                datasets: [{
                    label: 'Number of Incidents',
                    data: <?php echo json_encode(array_column($incidentCategories, 'count')); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Chart Interaction Functions
        function refreshChart(chartId) {
            const chart = Chart.getChart(chartId);
            chart.update('active');
        }

        function updateTimeRange(chartId, days) {
            // Implementation for time range update
            console.log(`Updating ${chartId} to show last ${days} days`);
        }

        function toggleMetric(chartId) {
            const chart = Chart.getChart(chartId);
            chart.data.datasets.forEach(dataset => {
                dataset.hidden = !dataset.hidden;
            });
            chart.update();
        }

        function changeChartType(chartId) {
            const chart = Chart.getChart(chartId);
            chart.config.type = chart.config.type === 'bar' ? 'line' : 'bar';
            chart.update();
        }
    </script>

    <style>
        .header-content {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .role-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        .summary-cards {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .summary-card i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .summary-info h3 {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            background: none;
            border: none;
            color: var(--gray-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }

        .time-range {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-color);
            background: white;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
