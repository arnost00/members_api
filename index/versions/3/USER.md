# `User` — API endpoint pre používateľov

Súbor: `endpoints/user.php`

Trieda definuje REST endpointy pre správu používateľských profilov, správu vzťahov "kto koho spravuje", zariadenia (push notifikácie), nastavenia notifikácií a pár administrátorských prehľadov.

---

## Registrácia routes (`init()`)

| Metóda | Cesta                      | Handler                               | Auth |
| ------ | -------------------------- | ------------------------------------- | ---- |
| GET    | `/user/{user_id}`          | `detail`                              | áno  |
| GET    | `/user/{user_id}/managing` | `managing`                            | áno  |
| GET    | `/user/{user_id}/races`    | `user_races`                          | áno  |
| GET    | `/user/{user_id}/devices`  | `user_devices`                        | áno  |
| POST   | `/user/{user_id}/notify`   | `user_send_notify`                    | áno  |
| GET    | `/user/{user_id}/profile`  | `user_profile`                        | áno  |
| POST   | `/user/{user_id}/profile`  | `user_profile_update`                 | áno  |
| GET    | `/user/{user_id}/policies` | `user_policies` _(@unimplemented)_    | áno  |
| GET    | `/user/device/{device}`    | `user_device`                         | áno  |
| DELETE | `/user/device/{device}`    | `user_device_delete`                  | áno  |
| GET    | `/user/`                   | `user_profile` _(@deprecated)_        | áno  |
| POST   | `/user/`                   | `user_profile_update` _(@deprecated)_ | áno  |
| GET    | `/user/managing`           | `my_managing`                         | áno  |
| GET    | `/user/profile`            | `my_profile`                          | áno  |
| POST   | `/user/profile`            | `my_profile_update`                   | áno  |
| GET    | `/user/notify`             | `notify`                              | áno  |
| POST   | `/user/notify`             | `notify_update`                       | áno  |
| GET    | `/user/devices`            | `my_devices`                          | áno  |
| GET    | `/user/policies`           | `my_policies`                         | áno  |
| GET    | `/user/list`               | `list`                                | áno  |
| GET    | `/user/send_notify`        | `send_notify_everyone`                | áno  |
| GET    | `/user/statistics`         | `statistics`                          | áno  |

---

## Metódy

### `detail($user_id)`

Vráti základné údaje o používateľovi: `user_id`, `name`, `surname`, `sort_name`, `reg`, `si_chip`, `chief_id`, `chief_pay`.

- **Prístup:** iba `AuthRequired` — žiadna ďalšia kontrola oprávnení. Ktokoľvek prihlásený vidí základné údaje kohokoľvek.
- `404`, ak používateľ neexistuje.

### `my_policies()`

Vráti oprávnenia (booleans) prihláseného používateľa: `policy_adm`, `policy_adm_small`, `policy_news`, `policy_regs`, `policy_fin`, `policy_mng_big`, `policy_mng_small`.

### `policies($user_id)`

Vždy vyhodí `ApiException` (404) — "Not implemented yet."

### `my_managing()`

Skratka — zavolá `managing(Session::$user_id)`.

### `managing($user_id)`

Vráti zoznam: samotného `$user_id` + všetkých používateľov, ktorých `chief_id` sa naň odkazuje (t.j. koho tento používateľ spravuje).

### `my_profile()`

Skratka — zavolá `user_profile(Session::$user_id)`.

### `user_profile($user_id)`

Vráti kompletný profil používateľa (osobné údaje).

- **Prístup:** `policy_mng_big` alebo `policy_sadm` (malý admin), alebo `SessionUtils::is_managing_this_user($user_id)` (seba alebo niekoho, koho spravuje — pravdepodobne cez `chief_id`). Inak `403`.
- Vracia okrem iného: kontakt (email, 3× telefón), adresu, dátum narodenia, rodné číslo (citlivý osobný údaj), národnosť, `chief_id`/`chief_pay`, tri licencie (`licence_ob`, `licence_lob`, `licence_mtbo` — pravdepodobne licencie na orientačný beh, lyžiarsky orientačný beh a MTB orienteering), a príznaky `is_hidden`, `is_entry_locked`.

### `my_profile_update()`

Skratka — zavolá `user_profile_update(Session::$user_id)`.

### `user_profile_update($user_id)`

Upraví profil používateľa. Rozlišuje dve úrovne práv:

- **`$access`** (bool) = `policy_mng_big || policy_sadm` — plný prístup. Polia označené `access: $access` (meno, priezvisko, pohlavie, dátum narodenia, rodné číslo, národnosť, `reg`) môže meniť **iba** niekto s `$access`.
- Ak nemá `$access`, ale je `SessionUtils::is_managing_this_user($user_id)` (seba/spravovaný), smie meniť len "neutrálne" polia: adresu, mesto, email, PSČ, telefóny, `si_chip`, licencie. Inak `403`.
- **`is_hidden`** má vlastné oprávnenie — vyžaduje `Session::$MASK_SADM` bez ohľadu na `$access`.
- **`sort_name`** sa prepočíta automaticky, ak sa mení meno alebo priezvisko (kombinuje nové/existujúce meno a priezvisko).
- Ak nie je poslané žiadne platné pole, vráti `{"pushed": []}` bez zápisu do DB.
- Úspešná zmena sa loguje cez `ModifyLog::edit` a vráti zoznam skutočne zapísaných kľúčov.

### `notify()`

Vráti nastavenia notifikácií prihláseného používateľa (`TBL_MAILINFO`). Ak záznam neexistuje, použijú sa defaultné hodnoty (`__default_notify_values()`).

- Používa lokálnu pomocnú funkciu `_parse_flags($value, $scheme)`, ktorá bitovú masku rozbalí na pole `{name, id, value}` pre každú položku číselníka (rovnaký princíp ako `rankings` v `RaceUtils::get_race_info`).
- Časti týkajúce sa financií (`send_finances`, `send_finances_data`, `financial_limit`) sú `null`, ak je `Config::$g_enable_finances` vypnuté.
- `send_member_minus` je viditeľné len ak sú financie zapnuté **a** má používateľ `policy_fin`.
- `send_internal_entry_expired` je viditeľné len s `policy_regs`.

### `notify_update()`

Upraví nastavenia notifikácií prihláseného používateľa.

- Polia viazané na financie majú `permission` = žiadne obmedzenie, ak sú financie zapnuté v `Config`, inak vyžadujú `MASK_SADM` (t.j. ak je funkcia financií globálne vypnutá, tieto polia môže meniť už len malý admin).
- `send_member_minus` vyžaduje `MASK_FIN` (financie zapnuté) alebo `MASK_SADM` (vypnuté).
- `send_internal_entry_expired` vyžaduje `MASK_REGS`.
- `days_before` sa orezáva na rozsah `Config::$g_mailinfo_minimal_daysbefore`–`maximal_daysbefore`.
- Ak je `email` explicitne poslaný ako `null`, nastaví sa späť na defaultný email a v bitovej maske `notify_type` sa vypne bit pre "email" (`&= ~1`).
- Ak sa `send_races`/`send_changes`/`send_finances` vypnú (`false`), súvisiace pod-nastavenia sa resetujú na defaultné hodnoty.
- Ak pre používateľa ešte neexistuje záznam v `TBL_MAILINFO`, najprv sa vloží defaultný riadok a až potom sa aplikuje `UPDATE`.

### `__default_notify_values()` (privátna)

Vráti defaultné hodnoty pre nový záznam v `TBL_MAILINFO` (email z profilu, 3 dni vopred, všetko vypnuté, `notify_type = 1`).

### `list()`

Vráti zoznam všetkých neskrytých (`hidden = 0`) používateľov, zoradený podľa priezviska.

- **Prístup:** iba `AuthRequired` — žiadna ďalšia kontrola. Kompletný adresár používateľov je dostupný komukoľvek prihlásenému.

### `user_races($user_id)`

Vráti zoznam pretekov, na ktoré je daný používateľ prihlásený (`race_id`, `name`, `category`), zoradené od najnovších.

- **Prístup:** iba `AuthRequired` — bez ďalšej kontroly.

### `my_devices()`

Skratka — zavolá `user_devices(Session::$user_id)`.

### `user_devices($user_id)`

Vráti zoznam zariadení (push notifikácie) používateľa.

- **Prístup:** seba, alebo `policy_mng_big`. Inak `403`.
- `fcm_status` je odvodený príznak (`fcm_token != ''`).

### `user_device($device)`

Vráti detail jedného zariadenia podľa jeho ID (naprieč používateľmi).

- **Prístup:** `policy_mng_big`, alebo vlastník zariadenia (over sa dotazom, komu `device` patrí). Inak `403`.

### `user_device_delete($device)`

Zmaže/odregistruje zariadenie. Rovnaká kontrola prístupu ako `user_device`.

### `user_send_notify($user_id)`

Odošle push notifikáciu konkrétnemu používateľovi (na všetky jeho zariadenia, alebo len na jedno konkrétne).

- **Prístup:** vyžaduje `policy_mng_big`.
- **Input:** `title`, `body`, `image` (voliteľná URL), `device` (voliteľné — obmedzí notifikáciu na jedno zariadenie).
- Ak `device` nie je zadané, vezmú sa všetky FCM tokeny daného `$user_id`. Ak je zadané, vezmú sa tokeny podľa `device` **bez** overenia, že dané zariadenie skutočne patrí `$user_id`.
- `404`, ak zariadenie/používateľ nemá aktívne žiadne tokeny.
- Notifikácia sa posiela **per-token samostatne** (jeden `Notifications::send()` volanie na token), výsledky sa vrátia ako pole.

### `send_notify_everyone()`

Odošle broadcast push notifikáciu všetkým (na `topic` = `Session::$clubname`).

- **Prístup:** vyžaduje `policy_mng_big`.

**TODO:** Metóda je **GET**, hoci vykonáva efekt (odoslanie notifikácie).

### `statistics()`

Vráti súhrn zariadení za každého neskrytého používateľa: počet zariadení (`device_count`) a počet s aktívnym FCM tokenom (`fcm_count`).

- **Prístup:** vyžaduje `policy_mng_big`.

---

## Použité DB tabuľky

- `Tables::$TBL_USER` — používatelia
- `Tables::$TBL_ZAVXUS` — prihlášky na preteky
- `Tables::$TBL_RACE` — preteky
- `Tables::$TBL_TOKENS` — zaregistrované zariadenia / push tokeny
- `Tables::$TBL_MAILINFO` — nastavenia e-mailových/push notifikácií za používateľa

---

## Formát requestov a odpovedí (JSON)

### `GET /user/{user_id}`

**Output:**

```json
{
  "user_id": 12,
  "name": "Ján",
  "surname": "Novák",
  "sort_name": "Novák Ján",
  "reg": "SVK12345",
  "si_chip": 123456,
  "chief_id": null,
  "chief_pay": null
}
```

---

### `GET /user/{user_id}/managing` a `GET /user/managing`

**Output:**

```json
[
  {
    "user_id": 12,
    "name": "Ján",
    "surname": "Novák",
    "reg": "SVK12345",
    "si_chip": 123456,
    "chief_id": null,
    "chief_pay": null
  },
  {
    "user_id": 15,
    "name": "Eva",
    "surname": "Nováková",
    "reg": null,
    "si_chip": 0,
    "chief_id": 12,
    "chief_pay": 12
  }
]
```

---

### `GET /user/{user_id}/profile`, `GET /user/profile`, (deprecated) `GET /user/`

**Output:**

```json
{
  "user_id": 12,
  "name": "Ján",
  "surname": "Novák",
  "sort_name": "Novák Ján",
  "email": "jan.novak@example.com",
  "gender": "M",
  "birth_date": "1990-05-01",
  "birth_number": "9005017890",
  "nationality": "SK",
  "address": "Hlavná 1",
  "city": "Bratislava",
  "postal_code": "81101",
  "phone": "+421900000000",
  "phone_home": null,
  "phone_work": null,
  "reg": "SVK12345",
  "si_chip": 123456,
  "chief_id": null,
  "chief_pay": null,
  "licence_ob": "A",
  "licence_lob": null,
  "licence_mtbo": null,
  "is_hidden": false,
  "is_entry_locked": false
}
```

---

### `POST /user/{user_id}/profile`, `POST /user/profile`, (deprecated) `POST /user/`

**Input:** všetky kľúče voliteľné.

```json
{
  "name": "Ján",
  "surname": "Novák",
  "gender": "M",
  "birth_date": "1990-05-01",
  "birth_number": "9005017890",
  "nationality": "SK",
  "reg": 12345,
  "is_hidden": false,

  "address": "Hlavná 1",
  "city": "Bratislava",
  "email": "jan.novak@example.com",
  "postal_code": 81101,
  "phone": "+421900000000",
  "phone_home": null,
  "phone_work": null,
  "si_chip": 123456,
  "licence_ob": "A",
  "licence_lob": null,
  "licence_mtbo": null
}
```

| Kľúč                                                                                                                                   | Kto môže meniť                      |
| -------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------- |
| `name`, `surname`, `gender`, `birth_date`, `birth_number`, `nationality`, `reg`                                                        | iba `policy_mng_big`/`policy_sadm`  |
| `is_hidden`                                                                                                                            | iba `MASK_SADM`                     |
| `address`, `city`, `email`, `postal_code`, `phone`, `phone_home`, `phone_work`, `si_chip`, `licence_ob`, `licence_lob`, `licence_mtbo` | seba/spravovaný, alebo vyššie práva |

**Output:**

```json
{ "pushed": ["jmeno", "prijmeni", "sort_name", "email"] }
```

---

### `GET /user/policies`, `GET /user/{user_id}/policies`

**Output:**

```json
{
  "policy_adm": false,
  "policy_adm_small": true,
  "policy_news": false,
  "policy_regs": false,
  "policy_fin": true,
  "policy_mng_big": false,
  "policy_mng_small": true
}
```

> Poznámka: `/user/{user_id}/policies` momentálne nie je impolementované, vracia 404. V budúcnosti však by malo vracať hodnoty v spomenutom formáte.

---

### `GET /user/notify`

**Output:**

```json
{
  "notify_type": [
    { "name": "Email", "id": 1, "value": true },
    { "name": "Push", "id": 2, "value": false }
  ],
  "email": "jan.novak@example.com",
  "send_news": true,
  "send_races": true,
  "days_before": 3,
  "days_before_min": 1,
  "days_before_max": 30,
  "race_types": [{ "name": "Beh", "id": 1, "value": true }],
  "rankings": [{ "name": "Slovenský pohár", "id": 1, "value": false }],
  "send_changes": false,
  "send_changes_data": [{ "name": "Zmena termínu", "id": 1, "value": false }],
  "send_finances": true,
  "send_finances_data": [{ "name": "Nová platba", "id": 1, "value": true }],
  "financial_limit": -50,
  "send_member_minus": false,
  "send_internal_entry_expired": null
}
```

\* (hodnoty polí ako `notify_type`/`race_types`/`rankings` závisia od číselníkov `Enums::$g\__`, ktoré nie sú súčasťou tohto súboru — príklad je ilustračný.)\*

---

### `POST /user/notify`

**Input:** všetky kľúče voliteľné.

```json
{
  "notify_type": 3,
  "email": "jan.novak@example.com",
  "send_news": true,
  "send_races": true,
  "days_before": 5,
  "race_types": 6,
  "rankings": 1,
  "send_changes": false,
  "send_changes_data": 0,
  "send_finances": true,
  "send_finances_data": 2,
  "financial_limit": -50,
  "send_member_minus": false,
  "send_internal_entry_expired": false
}
```

> Poznámka: `notify_type`, `race_types`, `rankings`, `send_changes_data`, `send_finances_data` sa v requeste posielajú ako **celé číslo (bitová maska)**, hoci v `GET /user/notify` sa vracajú rozbalené na pole objektov. Pri update teda treba masku poskladať naspäť (súčet/`OR` príslušných `id` hodnôt).

**Output:**

```json
{ "pushed": ["notify_type", "email", "active_news"] }
```

---

### `GET /user/{user_id}/races`

**Output:**

```json
[
  { "race_id": 7, "name": "Jarný beh 2026", "category": "H21" },
  { "race_id": 3, "name": "Zimný beh 2025", "category": "H21" }
]
```

---

### `GET /user/devices`, `GET /user/{user_id}/devices`

**Output:**

```json
[
  {
    "device": "abc123",
    "device_name": "iPhone Jána",
    "fcm_token_timestamp": "2026-06-01 10:00:00",
    "fcm_status": true,
    "app_last_opened": "2026-07-10 08:30:00"
  }
]
```

---

### `GET /user/device/{device}`

**Output:** jeden objekt v rovnakom tvare ako položka vyššie.

---

### `DELETE /user/device/{device}`

**Input:** bez tela.
**Output:** bez tela.

---

### `POST /user/{user_id}/notify` — `user_send_notify`

**Input:**

```json
{
  "title": "Pripomienka",
  "body": "Zajtra je uzávierka prihlášok.",
  "image": "https://example.com/banner.jpg",
  "device": "abc123"
}
```

| Kľúč     | Povinný | Poznámka                                                   |
| -------- | ------- | ---------------------------------------------------------- |
| `title`  | áno     |                                                            |
| `body`   | áno     |                                                            |
| `image`  | nie     | URL                                                        |
| `device` | nie     | ak chýba, notifikácia ide na všetky zariadenia používateľa |

**Output:** pole výsledkov `Notifications::send()`, jeden na každé zariadenie — presný tvar položky nepoznám (`Notifications::send()` nebol dodaný).

```json
[
  { "...": "výsledok Notifications::send() pre 1. token" },
  { "...": "výsledok Notifications::send() pre 2. token" }
]
```

---

### `GET /user/send_notify` — `send_notify_everyone`

**Input:**

```json
{
  "title": "Dôležité oznámenie",
  "body": "Zmena termínu klubových pretekov.",
  "image": null
}
```

**Output:** jeden výsledok `Notifications::send()` — presný tvar nepoznám.

---

### `GET /user/list`

**Output:**

```json
[
  {
    "user_id": 12,
    "surname": "Novák",
    "name": "Ján",
    "sort_name": "Novák Ján",
    "si_chip": 123456,
    "reg": "SVK12345",
    "chief_id": null,
    "chief_pay": null
  }
]
```

---

### `GET /user/statistics`

**Output:**

```json
[
  {
    "user_id": 12,
    "name": "Ján",
    "surname": "Novák",
    "sort_name": "Novák Ján",
    "device_count": 2,
    "fcm_count": 1
  }
]
```
