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
    $subject_name = trim($_POST['subject_name']);
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $is_elective = isset($_POST['is_elective']) ? 1 : 0;

    // Validate inputs
    if (empty($subject_name)) {
        $errors[] = "Subject name is required";
    }
    if ($class_id < 0) {
        $errors[] = "Invalid class selection";
    }

    if (empty($errors)) {
        // Insert subject
        $stmt = $conn->prepare("INSERT INTO subjects (school_id, name, class_id, is_elective) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $school_id, $subject_name, $class_id, $is_elective);
        
        if ($stmt->execute()) {
            $success = "Subject added successfully!";
        } else {
            $errors[] = "Failed to add subject: " . $conn->error;
        }
    }
}

// Get existing subjects
$subjects = [];
$result = $conn->query("
    SELECT s.subject_id, s.name, s.is_elective, 
           c.name AS class_name, c.class_id
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.class_id
    WHERE s.school_id = $school_id
    ORDER BY c.name, s.name
");
if ($result) {
    $subjects = $result->fetch_all(MYSQLI_ASSOC);
}

// Get classes for dropdown
$classes = [];
$class_result = $conn->query("SELECT class_id, name FROM classes WHERE school_id = $school_id ORDER BY name");
if ($class_result) {
    $classes = $class_result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Subject Management</h1>
    
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
        <!-- Add New Subject Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Add New Subject</h2>
            <form method="POST">
                <div class="mb-4">
                    <label for="subject_name" class="block text-gray-700 mb-2">Subject Name*</label>
                    <input type="text" id="subject_name" name="subject_name" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="class_id" class="block text-gray-700 mb-2">Assigned Class (optional)</label>
                    <select id="class_id" name="class_id"
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">-- General Subject --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4 flex items-center">
                    <input type="checkbox" id="is_elective" name="is_elective"
                           class="mr-2">
                    <label for="is_elective">Elective Subject</label>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Add Subject
                </button>
            </form>
        </div>

        <!-- Existing Subjects -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Existing Subjects</h2>
            <?php if (empty($subjects)): ?>
                <p class="text-gray-500">No subjects added yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Subject</th>
                                <th class="py-2 px-4 border">Class</th>
                                <th class="py-2 px-4 border">Type</th>
                                <th class="py-2 px-4 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($subject['name']) ?></td>
                                    <td class="py-2 px-4 border"><?= $subject['class_name'] ?? 'General' ?></td>
                                    <td class="py-2 px-4 border"><?= $subject['is_elective'] ? 'Elective' : 'Core' ?></td>
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

<?php require_once '../includes/footer.php'; ?>