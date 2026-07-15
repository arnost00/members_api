# `System` — API endpoint pre prihlásenie, zariadenia a cron

Súbor: `endpoints/system.php`
Namespace: `ApiTwo`
Implementuje: `Endpoint`

Trieda rieši prihlásenie (vydanie access tokenu + registráciu zariadenia), správu push-notifikačných tokenov, aktualizáciu/mazanie zariadení a spúšťanie cronu.

---

## Registrácia routes (`init()`)

| Metóda | Cesta                      | Handler            | Auth |
| ------ | -------------------------- | ------------------ | ---- |
| ANY    | `/system/`                 | `index`            | nie  |
| POST   | `/system/login`            | `login`            | nie  |
| GET    | `/system/cron`             | `cron`             | nie  |
| POST   | `/system/device/fcm_token` | `fcm_token_update` | áno  |
| DELETE | `/system/device/fcm_token` | `fcm_token_delete` | áno  |
| POST   | `/system/device/`          | `device_update`    | áno  |
| DELETE | `/system/device/`          | `device_delete`    | áno  |

---

## Metódy

### `index()`

Vypíše statické `<h1>System</h1>` — nie JSON, pravdepodobne len landing/health-check stránka API.

### `login()`

Overí prihlasovacie údaje a vydá access token pre nové zariadenie.

- **Input:** `username`, `password` (povinné); `app_version`, `device_name` (voliteľné, default `""`).
- **Postup:**
  1. Nájde účet v `TBL_ACCOUNT` podľa `login`. Ak neexistuje → `403` "Username does not exists."
  2. Ak je `locked` → `403` "Your account is locked."
  3. Overí heslo: `password_verify(md5($password), $output["heslo"])`.
  4. Aktualizuje `last_visit` na aktuálny čas.
  5. `Session::pull_policy_by_user_id()` — načíta oprávnenia používateľa do session (implementácia mimo tohto súboru).
  6. Vygeneruje nové **device UUID v4** manuálne z náhodných bajtov.
  7. Vloží nový záznam do `TBL_TOKENS` (nové zariadenie).
  8. Vygeneruje access token platný **90 dní**.
- **Output:** `access_token`, `expiration` (unix timestamp), `device` (UUID), `user_id`.

### `fcm_token_update()`

Nastaví/aktualizuje FCM (push) token pre aktuálne zariadenie prihláseného používateľa.

- **Input:** `token` (povinný).
- **Upsert** (`INSERT ... ON DUPLICATE KEY UPDATE`) do `TBL_TOKENS` podľa `device`+`user_id`: nastaví `fcm_token` a `fcm_token_timestamp` (aktuálny čas).

### `fcm_token_delete()`

Zruší FCM token pre aktuálne zariadenie (nastaví `fcm_token = NULL`). Samotný záznam zariadenia ostáva zachovaný — ide teda o odhlásenie z push notifikácií, nie o zmazanie zariadenia.

### `device_update()`

Aktualizuje meno zariadenia, verziu aplikácie a čas posledného otvorenia (`app_last_opened`).

- Vyžaduje, aby `Session::$device` nebolo `null` (`400`, ak chýba).
- **Input:** `device_name`, `app_version` (oba povinné).
- **Upsert** do `TBL_TOKENS` podľa `device`+`user_id`.

### `device_delete()`

Kompletne zmaže záznam zariadenia z `TBL_TOKENS` (na rozdiel od `fcm_token_delete`, ktorá len vynuluje token).

- Vyžaduje, aby `Session::$device` nebolo `null` (`400`, ak chýba).

### `cron()`

Includuje `boilerplate/cron/cron.php` a spustí `Cron::start()`.

---

## Formát requestov a odpovedí (JSON)

### `POST /system/login`

**Input:**

```json
{
  "username": "jan.novak",
  "password": "tajneheslo",
  "app_version": "1.2.3",
  "device_name": "iPhone používateľa Ján"
}
```

| Kľúč          | Povinný | Poznámka     |
| ------------- | ------- | ------------ |
| `username`    | áno     |              |
| `password`    | áno     |              |
| `app_version` | nie     | default `""` |
| `device_name` | nie     | default `""` |

**Output:**

```json
{
  "access_token": "eyJhbGciOi...",
  "expiration": 1768521600,
  "device": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "user_id": 12
}
```

---

### `POST /system/device/fcm_token`

**Input:**

```json
{ "token": "fcm-push-token-string" }
```

**Output:** bez tela.

---

### `DELETE /system/device/fcm_token`

**Input:** bez tela.
**Output:** bez tela.

---

### `POST /system/device/`

**Input:**

```json
{
  "device_name": "iPhone používateľa Ján",
  "app_version": "1.2.3"
}
```

**Output:** bez tela.

---

### `DELETE /system/device/`

**Input:** bez tela.
**Output:** bez tela.

---

### `GET /system/cron`

**Input:** bez tela.
**Output:** závisí od `Cron::start()` — implementácia nie je súčasťou tohto súboru.
