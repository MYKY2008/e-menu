# Gemini CLI — Project Context & Handover

Tento dokument slúži ako pamäť pre Gemini CLI (orchestrátora), aby pri každom novom spustení presne vedel, v akom stave je projekt a aká je jeho úloha.

## 🤖 Tvoja Úloha
- **Rola:** Senior Orchestrátor a Kontrolór.
- **Zákaz:** Ty nikdy nepíšeš kód priamo (okrem dokumentácie a promptov).
- **Pracovný tok:** 
    1. Analyzuješ požiadavku a kód.
    2. Pripravíš detailný technický prompt do `docs/prompt.md`.
    3. Počkáš, kým Claude AI (alebo iný agent) vykoná zmeny.
    4. Vykonáš hĺbkovú kontrolu bezpečnosti, architektúry a dizajnu.

## 📁 Projektový Prehľad
- **Názov:** GastroLink QR (EMENU).
- **Stack:** PHP 8.x (strict types), SQLite (WAL), Tailwind CSS (CDN), Vanilla JS.
- **Biznis model:** B2B SaaS (Plány: Free, Pro, Ultra, Custom).
- **Integrácie:** Stripe (Checkout + Customer Portal), SuperFaktura API (automatické faktúry a maily), Resend SMTP.

## ✅ Čo sme už urobili
- **B2B Transformácia:** Pridanie fakturačných údajov, rozdelenie adresy na bunky, ceny bez DPH.
- **Platobný systém:** Kompletný Stripe flow vrátane Webhookov a správy predplatného cez Portál.
- **Fakturácia:** Automatické generovanie a odosielanie PDF faktúr cez SuperFaktura API.
- **Admin Dashboard:** Plná správa užívateľov, tržieb (MRR), štatistík a systémových logov.
- **Security Hardening (Fáza 1):** CSP hlavičky, ochrana proti Session Fixation, Rate Limiting, odstránenie inline JS (XSS prevencia).
- **Diagnostika:** Nástroje na kontrolu PHP rozšírení (OpenSSL, cURL) pre localhost/Windows.

## 🚩 Aktuálny stav (Next Steps)
Sleduj súbor `docs/problems.md` pre zoznam nevyriešených úloh. Hlavné priority pri ďalšom spustení:
1. **SSL Verifikácia:** Vrátiť `CURLOPT_SSL_VERIFYPEER` na `true` v Stripe API volaniach (ošetriť localhost).
2. **Ownership Hardening:** Posilniť kontrolu vlastníctva v hromadných operáciách (manage_menu.php).
3. **Cleanup:** Implementácia Image Garbage Collectora a zjednotenie logiky mazania súborov.
4. **Sitemap & Robots:** Príprava pre produkčné SEO.

## 🛠️ Dôležité príkazy
- **Localhost:** `http://localhost:8080` (vynútené v `.env`).
- **Stripe CLI:** `stripe listen --forward-to localhost:8080/api/payments/webhook.php`.
- **Diagnostika:** `/diagnose` (prístupné pre Admina).

---
*Keď sa znova stretneš s užívateľom, začni prečítaním tohto súboru a `docs/problems.md`.*
