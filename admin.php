<?php
session_start();

// Guard: admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

require_once 'dbconfig.php';

// Fetch items
// Search, filter, sort for items section
$i_search = trim($_GET['i_search'] ?? '');
$i_filter = $_GET['i_filter'] ?? 'all';
$i_sort   = $_GET['i_sort']   ?? 'newest';

$i_where = [];
$i_params = [];
$i_types  = '';

if ($i_search !== '') {
    $i_where[] = "(i.item_name LIKE ? OR i.description LIKE ? OR i.location_found LIKE ?)";
    $like = "%$i_search%";
    $i_params[] = $like; $i_params[] = $like; $i_params[] = $like;
    $i_types .= 'sss';
}
if ($i_filter === 'unclaimed')   { $i_where[] = "i.status = 'unclaimed'"; }
elseif ($i_filter === 'claimed') { $i_where[] = "i.status = 'claimed'"; }
elseif ($i_filter === 'pending') { $i_where[] = "i.claim_status = 'pending'"; }
elseif ($i_filter === 'turned')  { $i_where[] = "i.status = 'turned_over'"; }

$i_where_sql = count($i_where) ? 'WHERE ' . implode(' AND ', $i_where) : '';
$i_order_sql = match($i_sort) {
    'oldest'  => 'ORDER BY i.date_found ASC',
    'name_az' => 'ORDER BY i.item_name ASC',
    'name_za' => 'ORDER BY i.item_name DESC',
    default   => 'ORDER BY i.date_found DESC',
};

$i_sql = "SELECT i.*, u.username FROM item i LEFT JOIN user u ON i.reported_by = u.user_id $i_where_sql $i_order_sql";
$i_stmt = $conn->prepare($i_sql);
if ($i_types && $i_params) { $i_stmt->bind_param($i_types, ...$i_params); }
$i_stmt->execute();
$items_result = $i_stmt->get_result();

// Fetch users (exclude admin)
$users_result = $conn->query(
    "SELECT u.*, COUNT(i.item_id) AS items_reported
     FROM user u
     LEFT JOIN item i ON u.user_id = i.reported_by
     WHERE u.username != 'admin'
     GROUP BY u.user_id
     ORDER BY u.created_at DESC"
);

// Fetch claims — no separate claim table, claim info is inside item table
$claims_result = $conn->query(
    "SELECT i.item_id, i.item_name, u.username,
            i.claim_date, i.claim_status
     FROM item i
     LEFT JOIN user u ON i.reported_by = u.user_id
     WHERE i.claim_status IS NOT NULL
     ORDER BY i.claim_date DESC"
);

// Counts for stat cards
$total_items     = $conn->query("SELECT COUNT(*) FROM item")->fetch_row()[0];
$total_unclaimed = $conn->query("SELECT COUNT(*) FROM item WHERE status='unclaimed'")->fetch_row()[0];
$total_claimed   = $conn->query("SELECT COUNT(*) FROM item WHERE status='claimed'")->fetch_row()[0];
$total_users     = $conn->query("SELECT COUNT(*) FROM user WHERE username != 'admin'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lost & Found – Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:      #0f1f38;
            --teal:      #1a6b72;
            --gold:      #c9a84c;
            --cream:     #f7f3eb;
            --white:     #ffffff;
            --red:       #b94040;
            --border:    #e8e2d9;
            --muted:     #6b7280;
            --sidebar-w: 240px;
        }

        body {
            min-height: 100vh;
            background: var(--cream);
            font-family: 'DM Sans', sans-serif;
            color: var(--navy);
            display: flex;
        }

        /* ── SIDEBAR ─────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }

        #sidebar-brand {
            padding: 28px 24px 22px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .logo {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .logo span { color: var(--gold); }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(201,168,76,.12);
            color: var(--gold);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(201,168,76,.22);
        }

        #sidebar-nav {
            flex: 1;
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #475569;
            padding: 12px 12px 6px;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            background: none;
            border: 1px solid transparent;
            color: #94a3b8;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: background .15s, color .15s, border-color .15s;
        }

        .nav-btn:hover { background: rgba(255,255,255,.07); color: var(--white); }

        .nav-btn.active {
            background: rgba(26,107,114,.28);
            border-color: rgba(26,107,114,.4);
            color: var(--white);
        }

        .nav-icon { font-size: 16px; width: 20px; text-align: center; }
        .nav-text  { flex: 1; }

        .nav-count {
            background: var(--teal);
            color: var(--white);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            min-width: 22px;
            text-align: center;
        }

        #sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            margin-bottom: 6px;
        }

        .avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--teal);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700;
            color: var(--white);
            flex-shrink: 0;
        }

        .uname { font-size: 13px; font-weight: 600; color: var(--white); }
        .urole  { font-size: 11px; color: #64748b; }

        .signout {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: #94a3b8;
            font-size: 13px;
            text-decoration: none;
            transition: background .15s, color .15s;
        }

        .signout:hover { background: rgba(185,64,64,.2); color: #f87171; }

        /* ── MAIN ────────────────────────────────── */
        #main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        #topbar {
            height: 64px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 0 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        #topbar-title {
            font-family: 'DM Serif Display', serif;
            font-size: 22px;
            color: var(--navy);
        }

        #topbar-title span { color: var(--teal); font-style: italic; }

        #content { padding: 36px; flex: 1; }

        /* ── BUTTONS ─────────────────────────────── */
        .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--navy); color: var(--white);
            border: none; border-radius: 10px;
            padding: 10px 20px;
            font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s;
        }
        .btn-primary:hover  { background: var(--teal); }
        .btn-primary:active { transform: scale(.97); }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--cream); color: var(--navy);
            border: 1.5px solid #d4cfc6; border-radius: 10px;
            padding: 10px 18px;
            font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .btn-secondary:hover { border-color: var(--navy); background: #eee8de; }

        .btn-danger {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fdecea; color: var(--red);
            border: 1px solid #f5c6c6; border-radius: 8px;
            padding: 7px 14px;
            font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-danger:hover { background: #fbd5d5; }

        .btn-edit {
            display: inline-flex; align-items: center; gap: 6px;
            background: #eff6ff; color: #1d4ed8;
            border: 1px solid #bfdbfe; border-radius: 8px;
            padding: 7px 14px;
            font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-edit:hover { background: #dbeafe; }

        .btn-approve {
            display: inline-flex; align-items: center; gap: 6px;
            background: #dcfce7; color: #14532d;
            border: 1px solid #bbf7d0; border-radius: 8px;
            padding: 7px 14px;
            font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-approve:hover { background: #bbf7d0; }

        .btn-reject {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fdecea; color: var(--red);
            border: 1px solid #f5c6c6; border-radius: 8px;
            padding: 7px 14px;
            font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-reject:hover { background: #fbd5d5; }

        .btn-filter {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--navy); color: var(--white);
            border: none; border-radius: 9px;
            padding: 9px 18px;
            font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .btn-filter:hover { background: var(--teal); }

        /* ── STAT CARDS ──────────────────────────── */
        #stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px 24px;
            display: flex; align-items: flex-start; gap: 16px;
            transition: box-shadow .2s;
        }
        .stat-card:hover { box-shadow: 0 4px 20px rgba(15,31,56,.08); }

        .stat-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .s-navy { background: rgba(15,31,56,.08); }
        .s-gold { background: rgba(201,168,76,.12); }
        .s-teal { background: rgba(26,107,114,.10); }
        .s-red  { background: rgba(185,64,64,.08); }

        .stat-value {
            font-family: 'DM Serif Display', serif;
            font-size: 30px; color: var(--navy);
            line-height: 1; margin-bottom: 4px;
        }
        .stat-label { font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── PANEL ───────────────────────────────── */
        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }

        .panel-header {
            padding: 20px 28px;
            border-bottom: 1px solid #f0ebe2;
            display: flex; align-items: center; justify-content: space-between;
        }

        .panel-header h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 20px; color: var(--navy); font-weight: 400;
        }
        .panel-header p { font-size: 13px; color: #9ca3af; margin-top: 2px; }

        /* ── FILTER BAR ──────────────────────────── */
        .filter-bar {
            padding: 14px 28px;
            border-bottom: 1px solid #f0ebe2;
            background: #faf8f4;
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        }

        .search-wrap { position: relative; flex: 1; max-width: 280px; }

        .search-icon {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%);
            font-size: 14px; color: #9ca3af; pointer-events: none;
        }

        .filter-bar input[type="text"] {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1.5px solid #d4cfc6; border-radius: 9px;
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            background: var(--white); color: var(--navy);
            outline: none; transition: border-color .2s;
        }
        .filter-bar input[type="text"]:focus { border-color: var(--teal); }

        .filter-bar select {
            padding: 9px 14px;
            border: 1.5px solid #d4cfc6; border-radius: 9px;
            font-family: 'DM Sans', sans-serif; font-size: 13px;
            background: var(--white); color: var(--navy);
            outline: none; cursor: pointer; transition: border-color .2s;
        }
        .filter-bar select:focus { border-color: var(--teal); }

        /* ── TABLE ───────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { border-bottom: 1px solid var(--border); }
        thead th {
            padding: 12px 24px;
            text-align: left; font-size: 11px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase;
            color: #9ca3af; white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid #f5f0e8; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #faf8f4; }
        td { padding: 15px 24px; font-size: 13.5px; vertical-align: middle; }
        .td-main { font-weight: 600; color: var(--navy); }
        .td-sub  { font-size: 12px; color: #9ca3af; margin-top: 2px; }
        .action-group { display: flex; align-items: center; gap: 8px; }

        /* ── BADGES ──────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 600;
            padding: 4px 10px; border-radius: 20px; white-space: nowrap;
        }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }

        .badge-unclaimed   { background: #fef9c3; color: #854d0e; }
        .badge-unclaimed::before   { background: #ca8a04; }
        .badge-claimed     { background: #dcfce7; color: #14532d; }
        .badge-claimed::before     { background: #16a34a; }
        .badge-pending     { background: #eff6ff; color: #1e40af; }
        .badge-pending::before     { background: #3b82f6; }
        .badge-turned_over { background: #f3f4f6; color: #374151; }
        .badge-turned_over::before { background: #9ca3af; }
        .badge-approved    { background: #dcfce7; color: #14532d; }
        .badge-approved::before    { background: #16a34a; }
        .badge-rejected    { background: #fdecea; color: var(--red); }
        .badge-rejected::before    { background: var(--red); }

        /* ── EMPTY STATE ─────────────────────────── */
        .empty-state { padding: 60px 40px; text-align: center; color: #9ca3af; }
        .empty-icon  { font-size: 40px; margin-bottom: 14px; }
        .empty-state p { font-size: 14px; line-height: 1.7; }
        .empty-state strong { color: var(--teal); }

        /* ── MODAL ───────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15,31,56,.52);
            backdrop-filter: blur(4px);
            z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }

        .modal-box {
            background: var(--cream);
            border-radius: 20px;
            width: 520px; max-height: 90vh; overflow-y: auto;
            padding: 40px 44px 36px;
            box-shadow: 0 16px 60px rgba(15,31,56,.22);
            position: relative;
            animation: modalIn .3s cubic-bezier(.16,1,.3,1) both;
        }
        .modal-box.modal-sm { width: 420px; }

        @keyframes modalIn {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-close {
            position: absolute; top: 18px; right: 20px;
            background: none; border: none;
            font-size: 20px; cursor: pointer; color: #9ca3af;
            transition: color .15s;
        }
        .modal-close:hover { color: var(--navy); }

        .modal-box h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 24px; color: var(--navy); margin-bottom: 4px;
        }
        .modal-sub { font-size: 13px; color: var(--muted); margin-bottom: 28px; }

        .form-field { margin-bottom: 18px; }
        .form-field label {
            display: block; font-size: 11px; font-weight: 600;
            letter-spacing: .07em; text-transform: uppercase;
            color: var(--navy); margin-bottom: 7px;
        }
        .form-field input,
        .form-field textarea,
        .form-field select {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #d4cfc6; border-radius: 10px;
            font-family: 'DM Sans', sans-serif; font-size: 14px;
            background: var(--white); color: var(--navy);
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .form-field textarea { resize: vertical; min-height: 90px; }
        .form-field input:focus,
        .form-field textarea:focus,
        .form-field select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(26,107,114,.12);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .modal-actions {
            display: flex; gap: 10px; justify-content: flex-end;
            margin-top: 28px; padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* ── TOAST ───────────────────────────────── */
        #toast {
            display: none;
            position: fixed; bottom: 28px; right: 28px;
            background: var(--navy); color: var(--white);
            padding: 14px 22px; border-radius: 12px;
            font-size: 13px; font-weight: 500;
            box-shadow: 0 4px 24px rgba(15,31,56,.2);
            z-index: 300;
            animation: toastIn .3s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn { 
            from { opacity:0; transform:translateY(12px);} 
            to { opacity:1; transform:translateY(0);} 
        }

    </style>
</head>
<body>

<!-- ════════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════ -->
<aside id="sidebar">

    <div id="sidebar-brand">
        <div class="logo">🔍 <span>Lost</span> & Found</div>
        <div class="admin-badge">⚙️ Admin Panel</div>
    </div>

    <nav id="sidebar-nav">
        <div class="nav-label">Management</div>

        <button class="nav-btn active" onclick="switchSection('items')">
            <span class="nav-icon">📦</span>
            <span class="nav-text">Items</span>
            <span class="nav-count"><?= $total_items ?></span>
        </button>

        <button class="nav-btn" onclick="switchSection('users')">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Users</span>
            <span class="nav-count"><?= $total_users ?></span>
        </button>

        <div class="nav-label">Reports</div>

        <button class="nav-btn" onclick="switchSection('claims')">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Claim Requests</span>
            <span class="nav-count"><?= $conn->query("SELECT COUNT(*) FROM item WHERE claim_status IS NOT NULL")->fetch_row()[0] ?></span>
        </button>
    </nav>

    <div id="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <div>
                <div class="uname"><?= htmlspecialchars($_SESSION['username']) ?></div>
                <div class="urole">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="signout">🚪 Sign Out</a>
    </div>

</aside>

<!-- ════════════════════════════════════════════
     MAIN
════════════════════════════════════════════ -->
<div id="main">

    <header id="topbar">
        <h2 id="topbar-title"><span>Items</span> Catalog</h2>
        <button class="btn-primary" id="btn-add" onclick="openAddModal()">
            + Add New Item
        </button>
    </header>

    <main id="content">

        <!-- ── Stat Cards ── -->
        <div id="stats-grid">
            <div class="stat-card">
                <div class="stat-icon s-navy">📦</div>
                <div>
                    <div class="stat-value"><?= $total_items ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon s-gold">🔍</div>
                <div>
                    <div class="stat-value"><?= $total_unclaimed ?></div>
                    <div class="stat-label">Unclaimed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon s-teal">✅</div>
                <div>
                    <div class="stat-value"><?= $total_claimed ?></div>
                    <div class="stat-label">Claimed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon s-red">👥</div>
                <div>
                    <div class="stat-value"><?= $total_users ?></div>
                    <div class="stat-label">Registered Users</div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════
             SECTION: ITEMS
        ════════════════════════════════ -->
        <div id="section-items" class="panel">

            <div class="panel-header">
                <div>
                    <h3>Found Items</h3>
                    <p>All items reported and logged by the office</p>
                </div>
            </div>

            <form method="GET" action="admin.php" id="items-filter-form">
            <div class="filter-bar">
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="i_search" placeholder="Search items..."
                        value="<?= htmlspecialchars($i_search) ?>">
                </div>
                <select name="i_sort">
                    <option value="newest"  <?= $i_sort==='newest'  ? 'selected':'' ?>>Newest First</option>
                    <option value="oldest"  <?= $i_sort==='oldest'  ? 'selected':'' ?>>Oldest First</option>
                    <option value="name_az" <?= $i_sort==='name_az' ? 'selected':'' ?>>Name A–Z</option>
                    <option value="name_za" <?= $i_sort==='name_za' ? 'selected':'' ?>>Name Z–A</option>
                </select>
                <select name="i_filter">
                    <option value="all"      <?= $i_filter==='all'      ? 'selected':'' ?>>All Statuses</option>
                    <option value="unclaimed"<?= $i_filter==='unclaimed' ? 'selected':'' ?>>Unclaimed</option>
                    <option value="claimed"  <?= $i_filter==='claimed'   ? 'selected':'' ?>>Claimed</option>
                    <option value="pending"  <?= $i_filter==='pending'   ? 'selected':'' ?>>Pending</option>
                    <option value="turned"   <?= $i_filter==='turned'    ? 'selected':'' ?>>Turned Over</option>
                </select>
                <button type="submit" class="btn-filter">Apply</button>
                <?php if ($i_search || $i_filter !== 'all' || $i_sort !== 'newest'): ?>
                    <a href="admin.php" style="font-size:13px;color:#9ca3af;text-decoration:none;white-space:nowrap;">✕ Clear</a>
                <?php endif; ?>
            </div>
            </form>

            <div style="padding:24px 28px;">
            <p style="font-size:13px;color:#9ca3af;margin-bottom:20px;">
                Showing <strong style="color:var(--navy)"><?= $items_result->num_rows ?></strong> item<?= $items_result->num_rows !== 1 ? 's' : '' ?>
            </p>
            <?php
            $admin_items = $items_result->fetch_all(MYSQLI_ASSOC);
            if (empty($admin_items)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No items found. Try adjusting your search or filter.</p>
                </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
            <?php foreach ($admin_items as $idx => $item):
                $status = $item['status'];
                $cs     = $item['claim_status'];
                if ($status === 'claimed')       { $badge_color='#2e7d52'; $badge_bg='#e8f5ee'; $badge_lbl='✓ Claimed'; }
                elseif ($status === 'turned_over'){ $badge_color='#5b4a9e'; $badge_bg='#ede9ff'; $badge_lbl='↗ Turned Over'; }
                elseif ($cs === 'pending')       { $badge_color='#a07800'; $badge_bg='#fff8e0'; $badge_lbl='⏳ Pending'; }
                else                             { $badge_color='#b94040'; $badge_bg='#fdecea'; $badge_lbl='◉ Unclaimed'; }
            ?>
            <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(15,31,56,.07);border:1.5px solid var(--border);overflow:hidden;display:flex;flex-direction:column;animation:fadeIn .4s ease both;animation-delay:<?= $idx*0.03 ?>s">
                <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="Item photo"
                        style="width:100%;height:180px;object-fit:cover;border-bottom:1px solid var(--border);">
                <?php else: ?>
                    <div style="width:100%;height:120px;background:#f0ebe2;display:flex;align-items:center;justify-content:center;font-size:40px;border-bottom:1px solid var(--border);">📦</div>
                <?php endif; ?>
                <div style="padding:16px 18px 12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="font-family:'DM Serif Display',serif;font-size:17px;color:var(--navy);line-height:1.2;"><?= htmlspecialchars($item['item_name']) ?></div>
                    <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;white-space:nowrap;color:<?= $badge_color ?>;background:<?= $badge_bg ?>"><?= $badge_lbl ?></span>
                </div>
                <div style="padding:14px 18px;flex:1;">
                    <div style="display:flex;gap:8px;margin-bottom:9px;font-size:13px;">
                        <span>📍</span>
                        <div>
                            <span style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Location</span>
                            <span style="color:var(--navy)"><?= htmlspecialchars($item['location_found']) ?></span>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;margin-bottom:9px;font-size:13px;">
                        <span>📅</span>
                        <div>
                            <span style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Date Found</span>
                            <span style="color:var(--navy)"><?= date('F j, Y', strtotime($item['date_found'])) ?></span>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;font-size:13px;">
                        <span>👤</span>
                        <div>
                            <span style="display:block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Reported By</span>
                            <span style="color:var(--navy)"><?= htmlspecialchars($item['username'] ?? 'Unknown') ?></span>
                        </div>
                    </div>
                </div>
                <div style="padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:8px;">
                    <button class="btn-edit" style="flex:1;justify-content:center;"
                        onclick="openEditModal(
                            <?= $item['item_id'] ?>,
                            '<?= htmlspecialchars(addslashes($item['item_name'])) ?>',
                            '<?= htmlspecialchars(addslashes($item['description'] ?? '')) ?>',
                            '<?= htmlspecialchars(addslashes($item['location_found'])) ?>',
                            '<?= $item['date_found'] ?>',
                            '<?= $item['status'] ?>'
                        )">✏️ Edit</button>
                    <button class="btn-danger" style="flex:1;justify-content:center;"
                        onclick="confirmDelete(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>')">
                        🗑 Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        </div><!-- /section-items -->

        <!-- ════════════════════════════════
             SECTION: USERS
        ════════════════════════════════ -->
        <div id="section-users" class="panel" style="display:none;">

            <div class="panel-header">
                <div>
                    <h3>Registered Users</h3>
                    <p>All user accounts (admin excluded)</p>
                </div>
            </div>

            <div class="filter-bar">
                <select>
                    <option>Newest First</option>
                    <option>Oldest First</option>
                    <option>Username A–Z</option>
                </select>
                <button class="btn-filter">Apply</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Date Joined</th>
                            <th>Items Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <!-- Users tbody -->
                    <tbody>
                        
                    </tbody>
                </table>
            </div>

        </div><!-- /section-users -->

        <!-- ════════════════════════════════
             SECTION: CLAIM REQUESTS
        ════════════════════════════════ -->
        <div id="section-claims" class="panel" style="display:none;">

            <div class="panel-header">
                <div>
                    <h3>Claim Requests</h3>
                    <p>Review and approve or reject user claim submissions</p>
                </div>
            </div>

            <div class="filter-bar">
                <select>
                    <option>All Statuses</option>
                    <option>Pending</option>
                    <option>Approved</option>
                    <option>Rejected</option>
                </select>
                <button class="btn-filter">Apply</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Claimed By</th>
                            <th>Claim Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <!-- Claims tbody -->
                    <tbody>
                        
                    </tbody>
                </table>
            </div>

        </div><!-- /section-claims -->

    </main>
</div><!-- /main -->

<!-- ════════════════════════════════════════════
     MODAL: ADD / EDIT ITEM
════════════════════════════════════════════ -->
<div id="item-modal" class="modal-overlay">
    <div class="modal-box">

        <button class="modal-close" onclick="closeModal('item-modal')">✕</button>
        <h2 id="modal-title">Add New Item</h2>
        <p class="modal-sub" id="modal-sub">Fill in the details of the found item.</p>

        <form method="POST" id="item-form" action="insert.php" enctype="multipart/form-data">
            <input type="hidden" name="item_id" id="form-item-id">

            <div class="form-field">
                <label>Item Name *</label>
                <input type="text" id="f-name" name="item_name"
                       placeholder="e.g. Black Umbrella" required>
            </div>

            <div class="form-field">
                <label>Description</label>
                <textarea id="f-desc" name="description"
                          placeholder="Color, brand, distinguishing marks…"></textarea>
            </div>

            <div class="form-field">
                <label>Item Photo <span style="font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;">(optional)</span></label>
                <input type="file" id="f-image" name="item_image" accept="image/*"
                style="padding:8px 14px;border:1.5px solid #d4cfc6;border-radius:10px;width:100%;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--white);cursor:pointer;">
                <div id="img-preview-wrap" style="margin-top:10px;display:none;">
                    <img id="img-preview" src="" alt="Preview"
                    style="max-height:140px;border-radius:8px;border:1px solid var(--border);object-fit:cover;">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label>Location Found *</label>
                    <input type="text" id="f-location" name="location_found"
                           placeholder="e.g. Library 2nd Floor" required>
                </div>
                <div class="form-field">
                    <label>Date Found *</label>
                    <input type="date" id="f-date" name="date_found" required>
                </div>
            </div>

            <div class="form-field">
                <label>Status</label>
                <select id="f-status" name="status">
                    <option value="unclaimed"  >Unclaimed</option>
                    <option value="claimed"    >Claimed</option>
                    <option value="pending"    >Pending</option>
                    <option value="turned_over">Turned Over</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('item-modal')">Cancel</button>
                <button type="submit" class="btn-primary" id="modal-submit-btn">
                    Save Item
                </button>
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ════════════════════════════════════════════
     MODAL: DELETE ITEM CONFIRMATION
════════════════════════════════════════════ -->
<div id="delete-item-modal" class="modal-overlay">
    <div class="modal-box modal-sm">

        <button class="modal-close" onclick="closeModal('delete-item-modal')">✕</button>
        <h2>Delete Item?</h2>
        <p class="modal-sub">
            You are about to delete <strong id="delete-item-name"></strong>.
            This action cannot be undone.
        </p>

        <form method="POST" action="delete.php">
            <input type="hidden" name="item_id" id="delete-item-id">
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('delete-item-modal')">Cancel</button>
                <button type="submit" class="btn-danger">🗑 Yes, Delete</button>
            </div>
        </form>

    </div>
</div>

<!-- ════════════════════════════════════════════
     MODAL: EDIT USER
════════════════════════════════════════════ -->
<div id="edit-user-modal" class="modal-overlay">
    <div class="modal-box">

        <button class="modal-close" onclick="closeModal('edit-user-modal')">✕</button>
        <h2>Edit User</h2>
        <p class="modal-sub">Update the account details of this user.</p>

        <form method="POST" action="update_user.php">
            <input type="hidden" name="user_id" id="edit-user-id">

            <div class="form-field">
                <label>Username</label>
                <input type="text" id="eu-username" name="username"
                       placeholder="e.g. jdelacruz" required>
            </div>

            <div class="form-field">
                <label>Email Address</label>
                <input type="email" id="eu-email" name="email"
                       placeholder="e.g. juan@example.com" required>
            </div>

            <div class="form-field">
                <label>New Password <span style="font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;">(leave blank to keep current)</span></label>
                <input type="password" id="eu-password" name="password"
                       placeholder="••••••••">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-user-modal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>

    </div>
</div>

<!-- ════════════════════════════════════════════
     MODAL: DELETE USER CONFIRMATION
════════════════════════════════════════════ -->
<div id="delete-user-modal" class="modal-overlay">
    <div class="modal-box modal-sm">

        <button class="modal-close" onclick="closeModal('delete-user-modal')">✕</button>
        <h2>Delete User?</h2>
        <p class="modal-sub">
            You are about to delete <strong id="delete-user-name"></strong>.
            This cannot be undone.
        </p>

        <form method="POST" action="delete_user.php">
            <input type="hidden" name="user_id" id="delete-user-id">
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('delete-user-modal')">Cancel</button>
                <button type="submit" class="btn-danger">🗑 Yes, Delete</button>
            </div>
        </form>

    </div>
</div>

<!-- Toast -->
<div id="toast"><span id="toast-msg"></span></div>

<!-- ════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════ -->
<script>

    const SECTIONS = ['items', 'users', 'claims'];
    const TITLES   = {
        items:  '<span>Items</span> Catalog',
        users:  '<span>User</span> Management',
        claims: '<span>Claim</span> Requests',
    };

    // ── Section switching ──────────────────────────
    function switchSection(name) {
        SECTIONS.forEach(s => {
            document.getElementById('section-' + s).style.display = s === name ? 'block' : 'none';
        });

        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.toggle('active',
                btn.getAttribute('onclick') === `switchSection('${name}')`
            );
        });

        document.getElementById('topbar-title').innerHTML = TITLES[name];
        document.getElementById('btn-add').style.display  = name === 'items' ? 'inline-flex' : 'none';
    }

    // ── Modal helpers ──────────────────────────────
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // ── Add Item ───────────────────────────────────
    function openAddModal() {
        document.getElementById('modal-title').textContent      = 'Add New Item';
        document.getElementById('modal-sub').textContent        = 'Fill in the details of the found item.';
        document.getElementById('modal-submit-btn').textContent = 'Save Item';
        document.getElementById('form-item-id').value           = '';
        document.getElementById('item-form').reset();
        document.getElementById('item-modal').classList.add('open');
        document.getElementById('item-form').action = 'insert.php';
        document.getElementById('img-preview-wrap').style.display = 'none';
    }

    // ── Edit Item ──────────────────────────────────
    function openEditModal(id, name, desc, location, date, status) {
        document.getElementById('modal-title').textContent      = 'Edit Item';
        document.getElementById('modal-sub').textContent        = 'Update the details of this item.';
        document.getElementById('modal-submit-btn').textContent = 'Update Item';
        document.getElementById('form-item-id').value           = id;
        document.getElementById('f-name').value                 = name;
        document.getElementById('f-desc').value                 = desc;
        document.getElementById('f-location').value             = location;
        document.getElementById('f-date').value                 = date;
        document.getElementById('f-status').value               = status;
        document.getElementById('item-modal').classList.add('open');
        document.getElementById('item-form').action = 'update.php';
    }

    // ── Delete Item ────────────────────────────────
    function confirmDelete(id, name) {
        document.getElementById('delete-item-id').value           = id;
        document.getElementById('delete-item-name').textContent   = name;
        document.getElementById('delete-item-modal').classList.add('open');
    }

    // ── Edit User ──────────────────────────────────
    function openEditUserModal(id, username, email) {
        document.getElementById('edit-user-id').value   = id;
        document.getElementById('eu-username').value    = username;
        document.getElementById('eu-email').value       = email;
        document.getElementById('eu-password').value    = '';
        document.getElementById('edit-user-modal').classList.add('open');
    }

    // ── Delete User ────────────────────────────────
    function confirmDeleteUser(id, name) {
        document.getElementById('delete-user-id').value           = id;
        document.getElementById('delete-user-name').textContent   = name;
        document.getElementById('delete-user-modal').classList.add('open');
    }

    // ── Toast ──────────────────────────────────────
    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toast-msg').textContent = msg;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3500);
    }

    <?php
    if (isset($_GET['success'])) {
        $msg = match($_GET['success']) {
            'added'        => '✅ Item added successfully.',
            'updated'      => '✅ Item updated successfully.',
            'deleted'      => '✅ Item deleted successfully.',
            'user_updated' => '✅ User updated successfully.',
            'user_deleted' => '✅ User deleted successfully.',
            'claim_approved' => '✅ Claim approved. Item marked as Claimed.',
            'claim_rejected' => '✅ Claim rejected.',
            default        => '✅ Done.',
        };
        echo "showToast(" . json_encode($msg) . ");";
    }
    if (isset($_GET['error'])) {
        $msg = match($_GET['error']) {
            'missing_fields'      => '❌ Please fill in all required fields.',
            'insert_failed'       => '❌ Failed to add item.',
            'update_failed'       => '❌ Failed to update item.',
            'delete_failed'       => '❌ Failed to delete item.',
            'user_update_failed'  => '❌ Failed to update user.',
            'user_has_items'      => '❌ Cannot delete user with reported items.',
            'cannot_delete_admin' => '❌ Cannot delete the admin account.',
            'cannot_edit_admin'   => '❌ Cannot edit the admin account.',
            'claim_failed'   => '❌ Failed to update claim.',
            'invalid_claim'  => '❌ Invalid claim action.',
            default               => '❌ Something went wrong.',
        };
        echo "showToast(" . json_encode($msg) . ");";
    }
    ?>

    // ══════════════════════════════════════════════════════
//  AJAX POLLING — updates tables every 5 seconds
//  without a full page reload
// ══════════════════════════════════════════════════════

const POLL_INTERVAL = 5000; // 5 seconds — change to your liking

// ── Helper: escape HTML to prevent XSS ────────────────
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

// ── Helper: badge HTML from a status string ────────────
function badge(status) {
    const label = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    return `<span class="badge badge-${status}">${label}</span>`;
}

// ── Rebuild Items table body ───────────────────────────
function renderItems(data) {
    // Update stat cards
    document.querySelector('#stats-grid .stat-card:nth-child(1) .stat-value').textContent = data.total_items;
    document.querySelector('#stats-grid .stat-card:nth-child(2) .stat-value').textContent = data.total_unclaimed;
    document.querySelector('#stats-grid .stat-card:nth-child(3) .stat-value').textContent = data.total_claimed;

    // Update Items nav count
    document.querySelector('.nav-btn:nth-child(2) .nav-count').textContent = data.total_items;

    const tbody = document.querySelector('#section-items tbody');

    if (!data.items.length) {
        tbody.innerHTML = `
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No items yet. <strong>Add the first item</strong> using the button above.</p>
                </div>
            </td></tr>`;
        return;
    }

    tbody.innerHTML = data.items.map(row => {
        const desc = row.description && row.description.length > 55
            ? row.description.substring(0, 55) + '…'
            : (row.description ?? '');

        // Safely escape values for JS onclick arguments
        const jsName     = row.item_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const jsDesc     = (row.description ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const jsLocation = row.location_found.replace(/'/g, "\\'").replace(/"/g, '&quot;');

        return `
        <tr>
            <td style="color:#9ca3af;font-size:12px;">${esc(row.item_id)}</td>
            <td>
                <div class="td-main">${esc(row.item_name)}</div>
                <div class="td-sub">${esc(desc)}</div>
            </td>
            <td>${esc(row.location_found)}</td>
            <td>${esc(row.date_found)}</td>
            <td>${badge(row.status)}</td>
            <td>${esc(row.username ?? '—')}</td>
            <td>
                <div class="action-group">
                    <button class="btn-edit" onclick="openEditModal(
                        ${row.item_id},
                        '${jsName}',
                        '${jsDesc}',
                        '${jsLocation}',
                        '${row.date_found}',
                        '${row.status}'
                    )">✏️ Edit</button>
                    <button class="btn-danger" onclick="confirmDelete(${row.item_id}, '${jsName}')">
                        🗑 Delete
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Rebuild Users table body ───────────────────────────
function renderUsers(data) {
    // Update Users nav count
    document.querySelector('#stats-grid .stat-card:nth-child(4) .stat-value').textContent = data.total_users;

    const tbody = document.querySelector('#section-users tbody');

    if (!data.users.length) {
        tbody.innerHTML = `
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon">👥</div>
                    <p>No registered users yet.</p>
                </div>
            </td></tr>`;
        return;
    }

    tbody.innerHTML = data.users.map(u => {
        const initial  = u.username.charAt(0).toUpperCase();
        const jsName   = u.username.replace(/'/g, "\\'");
        const jsEmail  = u.email.replace(/'/g, "\\'");
        const joined   = new Date(u.created_at).toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });

        return `
        <tr>
            <td style="color:#9ca3af;font-size:12px;">${esc(u.user_id)}</td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="avatar" style="width:30px;height:30px;font-size:12px;">${initial}</div>
                    <div class="td-main">${esc(u.username)}</div>
                </div>
            </td>
            <td>${esc(u.email)}</td>
            <td>${joined}</td>
            <td><span class="nav-count">${u.items_reported}</span></td>
            <td>
                <div class="action-group">
                    <button class="btn-edit" onclick="openEditUserModal(
                        ${u.user_id}, '${jsName}', '${jsEmail}'
                    )">✏️ Edit</button>
                    <button class="btn-danger" onclick="confirmDeleteUser(${u.user_id}, '${jsName}')">
                        🗑 Delete
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── Rebuild Claims table body ──────────────────────────
function renderClaims(data) {
    // Update Claims nav count
    document.querySelector('.nav-btn:nth-child(4) .nav-count').textContent = data.total_claims;

    const tbody = document.querySelector('#section-claims tbody');

    if (!data.claims.length) {
        tbody.innerHTML = `
            <tr><td colspan="6">
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <p>No claim requests yet.</p>
                </div>
            </td></tr>`;
        return;
    }

    tbody.innerHTML = data.claims.map(c => {
        const actions = c.claim_status === 'pending'
            ? `<form method="POST" action="claim_action.php" style="display:inline;">
                   <input type="hidden" name="item_id" value="${c.item_id}">
                   <input type="hidden" name="action"  value="approved">
                   <button type="submit" class="btn-approve">✅ Approve</button>
               </form>
               <form method="POST" action="claim_action.php" style="display:inline;">
                   <input type="hidden" name="item_id" value="${c.item_id}">
                   <input type="hidden" name="action"  value="rejected">
                   <button type="submit" class="btn-reject">✕ Reject</button>
               </form>`
            : `<span style="font-size:12px;color:#9ca3af;">No actions available</span>`;

        return `
        <tr>
            <td style="color:#9ca3af;font-size:12px;">${esc(c.item_id)}</td>
            <td class="td-main">${esc(c.item_name)}</td>
            <td>${esc(c.username ?? '—')}</td>
            <td>${esc(c.claim_date ?? '—')}</td>
            <td>${badge(c.claim_status)}</td>
            <td><div class="action-group">${actions}</div></td>
        </tr>`;
    }).join('');
}

    function pollUsers() {
        fetch('get_users.php')
            .then(res => res.json())
            .then(data => renderUsers(data))
            .catch(err => console.error('Users poll failed:', err));
    }

    function pollClaims() {
        fetch('get_claims.php')
            .then(res => res.json())
            .then(data => renderClaims(data))
            .catch(err => console.error('Claims poll failed:', err));
    }

    // ── Start polling on page load ─────────────────────────
    // Runs immediately once, then repeats every POLL_INTERVAL ms
    pollUsers();
    pollClaims();

    setInterval(pollUsers,  POLL_INTERVAL);
    setInterval(pollClaims, POLL_INTERVAL);

    // Image preview on file select
    document.getElementById('f-image').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('img-preview').src = e.target.result;
                document.getElementById('img-preview-wrap').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('img-preview-wrap').style.display = 'none';
        }
    });

</script>

</body>
</html>
