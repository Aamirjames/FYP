<?php
// SSH04: Messaging between Client and Freelancer (tied to a job)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/notify.php';

requireLogin();

$myId = (int) $_SESSION['user']['id'];
$myRole = $_SESSION['user']['role'];
$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0 || $myRole === 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

// Load job — verify the current user is either the client or the hired freelancer
$stmt = $pdo->prepare("
    SELECT j.job_id, j.title, j.status,
           j.client_id, j.hired_freelancer_id,
           uc.name AS client_name,
           uf.name AS freelancer_name
    FROM job j
    JOIN users uc ON uc.user_id = j.client_id
    LEFT JOIN users uf ON uf.user_id = j.hired_freelancer_id
    WHERE j.job_id = ?
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    exit("Job not found.");
}

// Access control — only the client or hired freelancer can message
$isClient = ($myRole === 'client' && (int) $job['client_id'] === $myId);
$isFreelancer = ($myRole === 'freelancer' && (int) $job['hired_freelancer_id'] === $myId);

if (!$isClient && !$isFreelancer) {
    http_response_code(403);
    exit("You are not part of this job.");
}

// Determine the other person
$otherId = $isClient ? (int) $job['hired_freelancer_id'] : (int) $job['client_id'];
$otherName = $isClient ? $job['freelancer_name'] : $job['client_name'];

if (!$otherId) {
    http_response_code(403);
    exit("No freelancer has been hired for this job yet.");
}

$msg = '';
$err = '';

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $text = trim($_POST['message'] ?? '');

    if ($text === '') {
        $err = "Message cannot be empty.";
    } elseif (strlen($text) > 2000) {
        $err = "Message too long (max 2000 characters).";
    } else {
        $pdo->prepare("
            INSERT INTO messages (job_id, sender_id, receiver_id, message)
            VALUES (?, ?, ?, ?)
        ")->execute([$jobId, $myId, $otherId, $text]);

        // Notify receiver (SSH05) — only if they have no unread messages from this sender already
        $unreadCheck = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0 AND link LIKE ?");
        $unreadCheck->execute([$otherId, "%messages.php?job_id={$jobId}%"]);
        if ((int) $unreadCheck->fetchColumn() === 0) {
            $senderName = $_SESSION['user']['name'];
            notify(
                $pdo,
                $otherId,
                "💬 New message from {$senderName} about: {$job['title']}",
                "messages.php?job_id={$jobId}"
            );
        }

        header("Location: " . BASE_URL . "/messages.php?job_id=" . $jobId);
        exit;
    }
}

// Mark all unread messages sent TO me as read
$pdo->prepare("
    UPDATE messages SET is_read = 1
    WHERE job_id = ? AND receiver_id = ? AND is_read = 0
")->execute([$jobId, $myId]);

// Load full conversation
$stmt = $pdo->prepare("
    SELECT m.message_id, m.message, m.created_at, m.is_read,
           m.sender_id,
           u.name AS sender_name
    FROM messages m
    JOIN users u ON u.user_id = m.sender_id
    WHERE m.job_id = ?
      AND (
            (m.sender_id = ? AND m.receiver_id = ?)
         OR (m.sender_id = ? AND m.receiver_id = ?)
          )
    ORDER BY m.created_at ASC
");
$stmt->execute([$jobId, $myId, $otherId, $otherId, $myId]);
$conversation = $stmt->fetchAll();

$title = "Messages — " . $job['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="chat-wrapper">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h3 class="mb-1 fw-bold">💬 Messages</h3>
                <div class="text-muted2" style="font-size:.88rem;">
                    📋 Job: <strong>
                        <?= htmlspecialchars($job['title']) ?>
                    </strong>
                    &bull; Chatting with: <strong style="color:var(--brand);">
                        <?= htmlspecialchars($otherName) ?>
                    </strong>
                </div>
            </div>
        </div>

        <?php if ($err): ?>
            <div class="alert alert-danger mb-3">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <!-- Chat Box -->
        <div class="chat-box" id="chatBox">
            <?php if (!$conversation): ?>
                <div class="chat-empty">
                    <div style="font-size:2rem;">💬</div>
                    <div>No messages yet. Say hello!</div>
                </div>
            <?php else: ?>
                <?php foreach ($conversation as $m):
                    $mine = (int) $m['sender_id'] === $myId;
                    ?>
                    <div class="bubble-wrap <?= $mine ? 'mine' : 'theirs' ?>">
                        <div class="bubble <?= $mine ? 'mine' : 'theirs' ?>">
                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                        </div>
                        <div class="bubble-meta">
                            <?= htmlspecialchars($m['sender_name']) ?>
                            &bull;
                            <?= date('d M, h:i A', strtotime($m['created_at'])) ?>
                            <?php if ($mine && $m['is_read']): ?>
                                &bull; <span style="color:#4ade80;">✓ Read</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Input Bar -->
        <form method="post" class="chat-input-bar" id="chatForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Emoji Picker Button -->
            <div class="position-relative">
                <button type="button" class="btn btn-outline-secondary rounded-pill" id="emojiBtn"
                    style="height:44px; font-size:1.2rem;" title="Emoji">
                    😊
                </button>

                <!-- Emoji Panel -->
                <div id="emojiPanel" class="emoji-panel" style="display:none;">
                    <div class="emoji-grid">
                        <?php
                        $emojis = [
                            '😊',
                            '😄',
                            '😂',
                            '🤣',
                            '😍',
                            '🥰',
                            '😎',
                            '🤩',
                            '👍',
                            '👏',
                            '🙌',
                            '🤝',
                            '💪',
                            '🎉',
                            '✅',
                            '🔥',
                            '❤️',
                            '💯',
                            '⭐',
                            '🌟',
                            '💡',
                            '📌',
                            '📎',
                            '🖊️',
                            '😅',
                            '😬',
                            '🤔',
                            '😮',
                            '😢',
                            '😴',
                            '🥳',
                            '😤',
                            '🙏',
                            '👋',
                            '✌️',
                            '🤞',
                            '👀',
                            '💬',
                            '📩',
                            '⏰',
                            '🚀',
                            '💻',
                            '🖥️',
                            '📱',
                            '📂',
                            '📋',
                            '💰',
                            '🏆',
                        ];
                        foreach ($emojis as $e) {
                            echo "<button type=\"button\" class=\"emoji-btn\" data-emoji=\"{$e}\">{$e}</button>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <textarea class="form-control" name="message" id="msgInput" rows="1"
                placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>

            <button class="btn btn-brand rounded-pill px-4" style="height:44px;">Send</button>
        </form>

    </div>
</div>

<script>
    // Auto-scroll chat to bottom on load
    const chatBox = document.getElementById('chatBox');
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    // Auto-grow textarea
    const msgInput = document.getElementById('msgInput');
    if (msgInput) {
        msgInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Send on Enter, new line on Shift+Enter
        msgInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    document.getElementById('chatForm').submit();
                }
            }
        });
    }

    // Emoji picker toggle
    const emojiBtn = document.getElementById('emojiBtn');
    const emojiPanel = document.getElementById('emojiPanel');

    emojiBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = emojiPanel.style.display === 'block';
        emojiPanel.style.display = isOpen ? 'none' : 'block';
    });

    // Close panel when clicking outside
    document.addEventListener('click', function (e) {
        if (!emojiPanel.contains(e.target) && e.target !== emojiBtn) {
            emojiPanel.style.display = 'none';
        }
    });

    // Insert emoji into textarea at cursor position
    document.querySelectorAll('.emoji-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const emoji = this.getAttribute('data-emoji');
            const start = msgInput.selectionStart;
            const end = msgInput.selectionEnd;
            const before = msgInput.value.substring(0, start);
            const after = msgInput.value.substring(end);

            msgInput.value = before + emoji + after;

            // Move cursor after inserted emoji
            const newPos = start + emoji.length;
            msgInput.setSelectionRange(newPos, newPos);
            msgInput.focus();

            // Auto-grow after insert
            msgInput.style.height = 'auto';
            msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';

            emojiPanel.style.display = 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>