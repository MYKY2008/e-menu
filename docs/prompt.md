# ÚLOHA: Automatické odosielanie faktúr cez SuperFaktura API

Cieľom je zabezpečiť, aby po úspešnom vytvorení faktúry bola táto faktúra automaticky odoslaná na e-mail zákazníka priamo zo systému SuperFaktura.

## 1. Rozšírenie knižnice (libs/superfaktura.php)
- Pridaj novú funkciu `sfSendInvoice(int $invoiceId, string $toEmail)`:
    - Táto funkcia zavolá endpoint `/invoices/send` (alebo ekvivalentný podľa API dokumentácie SuperFaktura).
    - Parametre v POST požiadavke by mali obsahovať `invoice_id` a `email`.
    - Zabezpeč správne odoslanie hlavičiek (email a API kľúč).

## 2. Aktualizácia Webhooku (api/payments/webhook.php)
- Uprav časť, kde sa spracováva úspešná platba (`checkout.session.completed` / `invoice.paid`):
    - Po úspešnom vytvorení faktúry pomocou `sfCreateInvoice()` a získaní jej `id`, zavolaj novú funkciu `sfSendInvoice()`.
    - Ako e-mail príjemcu použi e-mail užívateľa, ktorý platbu vykonal (získaj ho z objektu `$user`).

## 3. Ošetrenie chýb
- Ak odoslanie e-mailu zo SuperFaktury zlyhá, zapíš túto chybu do `gl_log()`, ale neprerušuj beh webhooku (aby platba ostala označená ako úspešná).
- Pridaj logovaciu správu pri úspešnom odoslaní faktúry (napr. "Faktúra ID: XXX odoslaná na email: YYY").

## 4. Double-check
- Skontroluj, či sa faktúra odosiela až po tom, čo bola úspešne vytvorená a máme k dispozícii jej ID.
