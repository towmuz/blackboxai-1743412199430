<?php
require_once '../includes/db.php';
require_once '../includes/header.php';

// Check authentication
if (!isset($_SESSION['school_id'])) {
    header('Location: ../index.php');
    exit;
}

$school_id = $_SESSION['school_id'];

// Get all classes and teachers for selection
$classes = $conn->query("
    SELECT c.class_id, c.name AS class_name, d.division_id, d.name AS division_name
    FROM classes c
    JOIN divisions d ON c.class_id = d.class_id
    WHERE c.school_id = $school_id
    ORDER BY c.name, d.name
")->fetch_all(MYSQLI_ASSOC);

$teachers = $conn->query("
    SELECT teacher_id, full_name 
    FROM teachers 
    WHERE school_id = $school_id
    ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mx-auto">
    <h1 class="text-2xl font-bold mb-6">Export Timetable</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Class Timetable Export -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Export Class Timetable</h2>
            <form action="export_pdf.php" method="GET">
                <input type="hidden" name="type" value="class">
                
                <div class="mb-4">
                    <label for="class_id" class="block text-gray-700 mb-2">Select Class:</label>
                    <select id="class_id" name="id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>">
                                <?= htmlspecialchars($class['class_name']) ?> - <?= htmlspecialchars($class['division_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" 
                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200">
                    Export as PDF
                </button>
            </form>
        </div>
        
        <!-- Teacher Timetable Export -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Export Teacher Timetable</h2>
            <form action="export_pdf.php" method="GET">
                <input type="hidden" name="type" value="teacher">
                
                <div class="mb-4">
                    <label for="teacher_id" class="block text-gray-700 mb-2">Select Teacher:</label>
                    <select id="teacher_id" name="id" required
                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" 
                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200">
                    Export as PDF
                </button>
            </form>
        </div>
    </div>
    
    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Export Options</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="export_pdf.php?type=all_classes" 
               class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 text-center">
                Export All Classes
            </a>
            <a href="export_pdf.php?type=all_teachers" 
               class="bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200 text-center">
                Export All Teachers
            </a>
            <a href="class_view.php?export=csv" 
               class="bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition duration-200 text-center">
                Export as CSV
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>