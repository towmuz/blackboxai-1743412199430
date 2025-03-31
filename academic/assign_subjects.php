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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = (int)$_POST['teacher_id'];
    $subject_id = (int)$_POST['subject_id'];
    $class_id = (int)$_POST['class_id'];
    $division_ids = isset($_POST['division_ids']) ? $_POST['division_ids'] : [];
    $periods_per_week = (int)$_POST['periods_per_week'];

    // Validate inputs
    if ($teacher_id <= 0) {
        $errors[] = "Select a valid teacher";
    }
    if ($subject_id <= 0) {
        $errors[] = "Select a valid subject";
    }
    if ($class_id <= 0) {
        $errors[] = "Select a valid class";
    }
    if (empty($division_ids)) {
        $errors[] = "Select at least one division";
    }
    if ($periods_per_week <= 0) {
        $errors[] = "Periods per week must be at least 1";
    }

    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert assignment for each division
            $stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, division_id, periods_per_week) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($division_ids as $division_id) {
                $stmt->bind_param("iiiii", $teacher_id, $subject_id, $class_id, $division_id, $periods_per_week);
                $stmt->execute();
            }

            $conn->commit();
            $success = "Subject successfully assigned to teacher!";
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to assign subject: " . $e->getMessage();
        }
    }
}

// Get teachers, subjects, classes for dropdowns
$teachers = $conn->query("SELECT teacher_id, full_name FROM teachers WHERE school_id = $school_id ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT subject_id, name FROM subjects WHERE school_id = $school_id ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$classes = $conn->query("SELECT class_id, name FROM classes WHERE school_id = $school_id ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get divisions when class is selected (via AJAX)
$divisions = [];
if (isset($_GET['class_id'])) {
    $class_id = (int)$_GET['class_id'];
    $divisions = $conn->query("SELECT division_id, name FROM divisions WHERE class_id = $class_id ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Assign Subjects to Teachers</h1>
    
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

    <form method="POST">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Teacher Selection -->
                <div>
                    <label for="teacher_id" class="block text-gray-700 mb-2">Teacher*</label>
                    <select id="teacher_id" name="teacher_id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Subject Selection -->
                <div>
                    <label for="subject_id" class="block text-gray-700 mb-2">Subject*</label>
                    <select id="subject_id" name="subject_id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>">
                                <?= htmlspecialchars($subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Class Selection -->
                <div>
                    <label for="class_id" class="block text-gray-700 mb-2">Class*</label>
                    <select id="class_id" name="class_id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="loadDivisions(this.value)">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>">
                                <?= htmlspecialchars($class['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Periods per Week -->
                <div>
                    <label for="periods_per_week" class="block text-gray-700 mb-2">Periods per Week*</label>
                    <input type="number" id="periods_per_week" name="periods_per_week" min="1" max="10" value="3" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Divisions Selection (loaded via AJAX) -->
            <div class="mt-6" id="divisions_container">
                <label class="block text-gray-700 mb-2">Divisions*</label>
                <div id="divisions_list" class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <?php if (!empty($divisions)): ?>
                        <?php foreach ($divisions as $division): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="division_<?= $division['division_id'] ?>" 
                                       name="division_ids[]" value="<?= $division['division_id'] ?>"
                                       class="mr-2">
                                <label for="division_<?= $division['division_id'] ?>"><?= $division['name'] ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500">Select a class to view divisions</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Assign Subject
                </button>
            </div>
        </div>
    </form>

    <!-- Current Assignments Table -->
    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Current Assignments</h2>
        <?php
        $assignments = $conn->query("
            SELECT ts.*, 
                   t.full_name AS teacher_name,
                   s.name AS subject_name,
                   c.name AS class_name,
                   d.name AS division_name
            FROM teacher_subjects ts
            JOIN teachers t ON ts.teacher_id = t.teacher_id
            JOIN subjects s ON ts.subject_id = s.subject_id
            JOIN classes c ON ts.class_id = c.class_id
            JOIN divisions d ON ts.division_id = d.division_id
            WHERE t.school_id = $school_id
            ORDER BY t.full_name, c.name, d.name
        ")->fetch_all(MYSQLI_ASSOC);
        ?>

        <?php if (empty($assignments)): ?>
            <p class="text-gray-500">No assignments yet</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2 px-4 border">Teacher</th>
                            <th class="py-2 px-4 border">Subject</th>
                            <th class="py-2 px-4 border">Class/Division</th>
                            <th class="py-2 px-4 border">Periods/Week</th>
                            <th class="py-2 px-4 border">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border"><?= htmlspecialchars($assignment['teacher_name']) ?></td>
                                <td class="py-2 px-4 border"><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                <td class="py-2 px-4 border">
                                    <?= htmlspecialchars($assignment['class_name']) ?> - 
                                    <?= htmlspecialchars($assignment['division_name']) ?>
                                </td>
                                <td class="py-2 px-4 border"><?= $assignment['periods_per_week'] ?></td>
                                <td class="py-2 px-4 border">
                                    <a href="#" class="text-blue-500 hover:underline">Edit</a>
                                    <span class="mx-2">|</span>
                                    <a href="#" class="text-red-500 hover:underline">Remove</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function loadDivisions(classId) {
    if (!classId) {
        document.getElementById('divisions_list').innerHTML = 
            '<p class="text-gray-500">Select a class to view divisions</p>';
        return;
    }

    fetch(`get_divisions.php?class_id=${classId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('divisions_list').innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('divisions_list').innerHTML = 
                '<p class="text-red-500">Error loading divisions</p>';
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>