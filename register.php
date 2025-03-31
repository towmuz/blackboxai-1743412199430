<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['school_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    $errors = [];
    if (empty($name)) $errors[] = "School name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($password)) $errors[] = "Password is required";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";

    if (empty($errors)) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT school_id FROM schools WHERE admin_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Email already registered";
        } else {
            // Hash password and insert new school
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO schools (name, address, admin_email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $address, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Registration successful! Please login.";
                header('Location: index.php');
                exit;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
    <h2 class="text-2xl font-bold text-center mb-6">School Registration</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <label for="name" class="block text-gray-700 mb-2">School Name*</label>
            <input type="text" id="name" name="name" required
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-4">
            <label for="address" class="block text-gray-700 mb-2">School Address</label>
            <textarea id="address" name="address" rows="3"
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        
        <div class="mb-4">
            <label for="email" class="block text-gray-700 mb-2">Admin Email*</label>
            <input type="email" id="email" name="email" required
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-4">
            <label for="password" class="block text-gray-700 mb-2">Password* (min 8 characters)</label>
            <input type="password" id="password" name="password" required minlength="8"
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-6">
            <label for="confirm_password" class="block text-gray-700 mb-2">Confirm Password*</label>
            <input type="password" id="confirm_password" name="confirm_password" required
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <button type="submit" 
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
            Register School
        </button>
    </form>
    
    <div class="mt-4 text-center">
        <a href="index.php" class="text-blue-600 hover:underline">Already have an account? Login</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>