# Zoznam problémov a vylepšení (GastroLink QR)

Tento dokument slúži ako roadmapa pre opravy a hardening projektu pred ostrým nasadením.

## 🔴 1. Bezpečnosť (Kritické)
- [x] **Import Hardening:** ✅ Hotové
- [x] **Rate Limiting:** ✅ Hotové
- [x] **Data-atribúty v Dashboarde:** ✅ Hotové
- [x] **Session Re-regeneration:** ✅ Hotové
- [x] **Verifikácia registrácie:** Opraviť stav, keď sa používateľ vie prihlásiť aj bez kliknutia na overovací link z registračného mailu. ✅
- [x] **Stripe SSL Verifikácia:** Vrátiť `CURLOPT_SSL_VERIFYPEER` na `true` v `create_session.php` a `create_portal_session.php`. ✅
- [ ] **Ownership Hardening (manage_menu.php):** Pridať kontrolu vlastníctva pre každé ID v hromadných operáciách (reorder).

## 🟡 2. Biznis Logika a Platby
- [x] **Globálna expirácia plánov:** ✅ Hotové
- [x] **Idempotencia Webhookov:** ✅ Hotové
- [x] **Upgrade/Downgrade Flow:** ✅ Hotové cez Stripe Portal
- [x] **Automatické odosielanie faktúr:** ✅ Hotové cez SuperFaktura API

## 🟢 3. Architektúra a UX
- [ ] **Centralizácia mazania (deleteVenueFiles):** Zjednotiť logiku v `config.php`, aby sa nestalo, že pri zmazaní užívateľa ostanú v `uploads/` siroty.
- [ ] **Image Garbage Collector:** Implementovať v Admine funkciu "Vymazať nepoužívané obrázky", ktorá porovná súbory v `uploads/` s tými v DB.
- [ ] **Backup Hardening:** Presunúť generovanie backupov z `/tmp` do `storage/backups/` s `.htaccess` ochranou.
- [ ] **Dark Mode Flash:** Presunúť skript pre detekciu Dark Mode do `header.php` úplne navrch.

## 🔵 4. Ostatné
- [ ] **Sitemap & Robots:** Vygenerovať korektný `robots.txt` a sitemapu.
- [ ] **Database Backup Rotation:** Automatická rotácia posledných 5 stavov v `storage/backups`.
