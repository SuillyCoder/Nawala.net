<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: index.php');
    exit();
}

require_once 'dbconfig.php';

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ── CLAIM REQUEST ────────────────────────────────────────────
$claim_msg   = '';
$claim_type  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'claim') {
    $item_id = intval($_POST['item_id'] ?? 0);

    // Check item exists and is unclaimed
    $chk = $conn->prepare("SELECT status FROM item WHERE item_id = ?");
    $chk->bind_param('i', $item_id);
    $chk->execute();
    $chk->store_result();
    $chk->bind_result($current_status);
    $chk->fetch();

    if ($chk->num_rows === 0) {
        $claim_msg  = 'Item not found.';
        $claim_type = 'error';
    } elseif ($current_status !== 'unclaimed') {
        $claim_msg  = 'This item is no longer available for claiming.';
        $claim_type = 'error';
    } else {
        // Set claim_status to pending, record date
        $today = date('Y-m-d');
        $upd = $conn->prepare("UPDATE item SET claim_status = 'pending', claim_date = ? WHERE item_id = ?");
        $upd->bind_param('si', $today, $item_id);
        if ($upd->execute()) {
            $claim_msg  = 'Your claim request has been submitted! The admin will review it shortly.';
            $claim_type = 'success';
        } else {
            $claim_msg  = 'Something went wrong. Please try again.';
            $claim_type = 'error';
        }
        $upd->close();
    }
    $chk->close();
}

// ── FETCH ITEMS ──────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$filter  = $_GET['filter']  ?? 'all';
$sort    = $_GET['sort']    ?? 'newest';

$where_clauses = [];
$params        = [];
$types         = '';

if ($search !== '') {
    $where_clauses[] = "(item_name LIKE ? OR description LIKE ? OR location_found LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

if ($filter === 'unclaimed') {
    $where_clauses[] = "status = 'unclaimed'";
} elseif ($filter === 'claimed') {
    $where_clauses[] = "status = 'claimed'";
} elseif ($filter === 'pending') {
    $where_clauses[] = "claim_status = 'pending'";
}

$where_sql = count($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$order_sql = match($sort) {
    'oldest'   => 'ORDER BY date_found ASC',
    'name_az'  => 'ORDER BY item_name ASC',
    'name_za'  => 'ORDER BY item_name DESC',
    default    => 'ORDER BY date_found DESC',
};

$sql = "SELECT item_id, item_name, description, location_found, date_found, status, claim_status, claim_date, image_path FROM item $where_sql $order_sql";
$stmt  = $conn->prepare($sql);

if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$items  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── TOTAL COUNTS ─────────────────────────────────────────────
$counts = $conn->query("SELECT
    COUNT(*) as total,
    SUM(status = 'unclaimed') as unclaimed,
    SUM(status = 'claimed') as claimed,
    SUM(claim_status = 'pending') as pending
    FROM item")->fetch_assoc();

// Helper: status badge
function statusBadge($status, $claim_status) {
    if ($status === 'claimed')      return ['claimed',   '✓ Claimed',   '#2e7d52', '#e8f5ee'];
    if ($status === 'turned_over')  return ['turned',    '↗ Turned Over','#5b4a9e','#ede9ff'];
    if ($claim_status === 'pending') return ['pending',  '⏳ Pending',   '#a07800', '#fff8e0'];
    return ['unclaimed', '◉ Unclaimed', '#b94040', '#fdecea'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Lost & Found – Browse Items</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
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
            --border: #e2ddd5;
        }
        body { min-height: 100vh; background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--navy); }

        /* NAV */
        nav { background: var(--navy); padding: 0 40px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .nav-brand { font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--white); display: flex; align-items: center; gap: 10px; }
        .nav-brand span { color: var(--gold); }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .nav-user { font-size: 13px; color: #94a3b8; }
        .nav-user strong { color: var(--white); }
        .logout-btn { background: rgba(255,255,255,.08); color: var(--white); border: 1px solid rgba(255,255,255,.15); padding: 7px 16px; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; cursor: pointer; text-decoration: none; transition: background .2s; }
        .logout-btn:hover { background: rgba(255,255,255,.18); }

        /* HERO */
        .hero { background: var(--navy); padding: 48px 40px 40px; position: relative; overflow: hidden; }
        .hero::after { content: ''; position: absolute; width: 400px; height: 400px; border-radius: 50%; background: var(--teal); opacity: .07; top: -100px; right: -80px; }
        .hero-inner { max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; }
        .hero-tag { display: inline-flex; align-items: center; gap: 8px; background: rgba(201,168,76,.15); color: var(--gold); font-size: 11px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; margin-bottom: 16px; border: 1px solid rgba(201,168,76,.25); }
        .hero h1 { font-family: 'DM Serif Display', serif; font-size: 36px; color: var(--white); margin-bottom: 8px; }
        .hero h1 em { font-style: italic; color: var(--gold); }
        .hero p { font-size: 15px; color: #94a3b8; max-width: 500px; line-height: 1.6; }

        /* STAT CARDS */
        .stats-row { max-width: 1100px; margin: -24px auto 0; padding: 0 40px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; position: relative; z-index: 10; }
        .stat-card { background: var(--white); border-radius: 12px; padding: 20px 22px; box-shadow: 0 4px 20px rgba(15,31,56,.09); border-top: 3px solid var(--teal); }
        .stat-card.gold-top  { border-top-color: var(--gold); }
        .stat-card.red-top   { border-top-color: var(--red); }
        .stat-card.green-top { border-top-color: var(--green); }
        .stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 8px; }
        .stat-value { font-family: 'DM Serif Display', serif; font-size: 30px; color: var(--navy); }

        /* TOOLBAR */
        .toolbar { max-width: 1100px; margin: 36px auto 20px; padding: 0 40px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-wrap { flex: 1; min-width: 200px; position: relative; }
        .search-wrap input { width: 100%; padding: 11px 16px 11px 42px; border: 1.5px solid var(--border); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; background: var(--white); color: var(--navy); outline: none; transition: border-color .2s, box-shadow .2s; }
        .search-wrap input:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(26,107,114,.1); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; pointer-events: none; }
        select { padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; background: var(--white); color: var(--navy); outline: none; cursor: pointer; transition: border-color .2s; }
        select:focus { border-color: var(--teal); }
        .search-btn { padding: 11px 22px; background: var(--teal); color: var(--white); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; transition: background .2s; white-space: nowrap; }
        .search-btn:hover { background: var(--navy); }

        /* ALERT */
        .alert-wrap { max-width: 1100px; margin: 0 auto 16px; padding: 0 40px; }
        .alert { padding: 13px 18px; border-radius: 10px; font-size: 14px; font-weight: 500; }
        .alert.success { background: #e8f5ee; color: var(--green); border-left: 3px solid var(--green); }
        .alert.error   { background: #fdecea; color: var(--red);   border-left: 3px solid var(--red); }

        /* ITEMS GRID */
        .items-wrap { max-width: 1100px; margin: 0 auto 60px; padding: 0 40px; }
        .results-label { font-size: 13px; color: #9ca3af; margin-bottom: 16px; }
        .results-label strong { color: var(--navy); }

        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

        .item-card { background: var(--white); border-radius: 14px; box-shadow: 0 2px 12px rgba(15,31,56,.07); border: 1.5px solid var(--border); overflow: hidden; transition: transform .2s, box-shadow .2s; display: flex; flex-direction: column; animation: fadeIn .4s ease both; }
        .item-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(15,31,56,.13); }
        @keyframes fadeIn { from { opacity:0; transform:translateY(12px);} to { opacity:1; transform:translateY(0);} }

        .card-header { padding: 18px 20px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .item-name { font-family: 'DM Serif Display', serif; font-size: 18px; color: var(--navy); line-height: 1.2; }
        .status-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }

        .card-body { padding: 16px 20px; flex: 1; }
        .card-field { display: flex; gap: 8px; margin-bottom: 10px; font-size: 13.5px; }
        .card-field .icon { flex-shrink: 0; font-size: 15px; margin-top: 1px; }
        .card-field .label { color: #9ca3af; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; display: block; margin-bottom: 1px; }
        .card-field .val { color: var(--navy); line-height: 1.4; }

        .card-footer { padding: 14px 20px; border-top: 1px solid var(--border); }
        .claim-btn { width: 100%; padding: 10px; background: var(--navy); color: var(--white); border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: background .2s; }
        .claim-btn:hover { background: var(--teal); }
        .claim-btn:disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; }
        .claimed-note { text-align: center; font-size: 13px; color: #9ca3af; padding: 4px 0; }

        /* EMPTY STATE */
        .empty { text-align: center; padding: 80px 40px; color: #9ca3af; }
        .empty .icon { font-size: 56px; margin-bottom: 16px; }
        .empty h3 { font-family: 'DM Serif Display', serif; font-size: 22px; color: #c4bdb4; margin-bottom: 8px; }
        .empty p { font-size: 14px; }

        /* MODAL */
        .overlay { display: none; position: fixed; inset: 0; background: rgba(15,31,56,.55); backdrop-filter: blur(4px); z-index: 200; align-items: center; justify-content: center; }
        .overlay.active { display: flex; }
        .modal { background: var(--white); border-radius: 18px; width: 480px; max-width: 95vw; box-shadow: 0 20px 60px rgba(15,31,56,.2); animation: slideUp .3s cubic-bezier(.16,1,.3,1) both; overflow: hidden; }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
        .modal-header { background: var(--navy); padding: 24px 28px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--white); }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 22px; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: var(--white); }
        .modal-body { padding: 28px; }
        .modal-field { margin-bottom: 16px; }
        .modal-field .mlabel { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin-bottom: 5px; }
        .modal-field .mval { font-size: 15px; color: var(--navy); line-height: 1.5; }
        .modal-field .mval.desc { color: #555; font-size: 14px; }
        .modal-divider { border: none; border-top: 1px solid var(--border); margin: 18px 0; }
        .modal-footer { padding: 0 28px 24px; display: flex; gap: 10px; }
        .modal-footer .btn { flex: 1; padding: 12px; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; border: none; transition: background .2s; }
        .btn-cancel { background: var(--border); color: var(--navy); }
        .btn-cancel:hover { background: #d4cfc6; }
        .btn-confirm { background: var(--teal); color: var(--white); }
        .btn-confirm:hover { background: var(--navy); }
        .btn-confirm:disabled { background: #d1d5db; color: #9ca3af; cursor: not-allowed; }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-brand">🔍 <span>Lost</span> & Found</div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?= htmlspecialchars($username) ?></strong></span>
        <a href="logout.php" class="logout-btn">Sign Out</a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-inner">
        <div class="hero-tag">👤 User Portal</div>
        <h1>Browse <em>Found Items.</em></h1>
        <p>Search through reported items and submit a claim request if you recognize something that belongs to you.</p>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-label">Total Items</div>
        <div class="stat-value"><?= $counts['total'] ?? 0 ?></div>
    </div>
    <div class="stat-card red-top">
        <div class="stat-label">Unclaimed</div>
        <div class="stat-value"><?= $counts['unclaimed'] ?? 0 ?></div>
    </div>
    <div class="stat-card green-top">
        <div class="stat-label">Claimed</div>
        <div class="stat-value"><?= $counts['claimed'] ?? 0 ?></div>
    </div>
    <div class="stat-card gold-top">
        <div class="stat-label">Pending Claims</div>
        <div class="stat-value"><?= $counts['pending'] ?? 0 ?></div>
    </div>
</div>

<!-- ALERT -->
<?php if ($claim_msg): ?>
<div class="alert-wrap" style="margin-top:36px;">
    <div class="alert <?= $claim_type ?>"><?= htmlspecialchars($claim_msg) ?></div>
</div>
<?php endif; ?>

<!-- TOOLBAR -->
<form method="GET" action="user.php">
    <div class="toolbar" style="<?= $claim_msg ? 'margin-top:12px;' : '' ?>">
        <div class="search-wrap">
            <span class="search-icon">🔎</span>
            <input type="text" name="search" placeholder="Search by name, description, or location…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <select name="filter">
            <option value="all"      <?= $filter==='all'      ? 'selected':'' ?>>All Items</option>
            <option value="unclaimed"<?= $filter==='unclaimed' ? 'selected':'' ?>>Unclaimed</option>
            <option value="claimed"  <?= $filter==='claimed'   ? 'selected':'' ?>>Claimed</option>
            <option value="pending"  <?= $filter==='pending'   ? 'selected':'' ?>>Pending Claims</option>
        </select>
        <select name="sort">
            <option value="newest"  <?= $sort==='newest'  ? 'selected':'' ?>>Newest First</option>
            <option value="oldest"  <?= $sort==='oldest'  ? 'selected':'' ?>>Oldest First</option>
            <option value="name_az" <?= $sort==='name_az' ? 'selected':'' ?>>Name A → Z</option>
            <option value="name_za" <?= $sort==='name_za' ? 'selected':'' ?>>Name Z → A</option>
        </select>
        <button type="submit" class="search-btn">Search</button>
        <?php if ($search || $filter !== 'all' || $sort !== 'newest'): ?>
            <a href="user.php" style="font-size:13px;color:#9ca3af;text-decoration:none;white-space:nowrap;">✕ Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- ITEMS -->
<div class="items-wrap">
    <p class="results-label">Showing <strong><?= count($items) ?></strong> item<?= count($items) !== 1 ? 's' : '' ?><?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?></p>

    <?php if (empty($items)): ?>
        <div class="empty">
            <div class="icon">📭</div>
            <h3>No items found</h3>
            <p>Try adjusting your search or filter options.</p>
        </div>
    <?php else: ?>
        <div class="items-grid">
            <?php foreach ($items as $i => $item):
                [$cls, $label, $color, $bg] = statusBadge($item['status'], $item['claim_status']);
                $can_claim = $item['status'] === 'unclaimed' && $item['claim_status'] !== 'pending';
            ?>
            <div class="item-card" style="animation-delay: <?= $i * 0.04 ?>s">
                <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/' . $item['image_path'])): ?>
                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="Item photo"
                    style="width:100%;height:180px;object-fit:cover;border-bottom:1px solid var(--border);">
                <?php else: ?>
                    div style="width:100%;height:110px;background:#f0ebe2;display:flex;align-items:center;justify-content:center;font-size:36px;border-bottom:1px solid var(--border);">📦</div>
                <?php endif; ?>
                <div class="card-header">
                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                    <span class="status-badge" style="color:<?= $color ?>;background:<?= $bg ?>"><?= $label ?></span>
                </div>
                <div class="card-body">
                    <div class="card-field">
                        <span class="icon">📍</span>
                        <div>
                            <span class="label">Location Found</span>
                            <span class="val"><?= htmlspecialchars($item['location_found']) ?></span>
                        </div>
                    </div>
                    <div class="card-field">
                        <span class="icon">📅</span>
                        <div>
                            <span class="label">Date Found</span>
                            <span class="val"><?= date('F j, Y', strtotime($item['date_found'])) ?></span>
                        </div>
                    </div>
                    <?php if ($item['description']): ?>
                    <div class="card-field">
                        <span class="icon">📝</span>
                        <div>
                            <span class="label">Description</span>
                            <span class="val"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 80, '…')) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['claim_date']): ?>
                    <div class="card-field">
                        <span class="icon">🗓</span>
                        <div>
                            <span class="label">Claim Date</span>
                            <span class="val"><?= date('F j, Y', strtotime($item['claim_date'])) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <?php if ($can_claim): ?>
                        <button class="claim-btn"
                            onclick="openClaim(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', '<?= htmlspecialchars(addslashes($item['location_found'])) ?>', '<?= date('F j, Y', strtotime($item['date_found'])) ?>', '<?= htmlspecialchars(addslashes($item['description'] ?? '')) ?>')">
                            Request Claim →
                        </button>
                    <?php elseif ($item['claim_status'] === 'pending'): ?>
                        <p class="claimed-note">⏳ Claim pending admin review</p>
                    <?php else: ?>
                        <p class="claimed-note">This item is no longer available</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- CLAIM MODAL -->
<div class="overlay" id="claimOverlay" onclick="closeOnOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <h2>Confirm Claim Request</h2>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;color:#6b7280;margin-bottom:20px;line-height:1.6;">
                You are about to submit a claim request for this item. The admin will be notified and will review your request.
            </p>
            <div class="modal-field">
                <div class="mlabel">Item Name</div>
                <div class="mval" id="modal-name">—</div>
            </div>
            <div class="modal-field">
                <div class="mlabel">Location Found</div>
                <div class="mval" id="modal-location">—</div>
            </div>
            <div class="modal-field">
                <div class="mlabel">Date Found</div>
                <div class="mval" id="modal-date">—</div>
            </div>
            <div class="modal-field">
                <div class="mlabel">Description</div>
                <div class="mval desc" id="modal-desc">—</div>
            </div>
            <hr class="modal-divider">
            <p style="font-size:13px;color:#9ca3af;">Claiming as: <strong style="color:var(--navy)"><?= htmlspecialchars($username) ?></strong></p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-cancel" onclick="closeModal()">Cancel</button>
            <form method="POST" action="user.php" style="flex:1;display:flex;">
                <input type="hidden" name="action" value="claim">
                <input type="hidden" name="item_id" id="modal-item-id" value="">
                <button type="submit" class="btn btn-confirm">Submit Claim →</button>
            </form>
        </div>
    </div>
</div>

<script>
    const overlay = document.getElementById('claimOverlay');

    function openClaim(id, name, location, date, desc) {
        document.getElementById('modal-item-id').value  = id;
        document.getElementById('modal-name').textContent     = name;
        document.getElementById('modal-location').textContent = location;
        document.getElementById('modal-date').textContent     = date;
        document.getElementById('modal-desc').textContent     = desc || 'No description provided.';
        overlay.classList.add('active');
    }

    function closeModal()          { overlay.classList.remove('active'); }
    function closeOnOverlay(e)     { if (e.target === overlay) closeModal(); }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>