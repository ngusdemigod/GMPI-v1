<?php
/**
 * Database Viewer Script
 * Church Financial Partnership System
 * View all database tables and their data
 */

require_once __DIR__ . '/includes/config.php';

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer - Church Partnership System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #D4AF37;
            margin-bottom: 30px;
            font-size: 2em;
        }
        .db-info {
            background: rgba(255,255,255,0.1);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .db-info span {
            color: #D4AF37;
            font-weight: bold;
        }
        .table-list {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }
        .table-btn {
            background: linear-gradient(135deg, #D4AF37 0%, #C5A028 100%);
            color: #1a1a2e;
            border: none;
            padding: 15px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-align: left;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .table-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.4);
        }
        .table-btn.active {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #D4AF37;
            border: 2px solid #D4AF37;
        }
        .table-data {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: none;
        }
        .table-data.show {
            display: block;
        }
        .table-data h2 {
            color: #D4AF37;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
        }
        .table-data .row-count {
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            background: rgba(212, 175, 55, 0.2);
            color: #D4AF37;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: rgba(255,255,255,0.05);
        }
        .back-btn {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .error {
            background: rgba(255, 100, 100, 0.2);
            border: 1px solid #ff6464;
            padding: 15px;
            border-radius: 8px;
            color: #ff6464;
            margin-bottom: 20px;
        }
        .success {
            background: rgba(100, 255, 100, 0.2);
            border: 1px solid #64ff64;
            padding: 15px;
            border-radius: 8px;
            color: #64ff64;
            margin-bottom: 20px;
        }
        .export-btn {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .export-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Database Viewer</h1>
        
        <div class="db-info">
            Database: <span><?php echo DB_NAME; ?></span> | 
            Connected: <span><?php echo DB_HOST; ?></span>
        </div>

        <?php
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                echo '<div class="error">No tables found in the database!</div>';
            } else {
                echo '<div class="table-list">';
                foreach ($tables as $table) {
                    echo '<button class="table-btn" onclick="showTableData(\'' . htmlspecialchars($table) . '\')">'
                         . htmlspecialchars($table) . '</button>';
                }
                echo '</div>';
                
                // Create table data containers
                foreach ($tables as $table) {
                    echo '<div class="table-data" id="data-' . htmlspecialchars($table) . '">';
                    echo '<h2>📋 Table: ' . htmlspecialchars($table) . '</h2>';
                    
                    // Get row count
                    $countStmt = $pdo->query("SELECT COUNT(*) FROM " . $table);
                    $rowCount = $countStmt->fetchColumn();
                    echo '<div class="row-count">Total rows: ' . $rowCount . '</div>';
                    
                    // Get column names
                    $stmt = $pdo->query("DESCRIBE " . $table);
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $columnNames = array_column($columns, 'Field');
                    
                    // Get all data
                    $stmt = $pdo->query("SELECT * FROM " . $table . " LIMIT 100");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        echo '<table>';
                        echo '<thead><tr>';
                        foreach ($columnNames as $col) {
                            echo '<th>' . htmlspecialchars($col) . '</th>';
                        }
                        echo '</tr></thead><tbody>';
                        
                        foreach ($rows as $row) {
                            echo '<tr>';
                            foreach ($columnNames as $col) {
                                $value = $row[$col] ?? 'NULL';
                                if (is_string($value) && strlen($value) > 100) {
                                    $value = substr($value, 0, 100) . '...';
                                }
                                echo '<td>' . htmlspecialchars($value) . '</td>';
                            }
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                        
                        if ($rowCount > 100) {
                            echo '<p style="color: #888; margin-top: 15px;">Showing first 100 rows. Total: ' . $rowCount . ' rows.</p>';
                        }
                    } else {
                        echo '<p style="color: #888;">No data in this table.</p>';
                    }
                    
                    echo '</div>';
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
    
    <script>
        function showTableData(tableName) {
            // Hide all table data
            document.querySelectorAll('.table-data').forEach(el => el.classList.remove('show'));
            
            // Remove active class from all buttons
            document.querySelectorAll('.table-btn').forEach(btn => btn.classList.remove('active'));
            
            // Show selected table data
            const dataDiv = document.getElementById('data-' + tableName);
            if (dataDiv) {
                dataDiv.classList.add('show');
                dataDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
<?php
ob_end_flush();