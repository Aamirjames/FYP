<?php
// Helper function to create a notification for a user

function notify(PDO $pdo, int $userId, string $message, string $link = ''): void
{
    $pdo->prepare("
        INSERT INTO notifications (user_id, message, link)
        VALUES (?, ?, ?)
    ")->execute([$userId, $message, $link ?: null]);
}