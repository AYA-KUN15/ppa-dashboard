<?php
// index.php (Home / Monitoring Dashboard)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// FullCalendar events – only active activities with duration range
$events = [];

try {
    $stmt = $pdo->query("
        SELECT id, activity_name, implementation_start, implementation_end
        FROM activity_entries
        WHERE status = 'active'
        AND implementation_start IS NOT NULL
        AND implementation_end IS NOT NULL
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'title' => $row['activity_name'] . ' (Activity)',
            'start' => $row['implementation_start'],
            'end'   => date('Y-m-d', strtotime($row['implementation_end'] . ' +1 day')), // inclusive end
            'url'   => "opmm/view_activity.php?id={$row['id']}",
            'color' => '#6B7280',
            'textColor' => '#FFFFFF',
            'borderColor' => '#C8102E',
            'extendedProps' => ['type' => 'activity']
        ];
    }
} catch (PDOException $e) {
    // silent fail or log
}

// Your existing due monitoring list (kept as-is)
$today = date('Y-m-d');
$currentDayOfWeek = date('N');

try {
    $stmt = $pdo->query("
        SELECT id, title, quarter, fiscal_year, frequency_monitoring, date_duration
        FROM ppa_entries
        WHERE status = 'active'
        ORDER BY created_at DESC
    ");
    $allEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dueToday = [];
    foreach ($allEntries as $entry) {
        $freq = strtolower($entry['frequency_monitoring'] ?? '');
        $needsAttention = false;

        switch ($freq) {
            case 'daily':
                $needsAttention = true;
                break;
            case 'weekly':
                $needsAttention = true;
                break;
            case 'monthly':
                $dayOfMonth = date('j', strtotime($today));
                if (strpos($entry['date_duration'], (string)$dayOfMonth) !== false) {
                    $needsAttention = true;
                }
                break;
            case 'quarterly':
            case 'semi-annually':
            case 'annually':
            case 'as needed':
            case 'event-based':
                $needsAttention = true;
                break;
        }

        if ($needsAttention) {
            $dueToday[] = $entry;
        }
    }
} catch (PDOException $e) {
    $dueToday = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Dashboard - Home</title>
    <link rel="stylesheet" href="css/style.css">

    <!-- FullCalendar v6 -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

    <style>
        #calendar {
            max-width: 1200px;
            margin: 40px auto;
            background: #FFFFFF;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }
        .fc-toolbar-chunk:first-child {
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            flex-wrap: nowrap !important;
        }
        .fc .fc-button-group {
            display: flex !important;
            flex-wrap: nowrap !important;
        }
        .fc .fc-toolbar-chunk:first-child .fc-button {
            padding: 6px 10px !important;
            font-size: 0.9em !important;
            min-width: 60px !important;
            line-height: 1.4 !important;
        }
        .fc .fc-toolbar-chunk:first-child .fc-today-button {
            background-color: #C8102E !important;
            border-color: #C8102E !important;
            color: white !important;
            font-weight: 500 !important;
        }
        .fc .fc-toolbar-chunk:first-child .fc-today-button:hover {
            background-color: #A30D26 !important;
            border-color: #A30D26 !important;
        }
        .fc .fc-button-primary {
            background-color: #C8102E;
            border-color: #C8102E;
            color: white;
        }
        .fc .fc-button-primary:hover {
            background-color: #A30D26;
            border-color: #A30D26;
        }
        .fc .fc-button-primary:disabled {
            background-color: #6B7280;
            border-color: #6B7280;
        }
        .fc-event {
            border: none;
            padding: 4px 8px;
            font-size: 0.95em;
            border-radius: 4px;
        }
        .fc-event:hover {
            opacity: 0.9;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .fc-daygrid-day-number {
            color: #374151;
        }
        .fc-col-header-cell-cushion {
            color: #374151;
            font-weight: 600;
        }
        @media (max-width: 576px) {
            .fc-toolbar-chunk:first-child {
                gap: 3px !important;
            }
            .fc .fc-toolbar-chunk:first-child .fc-button {
                padding: 5px 8px !important;
                font-size: 0.85em !important;
                min-width: 50px !important;
            }
        }
    </style>
</head>
<body>

    <header class="top-bar">
        <div class="logo-container">
            <img src="assets/bsu-logo.jpg" alt="BSU Logo" class="logo">
            <span class="logo-text">PPA Dashboard</span>
        </div>
        <nav class="main-nav">
            <a href="index.php" class="nav-button active">Home</a>
            <a href="opmm/list.php" class="nav-button">PPA</a>
            <a href="logout.php" class="nav-button logout">Logout</a>
        </nav>
    </header>

    <main class="dashboard-content">
        <h1>PPA Monitoring Dashboard</h1>

        <!-- Calendar -->
        <div id="calendar"></div>

        <!-- Due monitoring list -->
        <p style="margin-top: 40px;">Items that may need attention based on monitoring frequency.</p>

        <?php if (empty($dueToday)): ?>
            <p class="info">No PPAs require monitoring at this time.</p>
        <?php else: ?>
            <div class="summary-grid">
                <div class="summary-card">
                    <h3>Due for Monitoring</h3>
                    <p class="placeholder"><?= count($dueToday) ?></p>
                </div>
            </div>

            <div class="due-list" style="margin-top: 32px;">
                <h3>Activities to Monitor</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($dueToday as $entry): ?>
                        <li style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                            <a href="opmm/view.php?id=<?= $entry['id'] ?>" style="text-decoration: none; color: #374151;">
                                <strong><?= htmlspecialchars($entry['title']) ?></strong><br>
                                <small>
                                    <?= htmlspecialchars($entry['quarter'] ?? 'N/A') ?> Quarter, 
                                    FY <?= htmlspecialchars($entry['fiscal_year'] ?? 'N/A') ?>
                                    · <?= htmlspecialchars($entry['frequency_monitoring'] ?? 'Not specified') ?>
                                </small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: <?= json_encode($events) ?>,
            eventClick: function(info) {
                if (info.event.url) {
                    window.open(info.event.url, '_blank');
                    info.jsEvent.preventDefault();
                }
            },
            height: 'auto',
            contentHeight: 'auto',
            eventDidMount: function(info) {
                info.el.title = info.event.title;
            }
        });
        calendar.render();
    });
    </script>
</body>
</html>