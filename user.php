<?php
session_start();

// Guard: must be logged in as a regular user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lost & Found – User Dashboard</title>
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
        }
        body {
            min-height: 100vh;
            background: var(--cream);
            font-family: 'DM Sans', sans-serif;
            color: var(--navy);
        }

        /* ── NAV ── */
        nav {
            background: var(--navy);
            padding: 0 40px;
            height: 64px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-brand {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--white);
            display: flex; align-items: center; gap: 10px;
        }
        .nav-brand span { color: var(--gold); }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .nav-user {
            font-size: 13px; color: #94a3b8;
        }
        .nav-user strong { color: var(--white); }
        .logout-btn {
            background: rgba(255,255,255,.08);
            color: var(--white);
            border: 1px solid rgba(255,255,255,.15);
            padding: 7px 16px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }
        .logout-btn:hover { background: rgba(255,255,255,.15); }

        /* ── MAIN ── */
        main {
            max-width: 900px;
            margin: 60px auto;
            padding: 0 24px;
            text-align: center;
        }
        .welcome-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--navy); color: var(--gold);
            font-size: 11px; font-weight: 600; letter-spacing: .12em;
            text-transform: uppercase; padding: 6px 14px;
            border-radius: 20px; margin-bottom: 24px;
        }
        h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 42px; color: var(--navy); margin-bottom: 12px;
        }
        h1 em { font-style: italic; color: var(--teal); }
        .lead { font-size: 16px; color: #6b7280; max-width: 520px; margin: 0 auto 48px; line-height: 1.6; }

        .placeholder-box {
            background: var(--white);
            border: 2px dashed #d4cfc6;
            border-radius: 16px;
            padding: 60px 40px;
            color: #9ca3af;
            font-size: 15px;
        }
        .placeholder-box .icon { font-size: 48px; margin-bottom: 16px; }
        .placeholder-box p { line-height: 1.7; }
        .placeholder-box strong { color: var(--teal); }
    </style>
</head>
<body>

<nav>
    <div class="nav-brand">🔍 <span>Lost</span> & Found</div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
        <a href="logout.php" class="logout-btn">Sign Out</a>
    </div>
</nav>

<main>
    <div class="welcome-tag">👤 User Portal</div>
    <h1>Hello, <em><?= htmlspecialchars($_SESSION['username']) ?>.</em></h1>
    <p class="lead">Welcome to the Lost and Found Items Catalog. Browse reported items and check if your belongings have been turned in.</p>

    <div class="placeholder-box">
        <div class="icon">📦</div>
        <p>The <strong>item catalog</strong> will appear here.<br>
        This page will show all found items with search and filter options.</p>
    </div>
</main>

</body>
</html>