<?php
declare(strict_types=1);
defined('BASE_DIR') or die('Access denied');
/**
 * SuperFaktura API helper.
 * Requires SF_EMAIL, SF_API_KEY, SF_COMPANY_ID in .env.
 * Optional: SF_BASE_URL (default: https://moja.superfaktura.sk)
 */

function _sfBaseUrl(): string {
    return rtrim($_ENV['SF_BASE_URL'] ?? 'https://moja.superfaktura.sk', '/');
}

function _sfAuthHeader(): string {
    $email     = $_ENV['SF_EMAIL']      ?? '';
    $apiKey    = $_ENV['SF_API_KEY']    ?? '';
    $companyId = $_ENV['SF_COMPANY_ID'] ?? '';
    return "SFAPI email={$email}&apikey={$apiKey}&company_id={$companyId}&module=API";
}

function _sfConfigured(): bool {
    return ($_ENV['SF_EMAIL'] ?? '') !== '' && ($_ENV['SF_API_KEY'] ?? '') !== '';
}

/**
 * Vytvorí faktúru v SuperFaktura.
 * @param array $order  Riadok z tabuľky orders (id, plan_name, amount, currency)
 * @param array $user   Riadok z tabuľky users (username, company_name, ico, …)
 * @return string|null  SuperFaktura invoice ID alebo null pri chybe
 */
function sfCreateInvoice(array $order, array $user): ?string {
    if (!_sfConfigured()) {
        gl_log('SuperFaktura: SF_EMAIL alebo SF_API_KEY nie je nastavený.');
        return null;
    }

    $planLabels = ['pro' => 'Pro', 'ultra' => 'Ultra', 'custom' => 'Custom'];
    $planLabel  = $planLabels[$order['plan_name'] ?? ''] ?? 'Predplatné';
    $amount     = (float)($order['amount'] ?? 0);

    // Klient — firma alebo súkromná osoba
    $hasCompany = !empty(trim((string)($user['company_name'] ?? '')));
    $client = ['email' => (string)($user['username'] ?? '')];
    if ($hasCompany) {
        $client['name']    = (string)$user['company_name'];
        $client['ico']     = (string)($user['ico']             ?? '');
        $client['dic']     = (string)($user['dic']             ?? '');
        $client['ic_dph']  = (string)($user['ic_dph']          ?? '');
        $client['address'] = (string)($user['billing_street']  ?? '');
        $client['city']    = (string)($user['billing_city']    ?? '');
        $client['zip']     = (string)($user['billing_zip']     ?? '');
        $client['country'] = (string)($user['billing_country'] ?? 'Slovensko');
    } else {
        // Súkromná osoba — email ako meno
        $client['name'] = (string)($user['username'] ?? 'Zákazník');
    }

    $invoicePayload = json_encode([
        'Invoice' => [
            'name'     => 'GastroLink QR — ' . $planLabel . ' plán',
            'variable' => (string)($order['id'] ?? ''),
            'currency' => (string)($order['currency'] ?? 'EUR'),
        ],
        'Client'      => $client,
        'InvoiceItem' => [[
            'name'       => 'GastroLink QR — ' . $planLabel . ' plán (mesačné predplatné)',
            'quantity'   => 1,
            'unit'       => 'ks',
            'unit_price' => $amount,
            'tax'        => 0,
        ]],
    ]);

    $ch = curl_init(_sfBaseUrl() . '/invoices/create');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'data=' . urlencode((string)$invoicePayload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . _sfAuthHeader(),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || ($httpCode !== 200 && $httpCode !== 201)) {
        gl_log('SuperFaktura createInvoice HTTP ' . $httpCode . ': ' . (string)$response);
        return null;
    }

    $data  = json_decode((string)$response, true);
    $error = (int)($data['error'] ?? 1);
    if ($error !== 0) {
        gl_log('SuperFaktura createInvoice error: ' . ($data['error_message'] ?? (string)$response));
        return null;
    }

    $invoiceId = (string)($data['data']['Invoice']['id'] ?? '');
    if ($invoiceId === '') {
        gl_log('SuperFaktura createInvoice: chýba invoice ID. Response: ' . (string)$response);
        return null;
    }

    return $invoiceId;
}

/**
 * Odošle faktúru emailom zákazníkovi cez SuperFaktura.
 * @return bool  true pri úspešnom odoslaní, false pri chybe (chyba je zalogovaná)
 */
function sfSendInvoice(int $invoiceId, string $toEmail): bool {
    if (!_sfConfigured()) {
        gl_log('SuperFaktura: SF_EMAIL alebo SF_API_KEY nie je nastavený.');
        return false;
    }

    $payload = json_encode([
        'Invoice' => ['id' => $invoiceId],
        'Email'   => ['to' => $toEmail],
    ]);

    $ch = curl_init(_sfBaseUrl() . '/invoices/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'data=' . urlencode((string)$payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . _sfAuthHeader(),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || ($httpCode !== 200 && $httpCode !== 201)) {
        gl_log('SuperFaktura sendInvoice HTTP ' . $httpCode . ' id=' . $invoiceId . ': ' . (string)$response);
        return false;
    }

    $data  = json_decode((string)$response, true);
    $error = (int)($data['error'] ?? 1);
    if ($error !== 0) {
        gl_log('SuperFaktura sendInvoice error id=' . $invoiceId . ': ' . ($data['error_message'] ?? (string)$response));
        return false;
    }

    gl_log('Faktúra ID: ' . $invoiceId . ' odoslaná na email: ' . $toEmail);
    return true;
}

/**
 * Stiahne PDF faktúry zo SuperFaktura.
 * @return string|null  Binárny obsah PDF alebo null pri chybe
 */
function sfGetInvoicePdf(string $invoiceId): ?string {
    if (!_sfConfigured()) {
        gl_log('SuperFaktura: SF_EMAIL alebo SF_API_KEY nie je nastavený.');
        return null;
    }

    // 1. Načítaj detail faktúry — potrebujeme token pre PDF URL
    $ch = curl_init(_sfBaseUrl() . '/invoices/getInvoiceDetails/' . $invoiceId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . _sfAuthHeader()],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $detailResp = curl_exec($ch);
    $httpCode   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($detailResp === false || $httpCode !== 200) {
        gl_log('SuperFaktura getInvoiceDetails HTTP ' . $httpCode . ' id=' . $invoiceId);
        return null;
    }

    $detail = json_decode((string)$detailResp, true);
    $token  = (string)($detail['Invoice']['token'] ?? '');

    // 2. Stiahni PDF
    $pdfUrl = _sfBaseUrl() . '/invoices/pdf/' . $invoiceId
            . ($token !== '' ? '/token:' . $token : '');
    $ch = curl_init($pdfUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . _sfAuthHeader()],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $pdf      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($pdf === false || $httpCode !== 200) {
        gl_log('SuperFaktura getPdf HTTP ' . $httpCode . ' id=' . $invoiceId);
        return null;
    }

    // Overenie, že odpoveď je skutočné PDF
    if (!str_starts_with((string)$pdf, '%PDF')) {
        gl_log('SuperFaktura getPdf: odpoveď nie je PDF. id=' . $invoiceId);
        return null;
    }

    return (string)$pdf;
}
