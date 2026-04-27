 
<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'user.php'));
    exit();
}

require_once 'dbconfig.php';

$login_error      = '';
$register_error   = '';
$register_success = '';

// ── REGISTER ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $register_error = 'All fields are required.';
    } elseif ($password === 'admin12345') {
        $register_error = 'That password is not allowed.';
    } else {
        $chk = $conn->prepare("SELECT user_id FROM user WHERE email = ? OR username = ?");
        $chk->bind_param('ss', $email, $username);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $register_error = 'Username or email already exists.';
        } else {
            $ins = $conn->prepare("INSERT INTO user (username, password, email) VALUES (?, ?, ?)");
            $ins->bind_param('sss', $username, $password, $email);
            $register_success = $ins->execute() ? 'Account created! You can now log in.' : 'Registration failed. Please try again.';
            $ins->close();
        }
        $chk->close();
    }
}

// ── LOGIN ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $login_error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password FROM user WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $username, $db_password);
        $stmt->fetch();

        if ($stmt->num_rows === 0) {
            $login_error = 'No account found with that email.';
        } elseif ($password !== $db_password) {
            $login_error = 'Incorrect password.';
        } else {
            $role = ($password === 'admin12345' && $email === 'admin@lostandfound.com') ? 'admin' : 'user';
            $_SESSION['user_id']  = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = $role;
            $stmt->close();
            header('Location: ' . ($role === 'admin' ? 'admin.php' : 'user.php'));
            exit();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lost & Found – Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy:  #0f1f38;
            --teal:  #1a6b72;
            --gold:  #c9a84c;
            --cream: #f7f3eb;
            --white: #ffffff;
            --red:   #b94040;
            --green: #2e7d52;
        }
        body {
            min-height: 100vh;
            background: var(--navy);
            display: flex; align-items: center; justify-content: center;
            font-family: 'DM Sans', sans-serif;
            position: relative; overflow: hidden;
        }
        body::before, body::after {
            content: ''; position: fixed; border-radius: 50%;
            opacity: .07; background: var(--teal);
        }
        body::before { width: 600px; height: 600px; top: -150px; right: -150px; }
        body::after  { width: 400px; height: 400px; bottom: -100px; left: -100px; }

        .card {
            background: var(--cream); border-radius: 20px;
            width: 420px; padding: 48px 44px 40px;
            box-shadow: 0 8px 40px rgba(15,31,56,.18);
            position: relative; z-index: 1;
            animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(28px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--navy); color: var(--gold);
            font-size: 11px; font-weight: 600; letter-spacing: .12em;
            text-transform: uppercase; padding: 6px 14px;
            border-radius: 20px; margin-bottom: 20px;
        }
        h1 { font-family: 'DM Serif Display', serif; font-size: 32px; color: var(--navy); margin-bottom: 6px; }
        h1 em { font-style: italic; color: var(--teal); }
        .subtitle { font-size: 14px; color: #6b7280; margin-bottom: 32px; }
        .field { margin-bottom: 18px; }
        label { display: block; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--navy); margin-bottom: 7px; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%; padding: 12px 16px;
            border: 1.5px solid #d4cfc6; border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 15px;
            background: var(--white); color: var(--navy);
            transition: border-color .2s, box-shadow .2s; outline: none;
        }
        input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(26,107,114,.12); }
        .btn {
            width: 100%; padding: 14px; background: var(--navy);
            color: var(--white); border: none; border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 15px; font-weight: 600;
            cursor: pointer; margin-top: 8px;
            transition: background .2s, transform .1s;
        }
        .btn:hover { background: var(--teal); }
        .btn:active { transform: scale(.98); }
        .alert { padding: 11px 14px; border-radius: 8px; font-size: 13.5px; margin-bottom: 18px; font-weight: 500; }
        .alert.error   { background: #fdecea; color: var(--red);   border-left: 3px solid var(--red); }
        .alert.success { background: #e8f5ee; color: var(--green); border-left: 3px solid var(--green); }
        .divider { text-align: center; margin: 22px 0 0; font-size: 13.5px; color: #9ca3af; }
        .divider a { color: var(--teal); font-weight: 600; text-decoration: none; cursor: pointer; }
        .divider a:hover { text-decoration: underline; }

        .overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,31,56,.55); backdrop-filter: blur(3px);
            z-index: 100; align-items: center; justify-content: center;
        }
        .overlay.active { display: flex; }
        .modal {
            background: var(--cream); border-radius: 20px;
            width: 420px; padding: 44px 44px 36px;
            box-shadow: 0 8px 40px rgba(15,31,56,.18);
            position: relative;
            animation: slideUp .35s cubic-bezier(.16,1,.3,1) both;
        }
        .modal h2 { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--navy); margin-bottom: 4px; }
        .close-btn { position: absolute; top: 18px; right: 20px; background: none; border: none; font-size: 22px; cursor: pointer; color: #9ca3af; }
        .close-btn:hover { color: var(--navy); }
    </style>
</head>
<body>

<div class="card">
    <div class="badge"><span>🔍</span> Lost & Found Office</div>
    <h1>Welcome <em>back.</em></h1>
    <p class="subtitle">Sign in to access the item catalog.</p>

    <?php if ($login_error): ?>
        <div class="alert error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <?php if ($register_success): ?>
        <div class="alert success"><?= htmlspecialchars($register_success) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <input type="hidden" name="action" value="login">
        <div class="field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">Sign In →</button>
    </form>
    <p class="divider">Don't have an account? <a onclick="openModal()">Create one</a></p>
</div>

<div class="overlay" id="overlay" onclick="closeOnOverlay(event)">
    <div class="modal">
        <button class="close-btn" onclick="closeModal()">✕</button>
        <h2>Create Account</h2>
        <p class="subtitle">Register to track lost and found items.</p>

        <?php if ($register_error): ?>
            <div class="alert error"><?= htmlspecialchars($register_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="register">
            <div class="field">
                <label for="reg_username">Username</label>
                <input type="text" id="reg_username" name="username" placeholder="e.g. jdelacruz" required>
            </div>
            <div class="field">
                <label for="reg_email">Email Address</label>
                <input type="email" id="reg_email" name="email" placeholder="you@example.com" required>
            </div>
            <div class="field">
                <label for="reg_password">Password</label>
                <input type="password" id="reg_password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Register →</button>
        </form>
        <p class="divider">Already have an account? <a onclick="closeModal()">Sign in</a></p>
    </div>
</div>

<script>
    const overlay = document.getElementById('overlay');
    function openModal()  { overlay.classList.add('active'); }
    function closeModal() { overlay.classList.remove('active'); }
    function closeOnOverlay(e) { if (e.target === overlay) closeModal(); }
    <?php if ($register_error): ?>openModal();<?php endif; ?>
</script>
</body>
</html>