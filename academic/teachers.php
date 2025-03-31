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
    $full_name = trim($_POST['full_name']);
    $short_name = trim($_POST['short_name']);
    $designation = trim($_POST['designation']);
    $max_periods_day = (int)$_POST['max_periods_day'];
    $max_periods_week = (int)$_POST['max_periods_week'];
    $unavailable_days = isset($_POST['unavailable_days']) ? $_POST['unavailable_days'] : [];
    $unavailable_periods = isset($_POST['unavailable_periods']) ? $_POST['unavailable_periods'] : [];

    // Validate inputs
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    if (empty($short_name) || strlen($short_name) < 3 || strlen($short_name) > 4) {
        $errors[] = "Short name must be 3-4 characters";
    }
    if ($max_periods_day < 1 || $max_periods_day > 8) {
        $errors[] = "Max periods per day must be between 1-8";
    }
    if ($max_periods_week < $max_periods_day || $max_periods_week > 40) {
        $errors[] = "Max periods per week must be reasonable";
    }

    if (empty($errors)) {
        // Serialize unavailability data
        $unavailability = json_encode([
            'days' => $unavailable_days,
            'periods' => $unavailable_periods
        ]);

        // Insert teacher
        $stmt = $conn->prepare("INSERT INTO teachers (school_id, full_name, short_name, designation, max_periods_day, max_periods_week, unavailability) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiis", $school_id, $full_name, $short_name, $designation, $max_periods_day, $max_periods_week, $unavailability);
        
        if ($stmt->execute()) {
            $success = "Teacher added successfully!";
        } else {
            $errors[] = "Failed to add teacher: " . $conn->error;
        }
    }
}

// Get existing teachers
$teachers = [];
$result = $conn->query("SELECT * FROM teachers WHERE school_id = $school_id ORDER BY full_name");
if ($result) {
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
}

// Get week days for unavailability selection
$week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Teacher Management</h1>
    
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
        <!-- Add New Teacher Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Add New Teacher</h2>
            <form method="POST">
                <div class="mb-4">
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name*</label>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="short_name" class="block text-gray-700 mb-2">Short Name (3-4 chars)*</label>
                    <input type="text" id="short_name" name="short_name" minlength="3" maxlength="4" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="designation" class="block text-gray-700 mb-2">Designation</label>
                    <input type="text" id="designation" name="designation"
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="max_periods_day" class="block text-gray-700 mb-2">Max Periods/Day*</label>
                        <input type="number" id="max_periods_day" name="max_periods_day" min="1" max="8" value="4" required
                               class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="max_periods_week" class="block text-gray-700 mb-2">Max Periods/Week*</label>
                        <input type="number" id="max_periods_week" name="max_periods_week" min="1" max="40" value="20" required
                               class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Unavailable Days</label>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ($week_days as $day): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="unavailable_<?= strtolower($day) ?>" name="unavailable_days[]" value="<?= $day ?>"
                                       class="mr-2">
                                <label for="unavailable_<?= strtolower($day) ?>"><?= $day ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Unavailable Periods</label>
                    <div class="grid grid-cols-4 gap-2">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="unavailable_period_<?= $i ?>" name="unavailable_periods[]" value="<?= $i ?>"
                                       class="mr-2">
                                <label for="unavailable_period_<?= $i ?>">Period <?= $i ?></label>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Add Teacher
                </button>
            </form>
        </div>

        <!-- Existing Teachers -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Existing Teachers</h2>
            <?php if (empty($teachers)): ?>
                <p class="text-gray-500">No teachers added yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Name</th>
                                <th class="py-2 px-4 border">Short</th>
                                <th class="py-2 px-4 border">Designation</th>
                                <th class="py-2 px-4 border">Max Load</th>
                                <th class="py-2 px-4 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): 
                                $unavailability = json_decode($teacher['unavailability'], true);
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($teacher['full_name']) ?></td>
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($teacher['short_name']) ?></td>
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($teacher['designation']) ?></td>
                                    <td class="py-2 px-4 border">
                                        <?= $teacher['max_periods_day'] ?>/day<br>
                                        <?= $teacher['max_periods_week'] ?>/week
                                    </td>
                                    <td class="py-2 px-4 border">
                                        <a href="#" class="text-blue-500 hover:underline">Edit</a>
                                        <span class="mx-2">|</span>
                                        <a href="#" class="text-red-500 hover:underline">Delete</a>
                                    </td>
                                </tr>
                                <?php if (!empty($unavailability['days']) || !empty($unavailability['periods'])): ?>
                                <tr>
                                    <td colspan="5" class="py-1 px-4 border text-sm text-gray-600">
                                        Unavailable: 
                                        <?= !empty($unavailability['days']) ? 'Days: ' . implode(', ', $unavailability['days']) : '' ?>
                                        <?= !empty($unavailability['periods']) ? 'Periods: ' . implode(', ', $unavailability['periods']) : '' ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>