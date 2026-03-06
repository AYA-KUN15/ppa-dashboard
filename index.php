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
    // silent fail
}

$nav_links = [
    ['url' => 'index.php',          'label' => 'Home', 'active' => true],
    ['url' => 'opmm/list.php',      'label' => 'PPA',  'active' => false],
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

    <main class="dashboard-content">
        <h1>PPA Monitoring Dashboard</h1>

        <!-- Calendar (only content now) -->
        <div id="calendar"></div>
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