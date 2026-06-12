# Fix "Too many redirects" a vylepšenie registrácie (Produkcia)

## Problémy na vyriešenie
1. **Too many redirects:** Spôsobené nekonečnou slučkou medzi `/dashboard` (ktorý vyžaduje verifikáciu a hádže na `/login`) a `/login` (ktorý vidí, že je užívateľ prihlásený a hádže ho na `/dashboard`). Slučka nastáva, ak je v session `user_id`, ale `is_verified` je prázdne.
2. **Flow po registrácii:** Užívateľ by po registrácii nemal byť hodený na login, ale mal by zostať na stránke s jasným oznamom, že si má skontrolovať e-mail.
3. **Hardening:** Zabezpečiť, aby neoverený užívateľ nemohol nič robiť v systéme.

## Úlohy pre Claude AI

### 1. Oprava redirect slučky v `index.php`
- Uprav bloky `case $path === '/login'` a `case $path === '/register'`.
- Presmerovanie na `/dashboard` sa má vykonať **LEN vtedy**, ak je užívateľ prihlásený **A ZÁROVEŇ** verifikovaný.
- Teda zmeň `if (isLoggedIn())` na `if (isLoggedIn() && !empty($_SESSION['is_verified']))`.

### 2. Vylepšenie `auth/register.php`
- Po úspešnom vytvorení účtu a odoslaní mailu:
    - **NEMENIŤ** redirect na `/login`.
    - Zmeň redirect na `header('Location: ' . url('register?success=1'));`.
    - Predtým uisti sa, že flash správa je nastavená správne.

### 3. Úprava `views/register_page.php`
- Na začiatku spracuj `$_GET['success']`.
- Ak je `success == 1`, **Nezobrazuj registračný formulár**.
- Namiesto formulára zobraz veľkú, peknú "Success" kartu s informáciou:
    - "Registrácia bola úspešná!"
    - "Na vašu adresu (zobraziť e-mail z flashu alebo old_input) sme odoslali aktivačný odkaz."
    - "Prosím, skontrolujte si e-mail (aj priečinok SPAM) a aktivujte svoj účet."
    - Tlačidlo "Späť na prihlásenie" vedúce na `/login`.

### 4. Hardening `config.php`
- Uisti sa, že `requireLogin()` je nepriestrelné a ak zistí v DB, že užívateľ nie je verifikovaný, natvrdo zavolá `session_destroy()` a hodí ho na `/login`. To už čiastočne máme, ale skontroluj logiku.

### 5. SMTP a APP_URL (Kontrola)
- V `config.php` v `baseUrl()` funkcii skontroluj, či sa správne používa `$_ENV['APP_URL']`.
- **Dôležité upozornenie pre užívateľa:** Pripomeň mu, že v produkčnom `.env` musí mať `APP_URL=https://emenu.myky.cz` (povinné HTTPS) a správne SMTP údaje, inak maily nebudú odchádzať.

## Technické detaily
- Zachovaj Tailwind štýl, indigo-600 akcent a zaoblené rohy.
- Kód musí byť čistý, bez zbytočných komentárov.
