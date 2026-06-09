<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('forgot-password'));
    exit;
}

if (!csrfValid((string)($_POST['csrf'] ?? ''))) {
    flash('Bezpečnostná chyba. Skúste znova.', 'error');
    header('Location: ' . url('forgot-password'));
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('Zadajte platný e-mail.', 'error');
    header('Location: ' . url('forgot-password'));
    exit;
}

// Always show the same message to prevent user enumeration
$successMsg = 'Ak je e-mail registrovaný, pošleme vám odkaz na reset hesla. Skontrolujte svoju schránku.';

$db = getDB();
$st = $db->prepare("SELECT id FROM users WHERE username = ?");
$st->execute([$email]);
$user = $st->fetch();

if ($user) {
    $token   = bin2hex(random_bytes(32));
    $expires = time() + 3600;

    // Remove any existing tokens for this email
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
       ->execute([$email, $token, $expires]);

    $resetLink = baseUrl() . url('reset-password') . '?token=' . $token;

    $subject = 'Obnovenie hesla — GastroLink QR';
    $body    = emailResetTemplate($email, $resetLink);

    sendEmail($email, $subject, $body);
}

flash($successMsg, 'success');
header('Location: ' . url('forgot-password'));
exit;

function emailResetTemplate(string $email, string $link): string {
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeLink  = htmlspecialchars($link,  ENT_QUOTES, 'UTF-8');
    return <<<HTML
    <!DOCTYPE html>
    <html lang="sk">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f8fafc;font-family:'Inter',Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:40px 0;">
        <tr><td align="center">
          <table width="100%" style="max-width:520px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);">

            <!-- Header -->
            <tr>
              <td style="background:linear-gradient(135deg,#4f46e5,#0ea5e9);padding:36px 40px;text-align:center;">
                <p style="margin:0;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;">
                  GastroLink<span style="color:#6ee7b7;">QR</span>
                </p>
                <p style="margin:8px 0 0;font-size:13px;color:rgba(255,255,255,0.8);">Obnova hesla</p>
              </td>
            </tr>

            <!-- Body -->
            <tr>
              <td style="padding:40px;">
                <p style="margin:0 0 16px;font-size:16px;color:#1e293b;font-weight:600;">Dobrý deň,</p>
                <p style="margin:0 0 24px;font-size:14px;color:#475569;line-height:1.6;">
                  Dostali sme žiadosť o reset hesla pre účet <strong style="color:#1e293b;">{$safeEmail}</strong>.
                  Kliknite na tlačidlo nižšie a nastavte si nové heslo.
                </p>

                <!-- CTA Button -->
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td align="center" style="padding:8px 0 32px;">
                      <a href="{$safeLink}"
                         style="display:inline-block;background:#4f46e5;color:#ffffff;font-size:15px;
                                font-weight:700;text-decoration:none;padding:14px 36px;
                                border-radius:100px;letter-spacing:0.2px;">
                        Nastaviť nové heslo
                      </a>
                    </td>
                  </tr>
                </table>

                <p style="margin:0 0 8px;font-size:13px;color:#94a3b8;">
                  Odkaz je platný <strong>1 hodinu</strong>. Ak ste si heslo nežiadali obnovy, tento e-mail ignorujte.
                </p>
                <p style="margin:0;font-size:12px;color:#cbd5e1;word-break:break-all;">
                  Alebo skopírujte odkaz: {$safeLink}
                </p>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style="background:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #f1f5f9;">
                <p style="margin:0;font-size:12px;color:#94a3b8;">
                  &copy; GastroLink QR &mdash; digitálne menu pre reštaurácie
                </p>
              </td>
            </tr>

          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}
