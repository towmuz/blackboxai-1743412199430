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
    $class_name = trim($_POST['class_name']);
    $divisions = (int)$_POST['divisions'];
    
    // Validate inputs
    if (empty($class_name)) {
        $errors[] = "Class name is required";
    }
    if ($divisions < 1) {
        $errors[] = "Number of divisions must be at least 1";
    }

    if (empty($errors)) {
        // Insert class
        $stmt = $conn->prepare("INSERT INTO classes (school_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $school_id, $class_name);
        
        if ($stmt->execute()) {
            $class_id = $conn->insert_id;
            
            // Insert divisions (A, B, C, etc.)
            $stmt = $conn->prepare("INSERT INTO divisions (class_id, name) VALUES (?, ?)");
            for ($i = 0; $i < $divisions; $i++) {
                $div_name = chr(65 + $i); // A, B, C, etc.
                $stmt->bind_param("is", $class_id, $div_name);
                $stmt->execute();
            }
            
            $success = "Class and divisions created successfully!";
        } else {
            $errors[] = "Failed to create class: " . $conn->error;
        }
    }
}

// Get existing classes
$classes = [];
$result = $conn->query("
    SELECT c.class_id, c.name, COUNT(d.division_id) as division_count 
    FROM classes c
    LEFT JOIN divisions d ON c.class_id = d.class_id
    WHERE c.school_id = $school_id
    GROUP BY c.class_id, c.name
    ORDER BY c.name
");
if ($result) {
    $classes = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Class Management</h1>
    
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
        <!-- Add New Class Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Add New Class</h2>
            <form method="POST">
                <div class="mb-4">
                    <label for="class_name" class="block text-gray-700 mb-2">Class Name*</label>
                    <input type="text" id="class_name" name="class_name" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="divisions" class="block text-gray-700 mb-2">Number of Divisions*</label>
                    <input type="number" id="divisions" name="divisions" min="1" value="1" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                    Add Class
                </button>
            </form>
        </div>

        <!-- Existing Classes -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Existing Classes</h2>
            <?php if (empty($classes)): ?>
                <p class="text-gray-500">No classes created yet</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Class Name</th>
                                <th class="py-2 px-4 border">Divisions</th>
                                <th class="py-2 px-4 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-4 border"><?= htmlspecialchars($class['name']) ?></td>
                                    <td class="py-2 px-4 border"><?= $class['division_count'] ?></td>
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