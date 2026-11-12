<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "student_portal_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";
$form_mode = "login"; // login or register

// Handle Registration
if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email is already registered!";
        $form_mode = "register";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $password);
        if ($stmt->execute()) {
            $success = "Registration successful! Please sign in.";
        } else {
            $error = "Error: " . $stmt->error;
            $form_mode = "register";
        }
        $stmt->close();
    }
    $check->close();
}

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['loginEmail'];
    $password = $_POST['loginPassword'];

    $stmt = $conn->prepare("SELECT name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name'] = $row['name'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with that email!";
    }
    $stmt->close();
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Handle CRUD: Create / Update Subject
if (isset($_POST['save_subject']) && isset($_SESSION['user_email'])) {
    $code = $_POST['subjectCode'];
    $title = $_POST['subjectTitle'];
    $units = $_POST['units'];
    $sub_id = $_POST['sub_id'];
    $email = $_SESSION['user_email'];

    if ($sub_id == "") {
        // Create
        $stmt = $conn->prepare("INSERT INTO subjects (user_email, code, title, units) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $email, $code, $title, $units);
        $stmt->execute();
        $stmt->close();
    } else {
        // Update
        $stmt = $conn->prepare("UPDATE subjects SET code = ?, title = ?, units = ? WHERE id = ? AND user_email = ?");
        $stmt->bind_param("sssis", $code, $title, $units, $sub_id, $email);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Handle CRUD: Delete Subject
if (isset($_GET['delete']) && isset($_SESSION['user_email'])) {
    $sub_id = $_GET['delete'];
    $email = $_SESSION['user_email'];
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND user_email = ?");
    $stmt->bind_param("is", $sub_id, $email);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// Edit Fetch
$edit_id = "";
$edit_code = "";
$edit_title = "";
$edit_units = "";
if (isset($_GET['edit']) && isset($_SESSION['user_email'])) {
    $edit_id = $_GET['edit'];
    $email = $_SESSION['user_email'];
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND user_email = ?");
    $stmt->bind_param("is", $edit_id, $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $edit_code = $row['code'];
        $edit_title = $row['title'];
        $edit_units = $row['units'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Portal - XAMPP PHP</title>
    <style>
        * { box-sizing: border-box; font-family: Arial, sans-serif; }
        body {
            margin: 0;
            background: linear-gradient(135deg, #1565c0, #64b5f6);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px #333;
            text-align: center;
        }
        .dashboard-container { max-width: 750px; text-align: left; }
        h2, h3 { color: #1565c0; margin-top: 0; }
        input {
            width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ccc; border-radius: 7px;
        }
        button {
            width: 100%; padding: 12px; background: #1565c0; color: white; border: none; border-radius: 7px; cursor: pointer; margin-top: 10px; font-weight: bold;
        }
        button:hover { background: #0d47a1; }
        .btn-danger { background: #d32f2f; width: auto; padding: 6px 12px; font-size: 12px; }
        .btn-danger:hover { background: #b71c1c; }
        .btn-warning { background: #f57c00; width: auto; padding: 6px 12px; font-size: 12px; text-decoration: none; color: white; display: inline-block; border-radius: 4px;}
        .btn-warning:hover { background: #e65100; }
        p { font-size: 14px; }
        a { color: #1565c0; cursor: pointer; text-decoration: underline; }
        .hidden { display: none; }
        .error { color: #d32f2f; font-size: 13px; margin-bottom: 10px; }
        .success { color: #2e7d32; font-size: 13px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        th { background-color: #1565c0; color: white; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; }
        .header-flex button { width: auto; padding: 8px 15px; margin: 0; }
    </style>
</head>
<body>

<div class="container <?php echo isset($_SESSION['user_email']) ? 'dashboard-container' : ''; ?>">

    <?php if (!isset($_SESSION['user_email'])): ?>
        
        <!-- MESSAGES -->
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>

        <!-- LOGIN FORM -->
        <div id="loginForm" class="<?php echo ($form_mode == 'register') ? 'hidden' : ''; ?>">
            <h2>Student Portal</h2>
            <h3>Sign In</h3>
            <form method="POST">
                <input type="email" name="loginEmail" placeholder="Email Address" required>
                <input type="password" name="loginPassword" placeholder="Password" required>
                <button type="submit" name="login">Sign In</button>
            </form>
            <p>Don't have an account? <a onclick="toggleForm('register')">Register</a></p>
        </div>

        <!-- REGISTER FORM -->
        <div id="registerForm" class="<?php echo ($form_mode == 'register') ? '' : 'hidden'; ?>">
            <h2>Create Account</h2>
            <form method="POST">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Create Password" minlength="6" required>
                <button type="submit" name="register">Register</button>
            </form>
            <p>Already have an account? <a onclick="toggleForm('login')">Sign In</a></p>
        </div>

    <?php else: ?>
        
        <!-- DASHBOARD & CRUD -->
        <div class="header-flex">
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
            <a href="index.php?logout=true"><button class="btn-danger" style="color:white; border:none; border-radius:7px; cursor:pointer;">Logout</button></a>
        </div>
        <hr>

        <h3>Subject Management (CRUD)</h3>
        <form method="POST">
            <input type="hidden" name="sub_id" value="<?php echo $edit_id; ?>">
            <input type="text" name="subjectCode" placeholder="Subject Code (e.g., CS101)" value="<?php echo htmlspecialchars($edit_code); ?>" required>
            <input type="text" name="subjectTitle" placeholder="Subject Title (e.g., Web Development)" value="<?php echo htmlspecialchars($edit_title); ?>" required>
            <input type="number" name="units" placeholder="Units" min="1" max="6" value="<?php echo htmlspecialchars($edit_units); ?>" required>
            <button type="submit" name="save_subject"><?php echo ($edit_id != '') ? 'Update Subject' : 'Add Subject'; ?></button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Units</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $email = $_SESSION['user_email'];
                $result = $conn->query("SELECT * FROM subjects WHERE user_email = '$email'");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['code']) . "</td>
                                <td>" . htmlspecialchars($row['title']) . "</td>
                                <td>" . htmlspecialchars($row['units']) . "</td>
                                <td>
                                    <a href='index.php?edit=" . $row['id'] . "' class='btn-warning'>Edit</a>
                                    <a href='index.php?delete=" . $row['id'] . "' class='btn-danger' style='text-decoration:none; display:inline-block; border-radius:4px;' onclick='return confirm(\"Delete this subject?\")'>Delete</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center;'>No subjects found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

    <?php endif; ?>

</div>

<script>
    function toggleForm(mode) {
        if (mode === 'register') {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
        } else {
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
        }
    }
</script>

</body>
</html>