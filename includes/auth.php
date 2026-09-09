<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (ob_get_level() === 0) ob_start();
require_once __DIR__ . '/../config/database.php';

function user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return user() !== null; }
function require_login(): void { if (!is_logged_in()) { header('Location: login.php'); exit; } }
function require_role(string $role): void {
    require_login();
    if (user()['role'] !== $role) { header('Location: '.(user()['role'] === 'admin' ? 'dashboard_admin.php' : 'catalog.php')); exit; }
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header("Location: $url"); exit; }
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; }
    $value = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $value;
}
function device_transaction_time(): string {
    $value = trim($_POST['device_datetime'] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d H:i:s', $value);
    $errors = DateTime::getLastErrors();
    if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d H:i:s') === $value) {
        return $value;
    }
    return date('Y-m-d H:i:s');
}
