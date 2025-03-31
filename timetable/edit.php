<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Check authentication and existing timetable data
if (!isset($_SESSION['school_id']) || !isset($_SESSION['timetable_data'])) {
    header('Location: ../index.php');
    exit;
}

$school_id = $_SESSION['school_id'];
$timetable_data = $_SESSION['timetable_data'];
$working_days = $timetable_data['working_days'];
$class_schedule = $timetable_data['class_schedule'];

// Get all teachers for the buffer
$teachers = $conn->query("
    SELECT teacher_id, full_name, short_name 
    FROM teachers WHERE school_id = $school_id
")->fetch_all(MYSQLI_ASSOC);

// Get all subjects for the buffer
$subjects = $conn->query("
    SELECT s.subject_id, s.name, s.class_id,
           c.name AS class_name, d.name AS division_name
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.class_id
    LEFT JOIN divisions d ON s.class_id = d.class_id
    WHERE s.school_id = $school_id
")->fetch_all(MYSQLI_ASSOC);

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $class_id = (int)$_POST['class_id'];
        $division_id = (int)$_POST['division_id'];
        $day_idx = (int)$_POST['day_idx'];
        $period = (int)$_POST['period'];
        $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        
        $class_key = $class_id.'_'.$division_id;
        
        if ($_POST['action'] === 'update') {
            // Update the timetable entry
            $timetable_data['class_schedule'][$class_key][$day_idx][$period] = [
                'subject_id' => $subject_id,
                'teacher_id' => $teacher_id,
                'subject_name' => $_POST['subject_name'],
                'teacher_name' => $_POST['teacher_name']
            ];
            
            $_SESSION['timetable_data'] = $timetable_data;
            echo json_encode(['success' => true]);
        } 
        elseif ($_POST['action'] === 'clear') {
            // Clear the timetable entry
            unset($timetable_data['class_schedule'][$class_key][$day_idx][$period]);
            $_SESSION['timetable_data'] = $timetable_data;
            echo json_encode(['success' => true]);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Timetable</h1>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Drag and Drop Editor</h2>
            <div>
                <button id="save_btn" class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200">
                    Save Changes
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Timetable Display -->
            <div class="lg:col-span-3">
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
                                        <?php foreach ($working_days as $day): ?>
                                            <th class="py-2 px-4 border"><?= $day ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($period = 1; $period <= 8; $period++): ?>
                                        <tr>
                                            <td class="py-2 px-4 border font-medium">Period <?= $period ?></td>
                                            <?php foreach ($working_days as $day_idx => $day): ?>
                                                <td class="py-2 px-4 border timetable-cell" 
                                                    data-class-id="<?= $class_id ?>"
                                                    data-division-id="<?= $division_id ?>"
                                                    data-day-idx="<?= $day_idx ?>"
                                                    data-period="<?= $period ?>"
                                                    ondragover="allowDrop(event)"
                                                    ondrop="drop(event)">
                                                    <?php if (isset($days[$day_idx][$period])): ?>
                                                        <div class="timetable-entry bg-blue-100 p-2 rounded cursor-move" 
                                                             draggable="true" 
                                                             ondragstart="drag(event)"
                                                             data-subject-id="<?= $days[$day_idx][$period]['subject_id'] ?>"
                                                             data-teacher-id="<?= $days[$day_idx][$period]['teacher_id'] ?>"
                                                             data-subject-name="<?= htmlspecialchars($days[$day_idx][$period]['subject_name']) ?>"
                                                             data-teacher-name="<?= htmlspecialchars($days[$day_idx][$period]['teacher_name']) ?>">
                                                            <div class="text-sm">
                                                                <div><?= htmlspecialchars($days[$day_idx][$period]['subject_name']) ?></div>
                                                                <div class="text-gray-500"><?= htmlspecialchars($days[$day_idx][$period]['teacher_name']) ?></div>
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
            
            <!-- Buffer/Unassigned Items -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-medium mb-4">Unassigned Items</h3>
                
                <div class="mb-6">
                    <h4 class="font-medium mb-2">Teachers</h4>
                    <div class="grid grid-cols-1 gap-2" id="teacher_buffer">
                        <?php foreach ($teachers as $teacher): ?>
                            <div class="buffer-item bg-green-100 p-2 rounded cursor-move" 
                                 draggable="true"
                                 data-teacher-id="<?= $teacher['teacher_id'] ?>"
                                 data-teacher-name="<?= htmlspecialchars($teacher['full_name']) ?>"
                                 ondragstart="drag(event)">
                                <?= htmlspecialchars($teacher['short_name']) ?> (<?= htmlspecialchars($teacher['full_name']) ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium mb-2">Subjects</h4>
                    <div class="grid grid-cols-1 gap-2" id="subject_buffer">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="buffer-item bg-purple-100 p-2 rounded cursor-move" 
                                 draggable="true"
                                 data-subject-id="<?= $subject['subject_id'] ?>"
                                 data-subject-name="<?= htmlspecialchars($subject['name']) ?>"
                                 ondragstart="drag(event)">
                                <?= htmlspecialchars($subject['name']) ?>
                                <?php if ($subject['class_name']): ?>
                                    <span class="text-xs text-gray-500">(<?= htmlspecialchars($subject['class_name']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium mb-2">Empty Slot</h4>
                    <div class="buffer-item bg-red-100 p-2 rounded cursor-move" 
                         draggable="true"
                         data-clear="true"
                         ondragstart="drag(event)">
                        Clear Period
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Drag and Drop functions
function allowDrop(ev) {
    ev.preventDefault();
}

function drag(ev) {
    ev.dataTransfer.setData("text", ev.target.id);
    // Store all relevant data in dataTransfer
    const item = ev.target;
    const data = {
        subjectId: item.getAttribute('data-subject-id'),
        teacherId: item.getAttribute('data-teacher-id'),
        subjectName: item.getAttribute('data-subject-name'),
        teacherName: item.getAttribute('data-teacher-name'),
        isClear: item.getAttribute('data-clear') === 'true'
    };
    ev.dataTransfer.setData('application/json', JSON.stringify(data));
}

function drop(ev) {
    ev.preventDefault();
    const data = JSON.parse(ev.dataTransfer.getData('application/json'));
    const cell = ev.target.closest('.timetable-cell');
    
    if (data.isClear) {
        // Clear the cell
        cell.innerHTML = '';
        updateTimetable(cell, null, null, null, null);
    } else {
        // Create or update the entry
        const entryHtml = `
            <div class="timetable-entry bg-blue-100 p-2 rounded cursor-move" 
                 draggable="true" 
                 ondragstart="drag(event)"
                 data-subject-id="${data.subjectId || ''}"
                 data-teacher-id="${data.teacherId || ''}"
                 data-subject-name="${data.subjectName || ''}"
                 data-teacher-name="${data.teacherName || ''}">
                <div class="text-sm">
                    ${data.subjectName ? `<div>${data.subjectName}</div>` : ''}
                    ${data.teacherName ? `<div class="text-gray-500">${data.teacherName}</div>` : ''}
                </div>
            </div>
        `;
        cell.innerHTML = entryHtml;
        updateTimetable(cell, data.subjectId, data.teacherId, data.subjectName, data.teacherName);
    }
}

function updateTimetable(cell, subjectId, teacherId, subjectName, teacherName) {
    const classId = cell.getAttribute('data-class-id');
    const divisionId = cell.getAttribute('data-division-id');
    const dayIdx = cell.getAttribute('data-day-idx');
    const period = cell.getAttribute('data-period');
    
    fetch('edit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: subjectId ? 'update' : 'clear',
            class_id: classId,
            division_id: divisionId,
            day_idx: dayIdx,
            period: period,
            subject_id: subjectId || '',
            teacher_id: teacherId || '',
            subject_name: subjectName || '',
            teacher_name: teacherName || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error updating timetable: ' + (data.error || 'Unknown error'));
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating timetable');
        location.reload();
    });
}

// Save button handler
document.getElementById('save_btn').addEventListener('click', function() {
    alert('Timetable changes saved successfully!');
    window.location.href = 'generate.php';
});
</script>

<?php require_once '../includes/footer.php'; ?>