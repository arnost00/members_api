### Informácie o transakcii

GET `/api/3/{club_name}/finances/{fin_id}`

Token: potrebný

Povolenia:

- transakcia je určená:
  - prihlásenému: žiadne
  - mojej ovečke: žiadne
- inak: finančník

Odpoveď:

- `fin_id` (UINT): id transakcie zhodné s URL
- `editor_user_id` (UINT): id autora transakcie
  - `editor_sort_name` (STRING): identifikacne meno autora
- `user_id` (UINT): id obete
  - `user_sort_name` (STRING): identifikacne meno obete
  - `chief_pay` (UINT): id platiaceho za uzivatela
- `race_id` (UINT): id priradeneho preteku
  - `race_name` (STRING)
  - `race_cancelled` (BOOLLIKE)
  - `race_date` (ISO)
- `note` (STRING): poznamka k transakcii
- `amount` (INT): hodnota transakcie (kladne = vklad, zaporne = vyber)
- `date` (ISO): datum transakcie
- `storno` (BOOLLIKE): (1 = stornovane, null = aktivne)
- `storno_user_id` (UINT): id kto stornoval
  - `storno_sort_name` (STRING): identifikacne meno stornovaca
- `storno_date` (ISO)
- `storno_note` (STRING)
- `claim`: (1 = reklamacia otvorena, 0 = reklamacia uzatvorena, null = bez reklamacie)

### Aktualizacia transakcie

POST `/api/3/{club_name}/finances/{fin_id}`

Token: potrebny

Povolenia:

- finančník

Požiadavka:

- `editor_user_id` (UINT): id autora transakcie
- `user_id` (UINT): id obete
- `race_id` (UINT): id priradeneho preteku
- `note` (STRING): poznamka k transakcii
- `amount` (INT): hodnota transakcie (kladne = vklad, zaporne = vyber)
- `date` (ISO): datum transakcie
- `storno` (?BOOL): (false | null = aktivne, true = stornovane)
- `storno_date` (ISO)
- `storno_note` (STRING)
- `claim`: (1 = reklamacia otvorena, 0 = reklamacia uzatvorena, null = bez reklamacie)

**Zmenene budu len tie stlpce, ktore budu uvedene v poziadavke.**

### Odstranenie transakcie

DELETE `/api/3/{club_name}/finances/{fin_id}`

Token: potrebny

Povolenia:

- finančník
- velky manazer

**Preferuje sa stornovanie transakcie.**

### Import transakcii

POST `/api/3/{club_name}/finances/import`

Token: potrebny

Povolenia:

- finančník
- velky manazer

Požiadavka (zoznam objektov):

- `user_id` (UINT): id obete
- `race_id` (UINT): id preteku
- `amount` (INT): hodnota transakcie (kladne = vklad, zaporne = vyber)
- `date` (ISO): datum transakcie
- `note` (STRING): poznamka k transakcii
- `editor_user_id` (UINT): id autora, ak nie je uvedene, pouzije sa id prihlaseneho

Odpoveď (zoznam objektov):

- `ok` (BOOL): uspesnost
- `id` (UINT): priradene id transakcie
- `error` (STRING): popis chyby ak `ok` je `false`
