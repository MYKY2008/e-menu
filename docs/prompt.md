# ÚLOHA: Implementácia fakturácie (Fáza 2 - SuperFaktura & PDF)

Cieľom je automatizovať vystavovanie faktúr po úspešnej platbe a umožniť užívateľom ich sťahovanie.

## 1. SuperFaktura API Integrácia
- Vytvor pomocnú triedu alebo sadu funkcií (napr. v `libs/superfaktura.php`) na komunikáciu so SuperFaktura API.
- Vyžaduje premenné v `.env`: `SF_EMAIL`, `SF_API_KEY`, `SF_COMPANY_ID`.
- Implementuj funkciu na vytvorenie faktúry (regular invoice) na základe údajov o užívateľovi (IČO, DIČ, adresa) a objednávke.

## 2. Webhook Update (api/payments/webhook.php)
- Uprav spracovanie eventu `checkout.session.completed` a `invoice.paid`:
    - Po úspešnom zápise platby do DB zavolaj SuperFaktura API.
    - Odošli firemné údaje užívateľa (`company_name`, `ico`, `dic`, atď.) do SuperFaktury.
    - Získané `invoice_id` (zo SuperFaktury) ulož do tabuľky `orders` v stĺpci `invoice_id`.

## 3. Sťahovanie PDF (api/payments/download_invoice.php)
- Vytvor nový endpoint, ktorý:
    - Overí, či je užívateľ prihlásený a či mu daná objednávka patrí.
    - Načíta `invoice_id` z tabuľky `orders`.
    - Vyžiada PDF dokument zo SuperFaktura API.
    - Vráti PDF súbor priamo do prehliadača užívateľa so správnymi hlavičkami (`Content-Type: application/pdf`).

## 4. UI Profil (views/profile_page.php)
- V záložke **"Faktúry"** sprevádzkuj tlačidlo "Stiahnuť PDF".
- Tlačidlo by malo smerovať na `api/payments/download_invoice.php?order_id=XXX`.
- Ak `invoice_id` v DB chýba (faktúra sa ešte negenerovala), tlačidlo deaktivuj alebo zobraz informáciu "Spracováva sa".

## 5. Robustnosť
- Ošetri prípady, kedy užívateľ nemá vyplnené fakturačné údaje (v takom prípade vystav faktúru na meno/email ako súkromnú osobu).
- Pridaj logovanie chýb pri komunikácii s API do `storage/error.log`.
