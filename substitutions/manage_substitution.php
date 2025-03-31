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
    $date = $_POST['date'];
    $day = $_POST['day'];
    $period = (int)$_POST['period'];
    $original_teacher_id = (int)$_POST['original_teacher_id'];
    $substitute_teacher_id = (int)$_POST['substitute_teacher_id'];
    $notes = trim($_POST['notes']);

    // Validate inputs
    if (empty($date) || empty($day) || $period < 1 || $original_teacher_id <= 0 || $substitute_teacher_id <= 0) {
        $errors[] = "Please fill all required fields";
    } elseif ($original_teacher_id === $substitute_teacher_id) {
        $errors[] = "Substitute teacher must be different from original teacher";
    }

    if (empty($errors)) {
        // Insert substitution
        $stmt = $conn->prepare("INSERT INTO substitutions (school_id, original_teacher_id, substitute_teacher_id, date, day, period, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissis", $school_id, $original_teacher_id, $substitute_teacher_id, $date, $day, $period, $notes);
        
        if ($stmt->execute()) {
            $success = "Substitution recorded successfully!";
        } else {
            $errors[] = "Failed to record substitution: " . $conn->error;
        }
    }
}

// Get teachers for dropdowns
$teachers = $conn->query("
    SELECT teacher_id, full_name 
    FROM teachers 
    WHERE school_id = $school_id
    ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);

// Get existing substitutions
$substitutions = $conn->query("
    SELECT s.*, 
           ot.full_name AS original_teacher_name,
           st.full_name AS substitute_teacher_name
    FROM substitutions s
    JOIN teachers ot ON s.original_teacher_id = ot.teacher_id
    JOIN teachers st ON s.substitute_teacher_id = st.teacher_id
    WHERE s.school_id = $school_id
    ORDER BY s.date DESC, s.period
")->fetch_all(MYSQLI_ASSOC);

// Get working days from academic year
$working_days = [];
$academic_year = $conn->query("
    SELECT days_week FROM academic_years 
    WHERE school_id = $school_id 
    ORDER BY start_date DESC LIMIT 1
")->fetch_assoc();

if ($academic_year) {
    $working_days = explode(',', $academic_year['days_week']);
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Manage Substitutions</h1>
    
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Add New Substitution Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Record New Substitution</h2>
            <form method="POST">
                <div class="mb-4">
                    <label for="date" class="block text-gray-700 mb-2">Date*</label>
                    <input type="date" id="date" name="date" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="updateDay()">
                </div>
                
                <div class="mb-4">
                    <label for="day" class="block text-gray-700 mb-2">Day*</label>
                    <select id="day" name="day" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Day --</option>
                        <?php foreach ($working_days as $day): ?>
                            <option value="<?= $day ?>"><?= $day ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="period" class="block text-gray-700 mb-2">Period*</label>
                    <select id="period" name="period" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>">Period <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="original_teacher_id" class="block text-gray-700 mb-2">Original Teacher*</label>
                    <select id="original_teacher_id" name="original_teacher_id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="substitute_teacher_id" class="block text-gray-700 mb-2">Substitute Teacher*</label>
                    <select id="substitute_teacher_id" name="substitute_teacher_id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="notes" class="block text-gray-700 mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Record Substitution
                </button>
            </form>
        </div>

        <!-- Existing Substitutions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Upcoming Substitutions</h2>
            <?php if (empty($substitutions)): ?>
                <p class="text-gray-500">No substitutions recorded yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Date</th>
                                <th class="py-2 px-4 border">Day</th>
                                <th class="py-2 px-4 border">Period</th>
                                <th class="py-2 px-4 border">Original</th>
                                <th class="py-2 px-4 border">Substitute</th>
                                <th class="py-2 px-4 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($substitutions as $sub): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= date('d M Y', strtotime($sub['date'])) ?></td>
                                    <td class="py-2 px-4 border"><?= $sub['day'] ?></td>
                                    <td class="py-2 px-4 border"><?= $sub['period'] ?></td>
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($sub['original_teacher_name']) ?></td>
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($sub['substitute_teacher_name']) ?></td>
                                    <td class="py-2 px-4 border">
                                        <a href="#" class="text-blue-500 hover:underline">Edit</a>
                                        <span class="mx-2">|</span>
                                        <a href="#" class="text-red-500 hover:underline">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateDay() {
    const dateInput = document.getElementById('date');
    const daySelect = document.getElementById('day');
    
    if (dateInput.value) {
        const date = new Date(dateInput.value);
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const dayName = days[date.getDay()];
        
        // Set the day select value if it matches a working day
        for (let i = 0; i < daySelect.options.length; i++) {
            if (daySelect.options[i].value === dayName) {
                daySelect.value = dayName;
                break;
            }
        }
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>