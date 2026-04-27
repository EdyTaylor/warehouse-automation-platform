<?php
/**
 * Скрипт для автоматической синхронизации с GitHub
 * Запускать после внесения изменений в код
 */

echo "🔄 Начинаю синхронизацию с GitHub...\n";

// Добавляем все изменения
echo "📝 Добавляю файлы в индекс...\n";
exec('git add . 2>&1', $output, $return_var);
if ($return_var !== 0) {
    echo "❌ Ошибка добавления файлов: " . implode("\n", $output) . "\n";
    exit(1);
}

// Проверяем есть ли изменения для коммита
exec('git status --porcelain', $output, $return_var);
if (empty($output)) {
    echo "✅ Нет изменений для синхронизации\n";
    exit(0);
}

// Делаем коммит
$timestamp = date('Y-m-d H:i:s');
echo "💾 Создаю коммит: $timestamp\n";
exec("git commit -m \"Auto-sync: $timestamp\" 2>&1", $output, $return_var);
if ($return_var !== 0) {
    echo "❌ Ошибка коммита: " . implode("\n", $output) . "\n";
    exit(1);
}

// Отправляем на GitHub
echo "📤 Отправляю на GitHub...\n";
exec('git push origin master 2>&1', $output, $return_var);
if ($return_var !== 0) {
    echo "❌ Ошибка отправки: " . implode("\n", $output) . "\n";
    exit(1);
}

echo "✅ Синхронизация завершена успешно!\n";
?>
