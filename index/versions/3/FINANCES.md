# `Finances` — API endpoint pre financie

Súbor: `endpoints/finances.php`

Trieda definuje REST endpointy pre správu finančných transakcií používateľov (platby, história, reklamácie/spory k platbám, import platieb).

---

## Registrácia routes (`init()`)

Všetky routy sú pod prefixom `/finances`.

| Metóda | Cesta                     | Handler           | Auth |
| ------ | ------------------------- | ----------------- | ---- |
| GET    | `/`                       | `my_overview`     | áno  |
| GET    | `/user`                   | `my_overview`     | áno  |
| GET    | `/user/{user_id}`         | `user_overview`   | áno  |
| GET    | `/history`                | `history`         | áno  |
| GET    | `/{fin_id}`               | `detail`          | áno  |
| GET    | `/{fin_id}/claim/history` | `claim_history`   | áno  |
| POST   | `/{fin_id}/claim/message` | `claim_message`   | áno  |
| POST   | `/{fin_id}/claim/close`   | `claim_close`     | áno  |
| POST   | `/{fin_id}`               | `payment_update`  | áno  |
| DELETE | `/{fin_id}`               | `payment_delete`  | áno  |
| POST   | `/import`                 | `payments_import` | áno  |

`{fin_id}` a `{user_id}` sú obmedzené regexom na číselné hodnoty (`[0-9]+`).

Autentifikácia je vynútená middlewarom `AuthRequired` pre všetky routy.

---

## Metódy

### `my_overview()`

Vráti finančný prehľad prihláseného používateľa. Interne len zavolá `user_overview(Session::$user_id)`.

### `user_overview($user_id)`

Vráti súhrn (`total`) súm za daného používateľa, zoskupený podľa používateľa.

- **Prístup:** povolené je pozrieť si vlastný prehľad, alebo prehľad iného používateľa len ak má volajúci právo `Session::$MASK_FIN` (financník). Inak `403`.
- **SQL logika:** sčíta všetky nestornované (`storno IS NULL`) platby, kde je používateľ buď priamym platiteľom (`id_users_user`), alebo "pastierom" (`chief_pay`) iného používateľa — v internom slangu vzťah **pastier a ovečky**, napr. rodič platiaci za dieťa. V odpovedi sa tak môžu objaviť aj riadky "oveciek", za ktoré `chief_pay` používateľ platí.

### `history()`

Vráti kompletnú históriu nestornovaných platieb prihláseného používateľa (vrátane platieb, ktoré platí ako `chief_pay` za niekoho iného), so zoradením od najnovších.

Vracia rozšírené dáta: meno editora, meno používateľa, názov a dátum pretekov (`race`), poznámku, sumu, dátum, info o storne a `claim` (príznak reklamácie).

### `detail($fin_id)`

Vráti detail jednej platby podľa `fin_id`.

- **Prístup:** povolené pre vlastníka platby, jeho "pastiera" (`chief_pay`), alebo používateľa s právom `MASK_FIN`. Inak `403`.
- Vracia aj info o tom, kto platbu stornoval (`storno_by` → meno).

### `claim_history($fin_id)`

Vráti históriu správ/reklamácie (`claim`) k danej platbe, zoradenú od najnovšej.

> Poznámka: na rozdiel od `detail()` tu nevidím kontrolu oprávnenia — ktokoľvek prihlásený vie získať históriu reklamácie k ľubovoľnému `fin_id`. Ak to nie je zámer, stojí za to doplniť rovnakú kontrolu ako v `detail()`.

### `claim_message($fin_id)`

Pridá alebo upraví správu v rámci reklamácie k platbe.

- Vstup: `message` (povinný).
- Logika: ak existuje najnovšia reklamačná správa od **toho istého** používateľa, **upraví** ju (UPDATE). Inak vytvorí **novú** reklamáciu (INSERT).
- Nastaví príznak `claim = 1` na danej platbe (signalizuje, že platba má aktívnu reklamáciu).

### `claim_close($fin_id)`

Uzavrie reklamáciu — nastaví `claim = 0` na platbe. Nemaže históriu správ.

### `payment_update($fin_id)`

Upraví existujúcu platbu.

- **Prístup:** vyžaduje `MASK_FIN`. Inak `403`.
- Vstupné polia (všetky voliteľné, filtrované/validované): editor, používateľ, preteky, suma, dátum, poznámka, storno (bool/null), kto stornoval, dátum storna, poznámka k stornu, claim (bool/null).
- **Storno logika:** stĺpec `storno` používa iba dve hodnoty — `1` alebo `NULL` (nie `0`). Ak vstup obsahuje `storno = false`, prepíše sa na `null`.
- Zmena sa zaloguje cez `ModifyLog::edit`.

### `payment_delete($fin_id)`

Natrvalo vymaže platbu z databázy.

- **Prístup:** vyžaduje `MASK_MNG_BIG` **aj** `MASK_FIN` súčasne. Inak `403`.
- Zmena sa zaloguje cez `ModifyLog::delete`.

### `payments_import()`

Hromadný import viacerých platieb naraz.

- **Prístup:** vyžaduje `MASK_MNG_BIG` aj `MASK_FIN`.
- Vstup: pole objektov, každý s `user_id`, `race_id`, `amount`, `date`, `note`, voliteľne `editor_user_id` (default: prihlásený používateľ).
- Každá položka sa spracuje **samostatne v try/catch** — pri chybe jednej platby sa import nezastaví, ale zaznamená sa chyba pre danú položku (čiastočný úspech je možný).
- Návratová hodnota: pole výsledkov v tvare `{ ok: bool, id: int|null, error?: string }` pre každú vstupnú platbu.
- Úspešné vloženia sa logujú cez `ModifyLog::add`.

---

## Formát requestov a odpovedí (JSON)

Všetky vstupy aj výstupy sú JSON. Nižšie sú kľúče, ktoré jednotlivé metódy očakávajú (input) a vracajú (output).

---

### `GET /finances` a `GET /finances/user` — `my_overview`

**Input:** žiadny (používa sa `Session::$user_id`).

**Output:** pole objektov (súhrn za prihláseného používateľa a prípadne jeho "ovečky").

```json
[
  {
    "user_id": 12,
    "sort_name": "Novák Ján",
    "total": -450
  }
]
```

---

### `GET /finances/user/{user_id}` — `user_overview`

**Input:** `user_id` v ceste (path param, číslo).

**Output:** rovnaký formát ako `my_overview`.

---

### `GET /finances/history` — `history`

**Input:** žiadny (používa sa `Session::$user_id`).

**Output:** pole platieb, zoradené od najnovšej.

```json
[
  {
    "fin_id": 101,
    "editor_user_id": 5,
    "editor_sort_name": "Kováč Peter",
    "user_id": 12,
    "user_sort_name": "Novák Ján",
    "race_name": "Jarný beh 2026",
    "race_cancelled": 0,
    "race_date": "2026-04-12 00:00:00",
    "note": "Štartovné",
    "amount": -20,
    "date": "2026-04-01",
    "storno": null,
    "storno_user_id": null,
    "storno_date": null,
    "storno_note": null,
    "claim": 0
  }
]
```

---

### `GET /finances/{fin_id}` — `detail`

**Input:** `fin_id` v ceste (path param, číslo).

**Output:** jeden objekt (rovnaké kľúče ako v `history`, navyše `chief_pay`, `race_id` a `storno_sort_name`).

```json
{
  "fin_id": 101,
  "editor_user_id": 5,
  "editor_sort_name": "Kováč Peter",
  "user_id": 12,
  "user_sort_name": "Novák Ján",
  "chief_pay": null,
  "race_id": 7,
  "race_name": "Jarný beh 2026",
  "race_cancelled": 0,
  "race_date": "2026-04-12 00:00:00",
  "note": "Štartovné",
  "amount": -20,
  "date": "2026-04-01",
  "storno": null,
  "storno_user_id": null,
  "storno_sort_name": null,
  "storno_date": null,
  "storno_note": null,
  "claim": 0
}
```

---

### `GET /finances/{fin_id}/claim/history` — `claim_history`

**Input:** `fin_id` v ceste (path param, číslo).

**Output:** pole reklamačných správ, zoradené od najnovšej.

```json
[
  {
    "claim_id": 3,
    "user_id": 12,
    "payment_id": 101,
    "text": "Prosím o vysvetlenie tejto sumy.",
    "date": "2026-05-01 10:15:00",
    "sort_name": "Novák Ján"
  }
]
```

---

### `POST /finances/{fin_id}/claim/message` — `claim_message`

**Input:** `fin_id` v ceste + telo requestu.

```json
{
  "message": "Prosím o vysvetlenie tejto sumy."
}
```

| Kľúč      | Povinný | Poznámka    |
| --------- | ------- | ----------- |
| `message` | áno     | text správy |

**Output:** bez tela (žiadny explicitný response).

---

### `POST /finances/{fin_id}/claim/close` — `claim_close`

**Input:** `fin_id` v ceste, bez tela.

**Output:** bez tela.

---

### `POST /finances/{fin_id}` — `payment_update`

**Input:** `fin_id` v ceste + telo requestu, všetky kľúče voliteľné:

```json
{
  "editor_user_id": 5,
  "user_id": 12,
  "race_id": 7,
  "amount": -20,
  "date": "2026-04-01",
  "note": "Štartovné",
  "storno": true,
  "storno_by": 5,
  "storno_date": "2026-04-05",
  "storno_note": "Duplicitná platba",
  "claim": false
}
```

| Kľúč             | Typ       | Poznámka                                                   |
| ---------------- | --------- | ---------------------------------------------------------- |
| `editor_user_id` | int       | mapuje sa na `id_users_editor`                             |
| `user_id`        | int       | mapuje sa na `id_users_user`                               |
| `race_id`        | int       | mapuje sa na `id_zavod`                                    |
| `amount`         | int       | suma                                                       |
| `date`           | ISO dátum | dátum platby                                               |
| `note`           | string    | poznámka                                                   |
| `storno`         | bool/null | `true` → uloží sa `1`, `false`/vynechané → uloží sa `null` |
| `storno_by`      | int       | kto stornoval                                              |
| `storno_date`    | ISO dátum | dátum storna                                               |
| `storno_note`    | string    | poznámka k stornu                                          |
| `claim`          | bool/null | príznak reklamácie                                         |

**Output:** bez tela.

---

### `DELETE /finances/{fin_id}` — `payment_delete`

**Input:** `fin_id` v ceste, bez tela.

**Output:** bez tela.

---

### `POST /finances/import` — `payments_import`

**Input:** telo requestu je JSON pole objektov, každý reprezentuje jednu platbu:

```json
[
  {
    "user_id": 12,
    "race_id": 7,
    "amount": -20,
    "date": "2026-04-01",
    "note": "Štartovné",
    "editor_user_id": 5
  },
  {
    "user_id": 13,
    "race_id": 7,
    "amount": -20,
    "date": "2026-04-01",
    "note": "Štartovné"
  }
]
```

| Kľúč             | Povinný | Poznámka                            |
| ---------------- | ------- | ----------------------------------- |
| `user_id`        | áno     | za koho je platba                   |
| `race_id`        | áno     | súvisiace preteky, 0 ak nepriradené |
| `amount`         | áno     | suma                                |
| `date`           | áno     | ISO dátum                           |
| `note`           | áno     | poznámka                            |
| `editor_user_id` | nie     | default: prihlásený používateľ      |

**Output:** pole výsledkov, jeden záznam na každú vstupnú platbu (v rovnakom poradí):

```json
[
  { "ok": true, "id": 205 },
  { "ok": false, "id": null, "error": "Missing key: note" }
]
```
