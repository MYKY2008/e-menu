<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ob_start();
try {
    requireLogin();

    $raw     = (string)(file_get_contents('php://input') ?: '{}');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) throw new InvalidArgumentException('Neplatné dáta.');
    $payload = purify($payload);

    if (!csrfValid((string)($payload['csrf'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Bezpečnostná chyba (CSRF).');
    }

    $oldPass  = (string)($payload['old_password']  ?? '');
    $newPass  = (string)($payload['new_password']  ?? '');
    $newPass2 = (string)($payload['new_password2'] ?? '');

    if ($oldPass === '' || $newPass === '' || $newPass2 === '') {
        throw new InvalidArgumentException('Vyplňte všetky polia.');
    }
    if (strlen($newPass) < 8) {
        throw new InvalidArgumentException('Nové heslo musí mať aspoň 8 znakov.');
    }
    if ($newPass !== $newPass2) {
        throw new InvalidArgumentException('Nové heslá sa nezhodujú.');
    }

    $db = getDB();
    $st = $db->prepare("SELECT password FROM users WHERE id = ?");
    $st->execute([(int)$_SESSION['user_id']]);
    $user = $st->fetch();

    if (!$user || !password_verify($oldPass, $user['password'])) {
        throw new InvalidArgumentException('Aktuálne heslo nie je správne.');
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, (int)$_SESSION['user_id']]);

    ob_end_clean();
    echo json_encode(['ok' => true]);

} catch (InvalidArgumentException $e) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
