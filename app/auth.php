<?php
session_start();
include 'includes/db.php';

$action = isset($_GET['action']) && $_GET['action'] === 'register' ? 'register' : 'login';

$message = "";
$success = "";

// ========== HANDLE LOGIN ==========
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid password";
        }
    } else {
        $message = "User not found";
    }
}

// ========== HANDLE REGISTER ==========
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Check existing
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $message = "Username already taken.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed);
        if ($stmt->execute()) {
            $success = "Account created! You can now login.";
            $action = 'login';   // switch back to login tab
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Lantern</title>
    <style>
        body{
            background:#0f172a;
            color:white;
            font-family:Arial;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
            margin:0;
        }
        .box{
            background:#1e293b;
            padding:40px;
            border-radius:20px;
            width:360px;
            box-shadow:0 0 25px rgba(0,0,0,0.5);
        }
        h2{
            text-align:center;
            margin-bottom:10px;
        }
        .tabs{
            display:flex;
            gap:0;
            margin-bottom:30px;
            border-radius:12px;
            overflow:hidden;
            background:#0f172a;
        }
        .tabs a{
            flex:1;
            text-align:center;
            padding:12px 0;
            text-decoration:none;
            color:#94a3b8;
            font-weight:600;
            transition:all 0.2s ease;
            border-bottom:2px solid transparent;
        }
        .tabs a.active{
            color:white;
            background:#1e293b;
            border-bottom:2px solid #3b82f6;
        }
        .tabs a:hover:not(.active){
            color:#cbd5e1;
        }
        input{
            width:100%;
            padding:12px;
            margin-top:12px;
            border:none;
            border-radius:10px;
            box-sizing:border-box;
            background:#0f172a;
            color:white;
        }
        button{
            width:100%;
            padding:12px;
            margin-top:20px;
            border:none;
            border-radius:10px;
            background:#16a34a;
            color:white;
            cursor:pointer;
            font-size:16px;
            font-weight:600;
            transition:background 0.2s;
        }
        button:hover{
            background:#15803d;
        }
        .error{
            text-align:center;
            color:#f87171;
            margin-top:15px;
        }
        .success{
            text-align:center;
            color:#4ade80;
            margin-top:15px;
        }
    </style>
</head>
<body>

<div class="box">

    <h2>Project Lantern 🔥</h2>

    <div class="tabs">
        <a href="?action=login"    class="<?php echo $action === 'login'    ? 'active' : ''; ?>">Login</a>
        <a href="?action=register" class="<?php echo $action === 'register' ? 'active' : ''; ?>">Register</a>
    </div>

    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($action === 'login'): ?>
        <form method="POST">
            <input type="hidden" name="login" value="1">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="register" value="1">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password (min 8 chars)" required>
            <button type="submit">Create Account</button>
        </form>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="error"><?php echo $message; ?></div>
    <?php endif; ?>

</div>

</body>
</html>