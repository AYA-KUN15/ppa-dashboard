<?php
// index.php (Home / Monitoring Dashboard)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ────────────────────────────────────────────────
// 1. Calendar: active activities duration bars (unchanged)
// ────────────────────────────────────────────────
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
            'end'   => date('Y-m-d', strtotime($row['implementation_end'] . ' +1 day')),
            'url'   => "opmm/view_activity.php?id={$row['id']}",
            'color' => '#6B7280',
            'textColor' => '#FFFFFF',
            'borderColor' => '#C8102E',
            'extendedProps' => ['type' => 'activity']
        ];
    }
} catch (PDOException $e) {
    // silent fail
}

// ────────────────────────────────────────────────
// 2. Monitoring Due: only overdue + mae_phase programs
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
        $end   = new DateTime($prog['duration_end']);
        $yearsInProgress = (int) $start->diff(new DateTime($today))->y;
        $allProjectsDone = ($prog['total_activities'] > 0 && $prog['completed_activities'] == $prog['total_activities']);

        if ($prog['status'] === 'mae_phase') {
            // M&E Phase: all projects done + 3+ years passed
            $duePrograms[] = [
                'id'       => $prog['id'],
                'name'     => $prog['program_title'],
                'freq'     => ucfirst($freq),
                'due'      => 'Ongoing Evaluation (M&E Phase)',
                'overdue'  => false,
                'days'     => 0,
                'special'  => 'mae_phase'
            ];
            continue;
        }

        if ($prog['status'] === 'overdue') {
            // Overdue: 3+ years + incomplete projects
            $duePrograms[] = [
                'id'       => $prog['id'],
                'name'     => $prog['program_title'],
                'freq'     => ucfirst($freq),
                'due'      => 'Overdue (3+ years incomplete)',
                'overdue'  => true,
                'days'     => -$yearsInProgress * 365,
                'special'  => 'overdue'
            ];
            continue;
        }

        // Fallback: frequency-based for any remaining edge cases (<3 years, active, incomplete)
        $nextDue = clone $start;
        switch ($freq) {
            case 'monthly':
                $nextDue->modify('first day of next month');
                break;
            case 'quarterly':
                $month = (int)$start->format('n');
                $nextQ = ceil($month / 3) * 3 + 1;
                if ($nextQ > 12) $nextQ -= 12;
                $nextDue->setDate($start->format('Y'), $nextQ, 1);
                if ($nextDue <= $start) $nextDue->modify('+1 year');
                break;
            case 'semi-annually':
                $month = (int)$start->format('n');
                $nextHalf = ($month <= 6) ? 7 : 1;
                $yearAdd = ($month <= 6) ? 0 : 1;
                $nextDue->setDate($start->format('Y') + $yearAdd, $nextHalf, 1);
                break;
            case 'annually':
                $nextDue->modify('+1 year');
                $nextDue->setDate($nextDue->format('Y'), $start->format('n'), $start->format('j'));
                break;
            default:
                continue 2;
        }

        $dueDate = $nextDue->format('Y-m-d');
        $daysUntil = (new DateTime($dueDate))->diff(new DateTime($today))->days * 
                     ((new DateTime($dueDate) < new DateTime($today)) ? -1 : 1);

        if ($dueDate <= $today || $daysUntil <= 30) {
            $duePrograms[] = [
                'id'       => $prog['id'],
                'name'     => $prog['program_title'],
                'freq'     => ucfirst($freq),
                'due'      => $dueDate,
                'overdue'  => $dueDate < $today,
                'days'     => $daysUntil,
                'special'  => null
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Monitoring query error: " . $e->getMessage());
    $duePrograms = [];
}

$nav_links = [
    ['url' => 'index.php', 'label' => 'Home', 'active' => true],
    ['url' => 'opmm/list.php', 'label' => 'PPA', 'active' => false],
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

        /* Monitoring Cards Section */
        .monitoring-section {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .monitoring-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .monitoring-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .due-count {
            background: #c8102e;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .monitoring-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .monitoring-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
        }

        .monitoring-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        .monitoring-card.overdue {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .monitoring-card.mae_phase {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .monitoring-card .title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .monitoring-card .freq {
            color: #4b5563;
            font-size: 0.95rem;
            margin-bottom: 12px;
        }

        .monitoring-card .due-date {
            font-weight: 500;
            margin-bottom: 16px;
        }

        .monitoring-card .due-date.overdue {
            color: #ef4444;
        }

        .monitoring-card .due-date.mae_phase {
            color: #f59e0b;
        }

        .monitoring-card .action {
            display: inline-block;
            padding: 8px 16px;
            background: #c8102e;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .monitoring-card .action:hover {
            background: #a50d24;
        }

        @media (max-width: 768px) {
            .monitoring-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <main class="dashboard-content">
        <h1>PPA Monitoring Dashboard</h1>

        <!-- Calendar: Activities -->
        <div id="calendar"></div>

        <!-- Monitoring Due: Programs -->
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
                        <?php
                        $cardClass = $prog['overdue'] ? 'overdue' : 'mae_phase';
                        $dueTextClass = $prog['overdue'] ? 'overdue' : 'mae_phase';
                        $dueDisplay = $prog['overdue'] 
                            ? 'Overdue (3+ years incomplete)' 
                            : 'Ongoing Evaluation (M&E Phase)';
                        ?>

                        <div class="monitoring-card <?= $cardClass ?>">
                            <div class="title"><?= htmlspecialchars($prog['name']) ?></div>
                            <div class="freq">Frequency: <?= htmlspecialchars($prog['freq']) ?></div>
                            <div class="due-date <?= $dueTextClass ?>">
                                <?= $dueDisplay ?>
                            </div>
                            <a href="opmm/view.php?id=<?= $prog['id'] ?>" class="action">View Program</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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