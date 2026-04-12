<?php
require_once __DIR__ . '/../config/app.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit("Forbidden");
    }
}

function redirectByRole(string $role): void
{
    if ($role === 'admin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } elseif ($role === 'client') {
        header("Location: " . BASE_URL . "/client/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "/freelancer/dashboard.php");
    }
    exit;
}