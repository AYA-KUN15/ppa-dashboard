<?php
// index.php (Home / Monitoring Dashboard)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ────────────────────────────────────────────────
// 1. Calendar Events: Activities + Proposals
// ────────────────────────────────────────────────
$calendarEvents = [];

try {
    // Activities
    $stmt = $pdo->query("
        SELECT id, activity_name, implementation_start, implementation_end
        FROM activity_entries
        WHERE status = 'active'
          AND implementation_start IS NOT NULL
          AND implementation_end IS NOT NULL
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calendarEvents[] = [
            'title' => $row['activity_name'] . ' (Activity)',
            'start' => $row['implementation_start'],
            'end'   => date('Y-m-d', strtotime($row['implementation_end'] . ' +1 day')),
            'url'   => "opmm/view_activity.php?id={$row['id']}",
            'color' => '#6B7280',
            'textColor' => '#FFFFFF',
            'borderColor' => '#C8102E',
            'extendedProps' => ['type' => 'activity']
        ];
    }

    // Proposals
    $stmt = $pdo->query("
        SELECT id, title, start_date, end_date
        FROM research_proposals
        WHERE start_date IS NOT NULL AND end_date IS NOT NULL
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calendarEvents[] = [
            'title' => $row['title'] . ' (Proposal)',
            'start' => $row['start_date'],
            'end'   => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')),
            'url'   => "opmm/view_proposals.php?id={$row['id']}",
            'color' => '#C8102E',
            'textColor' => '#FFFFFF',
            'borderColor' => '#991B1B',
            'extendedProps' => ['type' => 'proposal']
        ];
    }
} catch (PDOException $e) {
    // silent fail
}

// ────────────────────────────────────────────────
// 2. Program Monitoring Due
// ────────────────────────────────────────────────
$today = date('Y-m-d');
$duePrograms = [];

try {
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.title AS program_title, 
            p.duration_start, 
            p.duration_end, 
            p.monitoring_frequency, 
            p.status,
            COUNT(a.id) AS total_activities,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_activities
        FROM program_entries p
        LEFT JOIN project_entries pr ON pr.program_id = p.id
        LEFT JOIN activity_entries a ON a.project_id = pr.id
        WHERE p.status IN ('overdue', 'mae_phase')
          AND p.duration_start IS NOT NULL
          AND p.duration_end IS NOT NULL
        GROUP BY p.id
        ORDER BY p.duration_start ASC
    ");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($programs as $prog) {
        $freq = strtolower($prog['monitoring_frequency'] ?? '');
        $start = new DateTime($prog['duration_start']);

        if ($prog['status'] === 'mae_phase') {
            $duePrograms[] = [
                'id'       => $prog['id'],
                'name'     => $prog['program_title'],
                'freq'     => ucfirst($freq),
                'due'      => 'Ongoing Evaluation (M&E Phase)',
                'overdue'  => false,
                'special'  => 'mae_phase'
            ];
            continue;
        }

        if ($prog['status'] === 'overdue') {
            $duePrograms[] = [
                'id'       => $prog['id'],
                'name'     => $prog['program_title'],
                'freq'     => ucfirst($freq),
                'due'      => 'Overdue (3+ years incomplete)',
                'overdue'  => true,
                'special'  => 'overdue'
            ];
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("Monitoring query error: " . $e->getMessage());
    $duePrograms = [];
}

// ────────────────────────────────────────────────
// 3. Proposals Monitoring Due This Period
// ────────────────────────────────────────────────
$dueProposals = [];

try {
    $stmt = $pdo->query("
        SELECT id, title, start_date, end_date, status
        FROM research_proposals
        WHERE end_date IS NOT NULL
        ORDER BY end_date ASC
    ");
    $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($proposals as $prop) {
        $endDate = new DateTime($prop['end_date']);
        $daysUntil = (new DateTime($today))->diff($endDate)->days * 
                     (($endDate < new DateTime($today)) ? -1 : 1);

        $isOverdue = $endDate < new DateTime($today);
        $isNearEnd = $daysUntil <= 30 && $daysUntil > 0;

        if ($isOverdue || $isNearEnd) {
            $dueProposals[] = [
                'id'      => $prop['id'],
                'title'   => $prop['title'],
                'end_date'=> $prop['end_date'],
                'days'    => $daysUntil,
                'overdue' => $isOverdue
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Proposals due query error: " . $e->getMessage());
    $dueProposals = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => true],
    ['url' => 'opmm/list.php', 'label' => 'PPA', 'active' => false],
    ['url' => '/opmm/list_proposals.php', 'label' => 'Proposals', 'active' => false],
];

include 'includes/header.php';
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
            margin: 30px auto;
            background: #FFFFFF;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
        }

        /* Compact calendar controls */
        .calendar-controls {
            max-width: 1200px;
            margin: 20px auto 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px;
            font-size: 0.95rem;
        }

        .calendar-controls label {
            font-weight: 500;
            color: #374151;
            white-space: nowrap;
        }

        .calendar-controls select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
            min-width: 220px;
        }

        /* Calendar Toolbar Styling - Today button aligned with < > */
        .fc-toolbar-chunk {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .fc-toolbar-title {
            margin: 0 12px !important;
        }

        .fc-button {
            padding: 6px 10px !important;
            font-size: 0.92rem !important;
            min-width: 44px;
        }

        .fc-today-button {
            background-color: #C8102E !important;
            border-color: #C8102E !important;
            color: white !important;
            font-weight: 500 !important;
            padding: 6px 12px !important;
            font-size: 0.88rem !important;
        }

        .fc-button-primary {
            background-color: #C8102E;
            border-color: #C8102E;
        }

        .fc-button-primary:hover {
            background-color: #A30D26;
            border-color: #A30D26;
        }

        .fc-event {
            border: none;
            padding: 4px 8px;
            font-size: 0.95em;
            border-radius: 4px;
        }

        .fc-daygrid-day-number {
            color: #374151;
        }

        .fc-col-header-cell-cushion {
            color: #374151;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>PPA Monitoring Dashboard</h1>

        <!-- Calendar Controls -->
        <div class="calendar-controls">
            <label for="calendar-filter">Show Schedule For:</label>
            <select id="calendar-filter">
                <option value="all">All (Activities + Proposals)</option>
                <option value="activity">Activities Only</option>
                <option value="proposal">Proposals Only</option>
            </select>
        </div>

        <!-- Calendar -->
        <div id="calendar"></div>

        <!-- Program Monitoring Due -->
        <div class="monitoring-section">
            <div class="monitoring-header">
                <h2>Program Monitoring Due This Period</h2>
                <?php if (!empty($duePrograms)): ?>
                    <span class="due-count"><?= count($duePrograms) ?> due</span>
                <?php endif; ?>
            </div>

            <?php if (empty($duePrograms)): ?>
                <p style="color:#6b7280; text-align:center; font-style:italic;">
                    All program monitoring is up to date.
                </p>
            <?php else: ?>
                <div class="monitoring-cards">
                    <?php foreach ($duePrograms as $prog): ?>
                        <div class="monitoring-card <?= $prog['overdue'] ? 'overdue' : '' ?>">
                            <div class="title"><?= htmlspecialchars($prog['name']) ?></div>
                            <div class="freq">Frequency: <?= htmlspecialchars($prog['freq']) ?></div>
                            <div class="due-date <?= $prog['overdue'] ? 'overdue' : '' ?>">
                                <?= htmlspecialchars($prog['due']) ?>
                            </div>
                            <a href="opmm/view.php?id=<?= $prog['id'] ?>" class="action">View Program</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Proposals Monitoring Due -->
        <div class="monitoring-section">
            <div class="monitoring-header">
                <h2>Proposals Monitoring Due This Period</h2>
                <?php if (!empty($dueProposals)): ?>
                    <span class="due-count"><?= count($dueProposals) ?> due</span>
                <?php endif; ?>
            </div>

            <?php if (empty($dueProposals)): ?>
                <p style="color:#6b7280; text-align:center; font-style:italic;">
                    All proposals monitoring is up to date.
                </p>
            <?php else: ?>
                <div class="monitoring-cards">
                    <?php foreach ($dueProposals as $prop): ?>
                        <div class="monitoring-card <?= $prop['overdue'] ? 'overdue' : '' ?>">
                            <div class="title"><?= htmlspecialchars($prop['title']) ?></div>
                            <div class="due-date <?= $prop['overdue'] ? 'overdue' : '' ?>">
                                <?= $prop['overdue'] ? 'Overdue' : 'Ending Soon' ?> — 
                                <?= htmlspecialchars(date('M d, Y', strtotime($prop['end_date']))) ?>
                                (<?= $prop['days'] ?> days)
                            </div>
                            <a href="opmm/view_proposals.php?id=<?= $prop['id'] ?>" class="action">View Proposal</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var allEvents = <?= json_encode($calendarEvents) ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''   // Removed week and day views
            },
            events: allEvents,
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

        // Filter functionality
        var filterSelect = document.getElementById('calendar-filter');
        filterSelect.addEventListener('change', function() {
            var filter = this.value;
            calendar.removeAllEvents();

            if (filter === 'all') {
                calendar.addEventSource(allEvents);
            } else {
                var filtered = allEvents.filter(function(event) {
                    return event.extendedProps.type === filter;
                });
                calendar.addEventSource(filtered);
            }
        });
    });
    </script>
</body>
</html>