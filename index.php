<?php
require_once 'dbconfig.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found System – Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 40px 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            font-size: 22px;
            color: #333;
            margin-bottom: 25px;
        }

        .status {
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .details {
            text-align: left;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            font-size: 14px;
            color: #555;
            line-height: 2;
        }

        .details span {
            font-weight: bold;
            color: #333;
        }

        .dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .dot.green { background-color: #28a745; }
        .dot.red   { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="card">
    <h1>🔍 Lost and Found System<br>Database Connection Test</h1>

    <?php if ($conn && !$conn->connect_error): ?>

        <div class="status success">
            <span class="dot green"></span>
            ✅ Connected to database successfully!
        </div>

        <div class="details">
            <div><span>Host:</span> <?= DB_HOST ?></div>
            <div><span>Database:</span> <?= DB_NAME ?></div>
            <div><span>User:</span> <?= DB_USER ?></div>
            <div><span>MySQL Version:</span> <?= $conn->server_info ?></div>

            <?php
                // Verify tables exist
                $tables = ['user', 'item'];
                foreach ($tables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    $exists = $result && $result->num_rows > 0;
                    echo "<div><span>Table '$table':</span> " . ($exists ? "✅ Found" : "❌ Not found") . "</div>";
                }
            ?>
        </div>

    <?php else: ?>

        <div class="status error">
            <span class="dot red"></span>
            ❌ Connection failed!
        </div>

        <div class="details">
            <div><span>Error:</span> <?= $conn->connect_error ?></div>
            <div>Make sure MySQL is running in XAMPP and the database exists.</div>
        </div>

    <?php endif; ?>
</div>

</body>
</html>