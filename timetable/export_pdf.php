<?php
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

// Check authentication
if (!isset($_SESSION['school_id'])) {
    header('Location: ../index.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$type = $_GET['type'] ?? 'class';
$id = $_GET['id'] ?? 0;

// Get timetable data
if (isset($_SESSION['timetable_data'])) {
    $timetable_data = $_SESSION['timetable_data'];
} else {
    $timetable_data = [
        'working_days' => [],
        'class_schedule' => []
    ];
    
    $academic_year = $conn->query("
        SELECT days_week FROM academic_years 
        WHERE school_id = $school_id 
        ORDER BY start_date DESC LIMIT 1
    ")->fetch_assoc();
    
    if ($academic_year) {
        $timetable_data['working_days'] = explode(',', $academic_year['days_week']);
    }
}

// Get all classes and teachers if needed
if ($type === 'all_classes' || $type === 'all_teachers') {
    $classes = $conn->query("
        SELECT c.class_id, c.name AS class_name, d.division_id, d.name AS division_name
        FROM classes c
        JOIN divisions d ON c.class_id = d.class_id
        WHERE c.school_id = $school_id
        ORDER BY c.name, d.name
    ")->fetch_all(MYSQLI_ASSOC);
    
    $teachers = $conn->query("
        SELECT teacher_id, full_name 
        FROM teachers 
        WHERE school_id = $school_id
        ORDER BY full_name
    ")->fetch_all(MYSQLI_ASSOC);
}

// Create PDF with optimized settings
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'L',
    'tempDir' => sys_get_temp_dir(),
    'setAutoTopMargin' => 'stretch',
    'setAutoBottomMargin' => 'stretch',
    'autoMarginPadding' => 5
]);

$html = '<style>
    body { font-family: Arial; }
    h1 { color: #2c3e50; text-align: center; }
    h2 { color: #34495e; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { background-color: #f8f9fa; text-align: center; padding: 8px; border: 1px solid #ddd; }
    td { padding: 8px; border: 1px solid #ddd; text-align: center; }
    .subject { font-weight: bold; }
    .teacher { color: #555; font-size: 0.9em; }
</style>';

if ($type === 'class' || $type === 'all_classes') {
    if ($type === 'all_classes') {
        $html .= '<h1>All Class Timetables</h1>';
        foreach ($classes as $class) {
            $class_id = $class['class_id'];
            $class_info = $class;
            
            $html .= '<h2 style="page-break-before: always;">'.htmlspecialchars($class_info['class_name']).' - '.htmlspecialchars($class_info['division_name']).'</h2>';
            $html .= generateClassTimetableHtml($timetable_data, $class_id, $class['division_id']);
        }
    } else {
        $class_info = $conn->query("
            SELECT c.name AS class_name, d.name AS division_name 
            FROM classes c JOIN divisions d ON c.class_id = d.class_id
            WHERE c.class_id = $id
        ")->fetch_assoc();
        
        $html .= '<h1>Timetable for '.htmlspecialchars($class_info['class_name']).' - '.htmlspecialchars($class_info['division_name']).'</h1>';
        $html .= generateClassTimetableHtml($timetable_data, $id, $id);
    }
} elseif ($type === 'teacher' || $type === 'all_teachers') {
    if ($type === 'all_teachers') {
        $html .= '<h1>All Teacher Timetables</h1>';
        foreach ($teachers as $teacher) {
            $html .= '<h2 style="page-break-before: always;">'.htmlspecialchars($teacher['full_name']).'</h2>';
            $html .= generateTeacherTimetableHtml($timetable_data, $teacher['teacher_id'], $conn);
        }
    } else {
        $teacher = $conn->query("
            SELECT full_name FROM teachers 
            WHERE teacher_id = $id
        ")->fetch_assoc();
        
        $html .= '<h1>Timetable for '.htmlspecialchars($teacher['full_name']).'</h1>';
        $html .= generateTeacherTimetableHtml($timetable_data, $id, $conn);
    }
}

// Output PDF with appropriate filename
$mpdf->WriteHTML($html);
$filename = $type === 'all_classes' ? 'all_classes_timetable_'.date('Y-m-d').'.pdf' : 
           ($type === 'all_teachers' ? 'all_teachers_timetable_'.date('Y-m-d').'.pdf' : 
           $type.'_timetable_'.date('Y-m-d').'.pdf');
$mpdf->Output($filename, 'D');
exit;

function generateClassTimetableHtml($timetable_data, $class_id, $division_id) {
    $html = '<table>';
    $html .= '<tr><th>Period/Day</th>';
    
    foreach ($timetable_data['working_days'] as $day) {
        $html .= '<th>'.$day.'</th>';
    }
    $html .= '</tr>';
    
    for ($period = 1; $period <= 8; $period++) {
        $html .= '<tr><td>Period '.$period.'</td>';
        
        foreach ($timetable_data['working_days'] as $day_idx => $day) {
            $entry = $timetable_data['class_schedule'][$class_id.'_'.$division_id][$day_idx][$period] ?? null;
            $html .= '<td>';
            if ($entry) {
                $html .= '<div class="subject">'.htmlspecialchars($entry['subject_name']).'</div>';
                $html .= '<div class="teacher">'.htmlspecialchars($entry['teacher_name']).'</div>';
            }
            $html .= '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    return $html;
}

function generateTeacherTimetableHtml($timetable_data, $teacher_id, $conn) {
    $html = '<table>';
    $html .= '<tr><th>Period/Day</th>';
    
    foreach ($timetable_data['working_days'] as $day) {
        $html .= '<th>'.$day.'</th>';
    }
    $html .= '</tr>';
    
    $teacher_schedule = [];
    foreach ($timetable_data['class_schedule'] as $class_key => $days) {
        list($class_id, $division_id) = explode('_', $class_key);
        
        $class_info = $conn->query("
            SELECT c.name AS class_name, d.name AS division_name 
            FROM classes c JOIN divisions d ON c.class_id = d.class_id
            WHERE c.class_id = $class_id AND d.division_id = $division_id
        ")->fetch_assoc();
        
        foreach ($days as $day_idx => $periods) {
            foreach ($periods as $period => $entry) {
                if ($entry['teacher_id'] == $teacher_id) {
                    $teacher_schedule[$day_idx][$period] = [
                        'subject_name' => $entry['subject_name'],
                        'class' => $class_info['class_name'].' - '.$class_info['division_name']
                    ];
                }
            }
        }
    }
    
    for ($period = 1; $period <= 8; $period++) {
        $html .= '<tr><td>Period '.$period.'</td>';
        
        foreach ($timetable_data['working_days'] as $day_idx => $day) {
            $entry = $teacher_schedule[$day_idx][$period] ?? null;
            $html .= '<td>';
            if ($entry) {
                $html .= '<div class="subject">'.htmlspecialchars($entry['subject_name']).'</div>';
                $html .= '<div class="teacher">'.htmlspecialchars($entry['class']).'</div>';
            }
            $html .= '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    return $html;
}