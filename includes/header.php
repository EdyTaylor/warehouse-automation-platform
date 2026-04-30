<?php
// Общая шапка для всех страниц
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Склад пленок' ?></title>
    <script>
        (function () {
            window.setUiTheme = function (theme) {
                var next = theme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('ui_theme', next); } catch (_e) {}
            };
            try {
                var theme = localStorage.getItem('ui_theme');
                window.setUiTheme(theme === 'dark' ? 'dark' : 'light');
            } catch (_e) {
                window.setUiTheme('light');
            }
        })();
    </script>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏭</text></svg>">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div>
                <h1>🏭 Склад пленок</h1>
                <p>Система управления складскими операциями</p>
            </div>
            <nav class="nav">
                <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">🏠 Главная</a>
                <a href="warehouse_orders.php" class="nav-link <?= $current_page == 'warehouse_orders.php' ? 'active' : '' ?>">🧰 Место кладовщика</a>
                <a href="warehouse.php" class="nav-link <?= $current_page == 'warehouse.php' ? 'active' : '' ?>">🏪 Склад</a>
                <a href="stock_operations.php" class="nav-link <?= $current_page == 'stock_operations.php' ? 'active' : '' ?>">🧾 Операции</a>
                <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">📦 Товары</a>
                <a href="sell.php" class="nav-link <?= $current_page == 'sell.php' ? 'active' : '' ?>">💰 Продажи</a>
                <a href="manager_dashboard.php" class="nav-link <?= $current_page == 'manager_dashboard.php' ? 'active' : '' ?>">📈 Руководитель <span class="nav-chip nav-chip-analytics">аналитика</span></a>
                <a href="report_day.php" class="nav-link <?= in_array($current_page, ['report_day.php','report_month.php','report_all.php']) ? 'active' : '' ?>">📊 Опер. отчеты <span class="nav-chip nav-chip-ops">учет</span></a>
                <a href="sync_monitor.php" class="nav-link <?= $current_page == 'sync_monitor.php' ? 'active' : '' ?>">⚙️ Интеграция</a>
                <button type="button" class="btn btn-light btn-sm js-theme-toggle">🌓 Тема</button>
            </nav>
        </div>
    </header>

    <?php if (isset($_GET['success'])): ?>
        <div class="container">
            <div class="alert alert-success fade-in">
                ✅ <?= htmlspecialchars($_GET['success']) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="container">
            <div class="alert alert-danger fade-in">
                ❌ <?= htmlspecialchars($_GET['error']) ?>
            </div>
        </div>
    <?php endif; ?>
