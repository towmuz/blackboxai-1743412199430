<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['school_id'])) {
    header('Location: index.php');
    exit;
}

// Get school information
$school_id = $_SESSION['school_id'];
$stmt = $conn->prepare("SELECT name FROM schools WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
?>

<div class="container mx-auto">
    <h1 class="text-3xl font-bold mb-6">Welcome, <?= htmlspecialchars($school['name']) ?></h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Academic Management Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold mb-4 text-blue-600">
                <i class="fas fa-graduation-cap mr-2"></i>Academic Management
            </h2>
            <ul class="space-y-2">
                <li><a href="academic/academic_year.php" class="text-blue-500 hover:underline">Academic Year</a></li>
                <li><a href="academic/classes.php" class="text-blue-500 hover:underline">Classes & Divisions</a></li>
                <li><a href="academic/teachers.php" class="text-blue-500 hover:underline">Teachers</a></li>
                <li><a href="academic/subjects.php" class="text-blue-500 hover:underline">Subjects</a></li>
            </ul>
        </div>

        <!-- Timetable Management Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold mb-4 text-green-600">
                <i class="fas fa-calendar-alt mr-2"></i>Timetable Management
            </h2>
            <ul class="space-y-2">
                <li><a href="timetable/generate.php" class="text-blue-500 hover:underline">Generate Timetable</a></li>
                <li><a href="timetable/edit.php" class="text-blue-500 hover:underline">Edit Timetable</a></li>
                <li><a href="timetable/class_view.php" class="text-blue-500 hover:underline">View Timetables</a></li>
            </ul>
        </div>

        <!-- Reports & Substitutions Card -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
            <h2 class="text-xl font-semibold mb-4 text-purple-600">
                <i class="fas fa-file-export mr-2"></i>Reports & Substitutions
            </h2>
            <ul class="space-y-2">
                <li><a href="exports/export_csv.php" class="text-blue-500 hover:underline">Export to CSV</a></li>
                <li><a href="exports/export_pdf.php" class="text-blue-500 hover:underline">Export to PDF</a></li>
                <li><a href="substitutions/manage_substitution.php" class="text-blue-500 hover:underline">Manage Substitutions</a></li>
            </ul>
        </div>
    </div>

    <!-- Quick Stats Section -->
    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Quick Stats</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            // Get counts for various entities
            $queries = [
                'Academic Years' => "SELECT COUNT(*) FROM academic_years WHERE school_id = $school_id",
                'Classes' => "SELECT COUNT(*) FROM classes WHERE school_id = $school_id",
                'Teachers' => "SELECT COUNT(*) FROM teachers WHERE school_id = $school_id",
                'Subjects' => "SELECT COUNT(*) FROM subjects WHERE school_id = $school_id"
            ];

            foreach ($queries as $label => $query) {
                $result = $conn->query($query);
                $count = $result->fetch_row()[0];
                echo "
                <div class='bg-gray-100 p-4 rounded-lg text-center'>
                    <div class='text-3xl font-bold text-blue-600'>$count</div>
                    <div class='text-gray-600'>$label</div>
                </div>";
            }
            ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>