<?php
/**
 * سكربت التثبيت: ينشئ قاعدة البيانات وينفذ ملفات SQL وينشئ مستخدم المدير
 * الاستخدام: php install.php
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
$cfg = (require BASE_PATH . '/config/config.php')['db'];

echo "== تثبيت نظام Top Organic ==\n";

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("فشل الاتصال بخادم MySQL: {$e->getMessage()}\n");
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$cfg['name']}`");
echo "تم إنشاء قاعدة البيانات: {$cfg['name']}\n";

$files = glob(BASE_PATH . '/database/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    // تخطي ملف البيانات الأولية إذا سبق تنفيذه
    if ($name === '09_seed.sql') {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        if ($count > 0) {
            echo "تخطي $name (البيانات الأولية موجودة)\n";
            continue;
        }
    }
    // تخطي بذر الهيكل التنظيمي إذا سبق تنفيذه (الجدول ينشأ دائماً، لكن لا نكرر الإدخالات)
    if ($name === '10_orgchart.sql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS org_positions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NULL,
            title VARCHAR(150) NOT NULL,
            employee_name VARCHAR(150) NULL,
            employee_id INT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES org_positions(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $count = (int)$pdo->query("SELECT COUNT(*) FROM org_positions")->fetchColumn();
        if ($count > 0) {
            echo "تخطي $name (الهيكل التنظيمي موجود مسبقاً)\n";
            continue;
        }
    }
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "تم تنفيذ: $name\n";
}

// إنشاء مستخدم المدير الافتراضي
$exists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($exists === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, full_name, role_id, branch_id)
         VALUES ('admin', ?, 'مدير النظام', 1, 1)"
    );
    $stmt->execute([$hash]);
    echo "تم إنشاء المستخدم: admin / admin123\n";
} else {
    echo "المستخدم admin موجود مسبقاً\n";
}

echo "== اكتمل التثبيت بنجاح ==\n";
echo "لتشغيل النظام: php -S localhost:8000 -t public\n";
