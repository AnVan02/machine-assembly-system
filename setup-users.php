<?php
require_once 'config.php';

try {
    // 1. Create users table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100),
        role ENUM('ketoan', 'kythuat', 'admin') DEFAULT 'kythuat',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

    $pdo->exec($sql);
    echo "Table 'users' created or already exists.<br>";

    // 2. Insert sample users if empty
    $check = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($check == 0) {
        $users = [
            ['ketoan', password_hash('123456', PASSWORD_DEFAULT), 'Kế Toán Trưởng', 'ketoan'],
            ['kythuat', password_hash('123456', PASSWORD_DEFAULT), 'Kỹ Thuật Viên', 'kythuat'],
            ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Quản Trị Viên', 'admin']
        ];

        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        echo "Sample users inserted successfully! (Password: 123456 or admin123)<br>";
    } else {
        echo "Users already exist, skipping insertion.<br>";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
