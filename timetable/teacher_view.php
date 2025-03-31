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
    // Fallback to load from database
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

// Get all teachers
$teachers = $conn->query("
    SELECT teacher_id, full_name 
    FROM teachers 
    WHERE school_id = $school_id
    ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);

// Get selected teacher (default to first teacher)
$selected_teacher_id = $_GET['teacher_id'] ?? ($teachers[0]['teacher_id'] ?? 0);
$teacher_schedule = [];

// Build teacher schedule if we have timetable data
if (!empty($timetable_data['working_days'])) {
    foreach ($timetable_data['class_schedule'] as $class_key => $days) {
        list($class_id, $division_id) = explode('_', $class_key);
        
        $class_info = $conn->query("
            SELECT c.name AS class_name, d.name AS division_name 
            FROM classes c JOIN divisions d ON c.class_id = d.class_id
            WHERE c.class_id = $class_id AND d.division_id = $division_id
        ")->fetch_assoc();
        
        foreach ($days as $day_idx => $periods) {
            foreach ($periods as $period => $entry) {
                if ($entry['teacher_id'] == $selected_teacher_id) {
                    $teacher_schedule[$day_idx][$period] = [
                        'subject_name' => $entry['subject_name'],
                        'class_name' => $class_info['class_name'],
                        'division_name' => $class_info['division_name']
                    ];
                }
            }
        }
    }
}

// Get today's substitutions for the teacher
$substitutions = $conn->query("
    SELECT s.*, 
           ot.full_name AS original_teacher_name,
           st.full_name AS substitute_teacher_name
    FROM substitutions s
    JOIN teachers ot ON s.original_teacher_id = ot.teacher_id
    JOIN teachers st ON s.substitute_teacher_id = st.teacher_id
    WHERE (s.original_teacher_id = $selected_teacher_id OR s.substitute_teacher_id = $selected_teacher_id)
    AND s.date = CURDATE()
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Teacher Timetable</h1>
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <label for="teacher_select" class="block text-gray-700 mb-2">Select Teacher:</label>
            <select id="teacher_select" class="px-3 py-2 border rounded-md"
                    onchange="window.location.href='?teacher_id='+this.value">
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?= $teacher['teacher_id'] ?>" 
                        <?= $teacher['teacher_id'] == $selected_teacher_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($teacher['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <a href="class_view.php" class="bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 transition duration-200">
                View Class Timetables
            </a>
        </div>
    </div>
    
    <?php if (empty($timetable_data['working_days'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            No timetable data available. Please generate a timetable first.
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">
                <?= htmlspecialchars($teachers[array_search($selected_teacher_id, array_column($teachers, 'teacher_id'))]['full_name']) ?>
            </h2>
            
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
                                        <?php if (isset($teacher_schedule[$day_idx][$period])): ?>
                                            <div class="text-sm">
                                                <div><?= htmlspecialchars($teacher_schedule[$day_idx][$period]['subject_name']) ?></div>
                                                <div class="text-gray-500">
                                                    <?= htmlspecialchars($teacher_schedule[$day_idx][$period]['class_name']) ?> - 
                                                    <?= htmlspecialchars($teacher_schedule[$day_idx][$period]['division_name']) ?>
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
        
        <!-- Today's Substitutions -->
        <?php if (!empty($substitutions)): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Today's Substitutions</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Period</th>
                                <th class="py-2 px-4 border">Type</th>
                                <th class="py-2 px-4 border">Details</th>
                                <th class="py-2 px-4 border">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($substitutions as $sub): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= $sub['period'] ?></td>
                                    <td class="py-2 px-4 border">
                                        <?php if ($sub['original_teacher_id'] == $selected_teacher_id): ?>
                                            <span class="text-red-600">Covering for you</span>
                                        <?php else: ?>
                                            <span class="text-green-600">You're covering</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 border">
                                        <?php if ($sub['original_teacher_id'] == $selected_teacher_id): ?>
                                            <div>Covered by: <?= htmlspecialchars($sub['substitute_teacher_name']) ?></div>
                                        <?php else: ?>
                                            <div>Covering: <?= htmlspecialchars($sub['original_teacher_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($sub['notes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>