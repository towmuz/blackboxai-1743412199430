<?php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['school_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check credentials
        $stmt = $conn->prepare("SELECT school_id, name, password FROM schools WHERE admin_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $school = $result->fetch_assoc();
            if (password_verify($password, $school['password'])) {
                // Set session variables
                $_SESSION['school_id'] = $school['school_id'];
                $_SESSION['school_name'] = $school['name'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
    <h2 class="text-2xl font-bold text-center mb-6">School Login</h2>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-4">
            <label for="email" class="block text-gray-700 mb-2">Admin Email</label>
            <input type="email" id="email" name="email" required
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div class="mb-6">
            <label for="password" class="block text-gray-700 mb-2">Password</label>
            <input type="password" id="password" name="password" required
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <button type="submit" 
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
            Login
        </button>
    </form>
    
    <div class="mt-4 text-center">
        <a href="register.php" class="text-blue-600 hover:underline">Register a new school</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>