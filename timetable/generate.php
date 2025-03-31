<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Check authentication
if (!isset($_SESSION['school_id'])) {
    header('Location: ../index.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$errors = [];
$success = '';
$generated = false;
$timetable_data = [];

// Handle timetable generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    // Get all necessary data
    $academic_year = $conn->query("
        SELECT days_week FROM academic_years 
        WHERE school_id = $school_id 
        ORDER BY start_date DESC LIMIT 1
    ")->fetch_assoc();
    
    if (!$academic_year) {
        $errors[] = "No academic year found. Please create one first.";
    } 
    else {
        $working_days = explode(',', $academic_year['days_week']);
        $teachers = $conn->query("
            SELECT t.*, 
                   GROUP_CONCAT(DISTINCT ts.subject_id) AS subject_ids,
                   GROUP_CONCAT(DISTINCT ts.class_id) AS class_ids,
                   GROUP_CONCAT(DISTINCT ts.division_id) AS division_ids
            FROM teachers t
            LEFT JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
            WHERE t.school_id = $school_id
            GROUP BY t.teacher_id
        ")->fetch_all(MYSQLI_ASSOC);
        
        $classes = $conn->query("
            SELECT c.class_id, c.name AS class_name, 
                   d.division_id, d.name AS division_name
            FROM classes c
            JOIN divisions d ON c.class_id = d.class_id
            WHERE c.school_id = $school_id
            ORDER BY c.name, d.name
        ")->fetch_all(MYSQLI_ASSOC);
        
        $subjects = $conn->query("
            SELECT s.subject_id, s.name, s.class_id,
                   ts.teacher_id, ts.division_id, ts.periods_per_week
            FROM subjects s
            JOIN teacher_subjects ts ON s.subject_id = ts.subject_id
            WHERE s.school_id = $school_id
        ")->fetch_all(MYSQLI_ASSOC);
        
        // Simple timetable generation algorithm (backtracking would be better)
        $timetable = [];
        $teacher_schedule = [];
        $class_schedule = [];
        
        // Initialize data structures
        foreach ($teachers as $teacher) {
            $teacher_schedule[$teacher['teacher_id']] = [
                'periods_week' => 0,
                'daily_periods' => array_fill(0, count($working_days), 0)
            ];
        }
        
        foreach ($classes as $class) {
            $class_key = $class['class_id'].'_'.$class['division_id'];
            $class_schedule[$class_key] = array_fill(0, count($working_days), []);
        }
        
        // Attempt to assign subjects
        foreach ($subjects as $subject) {
            $class_key = $subject['class_id'].'_'.$subject['division_id'];
            $teacher_id = $subject['teacher_id'];
            $periods_needed = $subject['periods_per_week'];
            $periods_assigned = 0;
            
            // Try to assign periods
            for ($day_idx = 0; $day_idx < count($working_days) && $periods_assigned < $periods_needed; $day_idx++) {
                // Check teacher availability
                if ($teacher_schedule[$teacher_id]['daily_periods'][$day_idx] >= $teachers[$teacher_id]['max_periods_day']) {
                    continue;
                }
                
                // Check if teacher is available this day
                $unavailability = json_decode($teachers[$teacher_id]['unavailability'], true);
                if (in_array($working_days[$day_idx], $unavailability['days'] ?? [])) {
                    continue;
                }
                
                // Find an available period
                for ($period = 1; $period <= 8; $period++) {
                    // Check if period is available for teacher
                    if (in_array($period, $unavailability['periods'] ?? [])) {
                        continue;
                    }
                    
                    // Check if class has this period free
                    if (!isset($class_schedule[$class_key][$day_idx][$period])) {
                        // Assign the period
                        $class_schedule[$class_key][$day_idx][$period] = [
                            'subject_id' => $subject['subject_id'],
                            'teacher_id' => $teacher_id,
                            'subject_name' => $subject['name'],
                            'teacher_name' => $teachers[$teacher_id]['full_name']
                        ];
                        
                        $teacher_schedule[$teacher_id]['periods_week']++;
                        $teacher_schedule[$teacher_id]['daily_periods'][$day_idx]++;
                        $periods_assigned++;
                        
                        break;
                    }
                }
            }
            
            if ($periods_assigned < $periods_needed) {
                $errors[] = "Could not fully assign ".$subject['name']." for class ".$class_key;
            }
        }
        
        if (empty($errors)) {
            $success = "Timetable generated successfully!";
            $generated = true;
            $timetable_data = [
                'working_days' => $working_days,
                'class_schedule' => $class_schedule,
                'teacher_schedule' => $teacher_schedule
            ];
            
            // Store in session for editing
            $_SESSION['timetable_data'] = $timetable_data;
        }
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Generate Timetable</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Timetable Generation</h2>
        
        <form method="POST">
            <div class="mb-4">
                <p class="text-gray-700 mb-2">This will generate a new timetable based on current assignments and constraints.</p>
                <p class="text-gray-500 text-sm">Note: Complex constraints may result in some unassigned periods.</p>
            </div>
            
            <button type="submit" name="generate"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                Generate Timetable
            </button>
        </form>
    </div>

    <?php if ($generated): ?>
        <!-- Display generated timetable -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Generated Timetable</h2>
            
            <div class="mb-4">
                <label for="view_type" class="block text-gray-700 mb-2">View As:</label>
                <select id="view_type" class="px-3 py-2 border rounded-md"
                        onchange="changeViewType(this.value)">
                    <option value="class">By Class/Division</option>
                    <option value="teacher">By Teacher</option>
                </select>
            </div>
            
            <!-- Class-wise Timetable View -->
            <div id="class_view">
                <?php foreach ($timetable_data['class_schedule'] as $class_key => $days): 
                    list($class_id, $division_id) = explode('_', $class_key);
                    $class_info = $conn->query("
                        SELECT c.name AS class_name, d.name AS division_name 
                        FROM classes c JOIN divisions d ON c.class_id = d.class_id
                        WHERE c.class_id = $class_id AND d.division_id = $division_id
                    ")->fetch_assoc();
                ?>
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-2">
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
            </div>
            
            <!-- Teacher-wise Timetable View (hidden by default) -->
            <div id="teacher_view" class="hidden">
                <?php foreach ($teacher_schedule as $teacher_id => $schedule): 
                    $teacher = $conn->query("
                        SELECT full_name FROM teachers 
                        WHERE teacher_id = $teacher_id
                    ")->fetch_assoc();
                ?>
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-2">
                            <?= htmlspecialchars($teacher['full_name']) ?>
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
                                            <?php foreach ($timetable_data['working_days'] as $day_idx => $day): 
                                                $assigned = false;
                                                foreach ($timetable_data['class_schedule'] as $class_key => $days) {
                                                    if (isset($days[$day_idx][$period]) && 
                                                        $days[$day_idx][$period]['teacher_id'] == $teacher_id) {
                                                        $assigned = $days[$day_idx][$period];
                                                        break;
                                                    }
                                                }
                                            ?>
                                                <td class="py-2 px-4 border">
                                                    <?php if ($assigned): ?>
                                                        <div class="text-sm">
                                                            <div><?= htmlspecialchars($assigned['subject_name']) ?></div>
                                                            <div class="text-gray-500">
                                                                <?= htmlspecialchars(explode('_', $class_key)[0]) ?>
                                                            </div>
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
            </div>
            
            <div class="mt-6">
                <a href="edit.php" class="inline-block bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200 mr-2">
                    Edit Timetable
                </a>
                <a href="export_csv.php" class="inline-block bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Export as CSV
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function changeViewType(type) {
    if (type === 'class') {
        document.getElementById('class_view').classList.remove('hidden');
        document.getElementById('teacher_view').classList.add('hidden');
    } else {
        document.getElementById('class_view').classList.add('hidden');
        document.getElementById('teacher_view').classList.remove('hidden');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>