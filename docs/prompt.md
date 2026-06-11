# ÚLOHA: Komplexný životný cyklus predplatného (Upgrade, Downgrade, Expirácia)

Cieľom je zabezpečiť 100% správne fungovanie platieb, správu plánov v Stripe a férový prechod medzi plánmi.

## 1. Upgrade & Downgrade Logika (api/payments/create_session.php)
- **Upgrade:** Ak užívateľ už má platený plán (napr. pro) a vyberie si vyšší (napr. ultra):
    - Nepoužívaj `subscription` mód pre novú session, ale využi Stripe API na **zmenu existujúceho subscription** (ak je to možné) alebo vytvor session, ktorá pri úspechu cez Webhook upraví existujúci odber.
    - *Zjednodušené riešenie:* Ak už má `stripe_subscription_id`, presmeruj ho do **Stripe Customer Portal** (musíš ho nakonfigurovať v Stripe Dashboarde a vytvoriť endpoint `create_portal_session.php`), kde si môže sám zmeniť plán. Toto je najbezpečnejšia cesta pre B2B.
- **Downgrade:** Ak si užívateľ vyberie nižší plán:
    - Nenúť ho ísť do profilu. Nastav mu `next_plan_name` na tento nový plán.
    - V Stripe nastav `cancel_at_period_end = true` pre aktuálny odber.
    - Do DB ulož info, že po skončení aktuálneho mesiaca sa prepne na tento nižší plán.

## 2. Zrušenie obnovy (api/user_actions.php)
- Pri akcii `cancel_plan`:
    - Musíš zavolať Stripe API a nastaviť `cancel_at_period_end = true` pre dané `stripe_subscription_id`. Týmto Stripe prestane sťahovať peniaze po skončení obdobia.
    - V UI profilu (`profile_page.php`) po úspešnom zrušení **skry tlačidlo "Zrušiť plán"** a namiesto neho zobraz text: "Predplatné bude ukončené k [dátum]. Opätovná obnova je vypnutá."

## 3. Globálna kontrola expirácie (views/client_view.php & index.php)
- Plány sa nesmú kontrolovať len pri logine.
- V `index.php` (pred switchom) alebo v `config.php` pridaj globálnu kontrolu: Ak je prihlásený užívateľ a jeho `plan_ends_at` je v minulosti, zavolaj `applyPlanTransitionIfNeeded`.
- V `views/client_view.php`: Ak majiteľovi menu vypršal plán a jeho menu prekračuje limity Free plánu (napr. má 5 kategórií), zobraziť návštevníkom správu: "Toto menu je dočasne nedostupné (Lockdown)."

## 4. Idempotencia Webhookov (api/payments/webhook.php)
- Pri `checkout.session.completed` najprv skontroluj, či objednávka s daným `stripe_session_id` už nie je v stave `paid`. Ak áno, event ignoruj (ochrana proti duplicite).
- Spracuj event `customer.subscription.updated`: Ak sa v Stripe zmení plán, aktualizuj `plan_name` a limity v našej DB.

## 5. UI Vylepšenia (views/plans_page.php)
- Ak má užívateľ `next_plan_name` nastavený, zobraz mu pri danom pláne informáciu: "Tento plán sa aktivuje od [dátum]."
- Ak užívateľ klikne na plán, ktorý už má, nič nerob (alebo zobraz toast "Tento plán už využívate").

## 6. Validácia IČ DPH (api/update_profile.php)
- Pri ukladaní fakturačných údajov pridaj základnú kontrolu pre `ic_dph`: Musí začínať dvoma písmenami (napr. SK, CZ) a nasledovať číslicami (použi regex).
