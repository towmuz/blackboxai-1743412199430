<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Check authentication
if (!isset($_SESSION['school_id'])) {
    header('Location: ../index.php');
    exit;
}

$school_id = $_SESSION['school_id'];

// Get timetable data from session or database
if (isset($_SESSION['timetable_data'])) {
    $timetable_data = $_SESSION['timetable_data'];
} else {
    // Fallback to load from database if session data not available
    $timetable_data = [
        'working_days' => [],
        'class_schedule' => []
    ];
    
    // Load working days
    $academic_year = $conn->query("
        SELECT days_week FROM academic_years 
        WHERE school_id = $school_id 
        ORDER BY start_date DESC LIMIT 1
    ")->fetch_assoc();
    
    if ($academic_year) {
        $timetable_data['working_days'] = explode(',', $academic_year['days_week']);
    }
    
    // Load class schedule (simplified example - would need proper storage in real implementation)
    $classes = $conn->query("
        SELECT c.class_id, c.name AS class_name, 
               d.division_id, d.name AS division_name
        FROM classes c
        JOIN divisions d ON c.class_id = d.class_id
        WHERE c.school_id = $school_id
    ")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($classes as $class) {
        $class_key = $class['class_id'].'_'.$class['division_id'];
        $timetable_data['class_schedule'][$class_key] = [];
    }
}

// Handle export requests
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="timetable_export.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, ['Class', 'Division', 'Day', 'Period', 'Subject', 'Teacher']);
        
        // CSV data
        foreach ($timetable_data['class_schedule'] as $class_key => $days) {
            list($class_id, $division_id) = explode('_', $class_key);
            
            $class_info = $conn->query("
                SELECT c.name AS class_name, d.name AS division_name 
                FROM classes c 
                JOIN divisions d ON c.class_id = d.class_id
                WHERE c.class_id = $class_id AND d.division_id = $division_id
            ")->fetch_assoc();
            
            foreach ($days as $day_idx => $periods) {
                $day = $timetable_data['working_days'][$day_idx];
                
                for ($period = 1; $period <= 8; $period++) {
                    $subject = isset($periods[$period]) ? $periods[$period]['subject_name'] : '';
                    $teacher = isset($periods[$period]) ? $periods[$period]['teacher_name'] : '';
                    
                    fputcsv($output, [
                        $class_info['class_name'],
                        $class_info['division_name'],
                        $day,
                        $period,
                        $subject,
                        $teacher
                    ]);
                }
            }
        }
        
        fclose($output);
        exit;
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">View Timetable</h1>
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold">Class-wise Timetable</h2>
        <div class="flex space-x-2">
            <a href="?export=csv" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                Export as CSV
            </a>
            <a href="generate.php" class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 transition duration-200">
                Back to Generator
            </a>
        </div>
    </div>
    
    <?php if (empty($timetable_data['working_days'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            No timetable data available. Please generate a timetable first.
        </div>
    <?php else: ?>
        <?php foreach ($timetable_data['class_schedule'] as $class_key => $days): 
            list($class_id, $division_id) = explode('_', $class_key);
            $class_info = $conn->query("
                SELECT c.name AS class_name, d.name AS division_name 
                FROM classes c JOIN divisions d ON c.class_id = d.class_id
                WHERE c.class_id = $class_id AND d.division_id = $division_id
            ")->fetch_assoc();
        ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-lg font-medium mb-4">
                    <?= htmlspecialchars($class_info['class_name']) ?> - 
                    <?= htmlspecialchars($class_info['division_name']) ?>
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Period/Day</th>
                                <?php foreach ($timetable_data['working_days'] as $day): ?>
                                    <th class="py-2 px-4 border"><?= $day ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($period = 1; $period <= 8; $period++): ?>
                                <tr>
                                    <td class="py-2 px-4 border font-medium">Period <?= $period ?></td>
                                    <?php foreach ($timetable_data['working_days'] as $day_idx => $day): ?>
                                        <td class="py-2 px-4 border">
                                            <?php if (isset($days[$day_idx][$period])): ?>
                                                <div class="text-sm">
                                                    <div><?= htmlspecialchars($days[$day_idx][$period]['subject_name']) ?></div>
                                                    <div class="text-gray-500"><?= htmlspecialchars($days[$day_idx][$period]['teacher_name']) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>