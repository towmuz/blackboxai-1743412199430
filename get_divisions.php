<?php
require_once 'includes/db.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if class_id is provided
if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request');
}

$class_id = (int)$_GET['class_id'];
$school_id = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;

// Verify the class belongs to the school
$stmt = $conn->prepare("SELECT class_id FROM classes WHERE class_id = ? AND school_id = ?");
$stmt->bind_param("ii", $class_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized access');
}

// Get divisions for the selected class
$divisions = [];
$stmt = $conn->prepare("SELECT division_id, name FROM divisions WHERE class_id = ? ORDER BY name");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

// Generate HTML for division checkboxes
$html = '';
while ($division = $result->fetch_assoc()) {
    $html .= '<div class="flex items-center">';
    $html .= '<input type="checkbox" id="division_' . $division['division_id'] . '" ';
    $html .= 'name="division_ids[]" value="' . $division['division_id'] . '" class="mr-2">';
    $html .= '<label for="division_' . $division['division_id'] . '">' . htmlspecialchars($division['name']) . '</label>';
    $html .= '</div>';
}

if (empty($html)) {
    $html = '<p class="text-gray-500">No divisions found for this class</p>';
}

echo $html;
?>