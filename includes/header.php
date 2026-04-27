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
                <a href="warehouse.php" class="nav-link <?= $current_page == 'warehouse.php' ? 'active' : '' ?>">🏪 Склад</a>
                <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">📦 Товары</a>
                <a href="sell.php" class="nav-link <?= $current_page == 'sell.php' ? 'active' : '' ?>">💰 Продажи</a>
                <a href="b24_sales.php" class="nav-link <?= $current_page == 'b24_sales.php' ? 'active' : '' ?>">🔄 Б24</a>
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
