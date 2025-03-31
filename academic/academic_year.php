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
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $days = isset($_POST['days']) ? $_POST['days'] : [];
    
    // Validate inputs
    if (empty($start_date) || empty($end_date)) {
        $errors[] = "Start date and end date are required";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "End date must be after start date";
    } elseif (count($days) < 1) {
        $errors[] = "Select at least one working day";
    }

    if (empty($errors)) {
        // Convert days array to comma-separated string
        $days_str = implode(',', $days);
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO academic_years (school_id, start_date, end_date, days_week) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $school_id, $start_date, $end_date, $days_str);
        
        if ($stmt->execute()) {
            $success = "Academic year created successfully!";
        } else {
            $errors[] = "Failed to create academic year: " . $conn->error;
        }
    }
}

// Get existing academic years
$academic_years = [];
$result = $conn->query("SELECT * FROM academic_years WHERE school_id = $school_id ORDER BY start_date DESC");
if ($result) {
    $academic_years = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Academic Year Management</h1>
    
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
        <!-- Create New Academic Year Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Create New Academic Year</h2>
            <form method="POST">
                <div class="mb-4">
                    <label for="start_date" class="block text-gray-700 mb-2">Start Date</label>
                    <input type="date" id="start_date" name="start_date" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="end_date" class="block text-gray-700 mb-2">End Date</label>
                    <input type="date" id="end_date" name="end_date" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Working Days</label>
                    <div class="grid grid-cols-3 gap-2">
                        <?php 
                        $week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($week_days as $day): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="day_<?= strtolower($day) ?>" name="days[]" value="<?= $day ?>"
                                       class="mr-2">
                                <label for="day_<?= strtolower($day) ?>"><?= $day ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Create Academic Year
                </button>
            </form>
        </div>

        <!-- Existing Academic Years -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Existing Academic Years</h2>
            <?php if (empty($academic_years)): ?>
                <p class="text-gray-500">No academic years created yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Start Date</th>
                                <th class="py-2 px-4 border">End Date</th>
                                <th class="py-2 px-4 border">Working Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($academic_years as $year): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= date('d M Y', strtotime($year['start_date'])) ?></td>
                                    <td class="py-2 px-4 border"><?= date('d M Y', strtotime($year['end_date'])) ?></td>
                                    <td class="py-2 px-4 border"><?= str_replace(',', ', ', $year['days_week']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>