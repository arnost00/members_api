# API Documentation

This API is currently the primary API used in the app. It includes basic race and user management functionalities.

**Warning:** Some status codes and error messages might be missing from this documentation. Please refer to the API's error messages for the most accurate and up-to-date information about the error.

# Table of contents

- [Examples](#examples)
  - [Retrieving a list of clubs](#retrieving-a-list-of-clubs)
  - [Retrieving user data](#retrieving-user-data)
  - [An error](#an-error)
- [Responses](#responses)
- [Permissions](#permissions)
  - [Authorization](#authorization)
  - [Policies](#policies)
- [Endpoints](#endpoints)
  - [/clubs](#clubs)
  - [/race](#race-index)
  - [/race/races](#race-races)
  - [/race/{race_id}](#race-detail)
  - [/race/{race_id}/redirect](#race-redirect)
  - [/race/{race_id}/relations](#race-relations)
  - [/race/{race_id}/signin/{user_id}](#race-sign-in)
  - [/race/{race_id}/signout/{user_id}](#race-sign-out)
  - [/race/{race_id}/notify](#race-notify)
  - [/user/login](#user-login)
  - [/user/{user_id}](#user-show)
  - [/user/{user_id}/managing](#user-managing)
  - [/user/](#user-data)
  - [/user/update](#user-update)
- [Interfaces](#interfaces)
  - [ClubResult](#clubresult)
  - [RaceResult](#raceresult)
  - [UserEntry](#userentry)
  - [LoginResponse](#loginresponse)
  - [UserDetail](#userdetail)
  - [UserFullDetail](#userfulldetail)
  - [updateResponse](#updateresponse)
- [Enums](#enums)
  - [TransportEnum](#transportenum)
  - [AccommodationEnum](#accommodationenum)
  - [PolicyEnum](#policyenum)

# Examples

_Response headers and content is shortened and formatted for better readability._

## Retrieving a list of clubs

```http
GET https://members.eob.cz/api/1/clubs HTTP/2
Host: members.eob.cz
User-Agent: curl/7.81.0
Accept: application/json
```

```http
HTTP/2 200
server: ATS
access-control-allow-origin: *
access-control-allow-headers: *, Authorization
access-control-allow-methods: POST, GET, OPTIONS
content-type: application/json; charset=utf-8

[
  {
    "clubname": "abm",
    "api_version": 0,
    "is_release": true,
    "fullname": "KOB ALFA  Brno",
    "shortcut": "ABM",
    "baseadr": "https:\/\/members.eob.cz\/abm\/",
    "mainwww": "https:\/\/abmbrno.cz\/",
    "emailadr": "web@eob.cz"
  },
  ...
]
```

## Retrieving user data

```http
GET https://members.eob.cz/api/1/spt/user HTTP/2
Host: members.eob.cz
User-Agent: curl/7.81.0
Accept: application/json
Authorization: Bearer <your secret token>
```

```http
HTTP/2 200
server: ATS
access-control-allow-origin: *
access-control-allow-headers: *, Authorization
access-control-allow-methods: POST, GET, OPTIONS
content-type: application/json; charset=utf-8

{
  "user_id": 213,
  "name": "Nov\u00e1k",
  "surname": "Jakub",
  "sort_name": "Jakub Nov\u00e1k",
  "email": "jakub@novak.sk",
  "gender": "H",
  "birth_date": "2001-01-01",
  "birth_number": "0123456789",
  "nationality": "SK",
  "address": "Nov\u00e1kov\u00e1 1",
  "city": "Nov\u00e1kovce",
  "postal_code": "12345",
  "phone": "+421123456789",
  "phone_home": "+421123456789",
  "phone_work": "+421123456789",
  "registration_number": 1234,
  "chip_number": 123456789,
  "chief_id": 0,
  "chief_pay": null,
  "licence_ob": "R",
  "licence_lob": "R",
  "licence_mtbo": "D",
  "is_hidden": false,
  "is_entry_locked": false
}
```

## An error

```http
GET https://members.eob.cz/api/1/does-not-exists HTTP/2
Host: members.eob.cz
Accept: application/json
```

```http
HTTP/2 418
server: ATS
access-control-allow-origin: *
access-control-allow-headers: *, Authorization
access-control-allow-methods: POST, GET, OPTIONS

{
  "code": 418,
  "message": "The club you're looking for is as real as a teapot handling coffee. Try a different name!"
}
```

# Responses

The HTTP status code indicates whether the request was successful.

- **Success Responses:** Always returned in JSON format, containing raw data.
- **Error Responses:**
  - Returns an interface based on the `$g_is_release` flag:
    - `$g_is_release === true`: [`ErrorResponse`](#errorresponse)
    - `$g_is_release === false`: [`ErrorDebugResponse`](#errordebugresponse)
  - Response format is based on the `Accept` HTTP header:
    - `Accept: application/json`: JSON format
    - `Accept: */*`: HTML format for debugging

# Permissions

## Authorization

Certain endpoints require an `Authorization` HTTP header with a valid token that is obtained after successful login:

- `Authorization: Bearer <token>`

## Policies

Additionally, specific policies are required for access:

- `small_manager`: The user must be exactly a small manager.
- `big_manager`: The user must be exactly a big manager.
- `any_manager`: The user must be small or big manager.

# Endpoints

## Clubs

- **Path**: /clubs
- **Methods**: GET || POST

Fetch all clubs.

**Response:**

- `200 OK`: Returns a list of [`ClubResult`](#clubresult),

## Race Index

- **Path**: /race
- **Methods**: GET || POST

Remind the developer to include `race_id` in the url.

**Response:**

- `404 Not Found`: `race_id` is required.

## Race Races

- **Path**: /race/races
- **Methods**: GET || POST

Fetch all upcoming races.

**Response:**

- `200 OK`: Returns a list of [`RaceResult`](#raceresult).

## Race Detail

- **Path**: /race/{race_id}
- **Methods**: GET || POST

Fetch detailed information about a race.

**Path Parameters:**

- `race_id`: The ID of the race.

**Response:**

- `200 OK`: Returns [`RaceResult`](#raceresult) with an additional field:
  - `everyone`: (array of [`UserEntry`](#userentry)): information about signed in users.
- `404 Not Found`: If the race does not exist.

## Race Redirect

- **Path**: /race/{race_id}/redirect
- **Methods**: GET || POST

Redirect user to race information page at `https://members.eob.cz/{clubname}/race_info_show.php?id_zav={race_id}`. This is **used as a deeplink** in the app.

**Path Parameters:**

- `race_id`: The ID of the race.

**Response:**

- `302 Found`: Redirects to the race information page.

## Race Relations

- **Path**: /race/{race_id}/relations
- **Methods**: GET || POST

Fetch the relations for a race.

**Path Parameters:**

- `race_id`: The ID of the race.

**Permissions:**

- [`Authorization header`](#authorization)

**Response:**

- `200 OK`: Returns [`UserEntry`](#userentry) with an additional field
  - `is_signed_in` (boolean): Whether the user is signed in for the race
- `403 Forbidden`: If the user does not have access.

## Race Sign in

- **Path**: /race/{race_id}/signin/{user_id}
- **Methods**: POST

Sign in a user for a race.

**Path Parameters:**

- `race_id`: The ID of the race.
- `user_id`: The ID of the user.

**Request Body:**

- `category` (string): The category of the race.
- `note` (string): Note for the race.
- `note_internal` (string): Internal note for the race.
- `transport` (integer): Transport option (0, 1, 2).
- `accommodation` (integer): Accommodation option (0, 1, 2).

**Permissions:**

- [`Authorization header`](#authorization)
- [`Policies`](#policies):
  - `any_manager` if you are signing in another user.

**Response:**

- `200 OK`: Successfully signed in the user.
- `404 Not Found`: If required fields are missing or the deadline for entry has been exceeded.
- `401 Unauthorized`: If the user is not authorized.
- `403 Forbidden`: If the user's account is locked.

## Race Sign out

- **Path**: /race/{race_id}/signout/{user_id}
- **Methods**: POST

Sign out a user from a race.

**Path Parameters:**

- `race_id`: The ID of the race.
- `user_id`: The ID of the user.

**Permissions:**

- [`Authorization header`](#authorization)
- [`Policies`](#policies):
  - `any_manager` if you are signing in another user.

**Response:**

- `200 OK`: Successfully signed out the user.
- `401 Unauthorized`: If the user is not authorized.
- `403 Forbidden`: If the user's account is locked.
- `404 Not Found`: If the race is cancelled.

## Race Notify

- **Path**: /race/{race_id}/notify
- **Methods**: POST

Send a notification about a race.

**Path Parameters:**

- `race_id`: The ID of the race.

**Request Body:**

- `title` (string): The title of the notification.
- `body` (string): The body of the notification.
- `image` (string, optional): The URL of an image.

**Permissions:**

- [`Authorization header`](#authorization)
- [`Policies`](#policies):
  - `big_manager`

**Response:**

- `200 OK`: Notification sent successfully.
- `400 Bad Request`: If required fields are missing or the image URL is invalid.
- `403 Forbidden`: If the user does not have permission to send notifications.

# User Endpoints

## User Login

- **Path**: /user/login
- **Methods**: POST

User login.

**Request Body:**

- `username` (string): The username.
- `password` (string): The password.

**Response:**

- `200 OK`: Returns `LoginResponse`.
- `400 Bad Request`: If username or password is not set.
- `401 Unauthorized`: If the username does not exist, the password is incorrect, or the account is locked.

## User Show

- **Path**: /user/{user_id}
- **Methods**: GET || POST

Fetch information about a specific user.

**Path Parameters:**

- `user_id`: The ID of the user.

**Response:**

- `200 OK`: Returns `UserDetail`.

## User Managing

- **Path**: /user/{user_id}/managing
- **Methods**: POST

Fetch information about users managed by a specific user.

**Path Parameters:**

- `user_id`: The ID of the user.

**Permissions:**

- [`Authorization header`](#authorization)

**Response:**

- `200 OK`: Returns a list of [`UserEntry`](#userentry).

## User Data

- **Path**: /user/
- **Methods**: GET || POST

Fetch data of the authenticated user.

**Permissions:**

- [`Authorization header`](#authorization)

**Response:**

- `200 OK`: Returns `UserFullDetail`.

## User Update

- **Path**: /user/update
- **Methods**: POST

Update data of the authenticated user.

**Request Body:** (Fields that can be updated based on user's permissions)

- `email` (string, editable by user)
- `address` (string, editable by user)
- `city` (string, editable by user)
- `postal_code` (string, editable by user)
- `phone` (string, editable by user)
- `phone_home` (string, editable by user)
- `phone_work` (string, editable by user)
- `chip_number` (string, editable by user)
- `licence_ob` (string, editable by user)
- `licence_lob` (string, editable by user)
- `licence_mtbo` (string, editable by user)

**Permissions:**

- [`Authorization header`](#authorization)
- [`Policies`](#policies):
  - `any_manager` for certain fields.

**Response:**

- `200 OK`: Returns `UpdateResponse`.
- `400 Bad Request`: Missing or invalid fields in the request.
- `401 Unauthorized`: User is not authorized.
- `403 Forbidden`: User account is locked or lacks necessary permissions.
- `404 Not Found`: Resource not found.
- `500 Internal Server Error`: An unexpected error occurred.

# Interfaces

## ClubResult

- `clubname` (string): The name of the club.
- `is_release` (boolean): Indicates whether the current version is a release version.
- `fullname` (string): The full name of the club.
- `shortcut` (string): The club's shortcut or abbreviation.
- `baseadr` (string): The base address of the club.
- `mainwww` (string): The main website URL of the club.
- `emailadr` (string): The email address of the club.

## RaceResult

- `race_id` (integer): The ID of the race.
- `dates` (array of strings): Dates of the race in ISO format.
- `entries` (array of strings): Entry deadlines for the race in ISO format.
- `name` (string): The name of the race.
- `cancelled` (boolean): Whether the race is cancelled.
- `club` (string): The organizing club.
- `link` (string): URL to the race information.
- `place` (string): Location of the race.
- `type` (string): Type of the race.
- `sport` (string): Sport category.
- `rankings` (array of strings): Ranking categories.
- `rank21` (string): Rank 21 information.
- `note` (string): Additional notes about the race.
- `transport` ([`TransportEnum`](#transportenum)): Transport availability.
- `accommodation` ([`AccommodationEnum`](#accommodationenum)): Accommodation availability.
- `categories` (array of strings): Race categories.

## UserEntry

- `user_id` (integer): The ID of the user.
- `name` (string): First name of the user.
- `surname` (string): Last name of the user.
- `registration_number` (string): Registration number of the user.
- `chip_number` (string): Chip number of the user.
- `category` (string): Race category of the user.
- `note` (string): Note for the user.
- `note_internal` (string): Internal note for the user.
- `transport` ([`TransportEnum`](#transportenum)): Transport availability.
- `accommodation` ([`AccommodationEnum`](#accommodationenum)): Accommodation availability.

## UserDetail

- `user_id` (integer): The ID of the user.
- `name` (string): First name of the user.
- `surname` (string): Last name of the user.
- `registration_number` (string): Registration number of the user.
- `chip_number` (string): Chip number of the user.
- `chief_id` (integer, nullable): Chief ID of the user.
- `chief_pay` (integer, nullable): Chief pay of the user.

## UserFullDetail

**UserFullDetail extends UserDetail**

- `sort_name` (string): Name of the user used for sorting. Should equal to `<name> <surname>`.
- `email` (string): Email of the user.
- `gender` (string): Gender of the user.
- `birth_date` (string): Birth date of the user.
- `birth_number` (string): Birth number of the user.
- `nationality` (string): Nationality of the user.
- `address` (string): Address of the user.
- `city` (string): City of the user.
- `postal_code` (string): Postal code of the user.
- `phone` (string): Personal phone number of the user.
- `phone_home` (string): Home phone number of the user.
- `phone_work` (string): Work phone number of the user.
- `licence_ob` (string): Orienteering licence.
- `licence_lob` (string): Ski orienteering licence.
- `licence_mtbo` (string): Mountain bike orienteering licence.
- `is_hidden` (boolean): Whether the user is hidden.
- `is_entry_locked` (boolean): Whether the user's entry is locked.

## UpdateResponse

- `pushed` (array of strings): List of updated fields.

## LoginResponse

- `token` (string): The authentication token.
- `policies` (object): The user's policies.
  - `policy_adm` (boolean): Admin policy.
  - `policy_news` (boolean): News policy.
  - `policy_regs` (boolean): Registration policy.
  - `policy_fin` (boolean): Finance policy.
  - `policy_mng` ([`PolicyEnum`](#policyenum)): Management policy.

## ErrorResponse

- `code` (integer): HTTP status code.
- `message` (string): Error description.

## ErrorDebugResponse

**ErrorDebugResponse extends ErrorResponse**

- `method` (string): HTTP method of the request.
- `path` (string): Requested endpoint path.
- `input` (array of strings): Parameters provided in the request.
- `line` (integer): Line number of the error.
- `file` (string): File name of the error.
- `trace` (string): Stack trace for debugging.

# Enums

## TransportEnum

- `TRANSPORT_UNAVAILABLE` (integer): 0
- `TRANSPORT_AVAILABLE` (integer): 1
- `TRANSPORT_REQUIRED` (integer): 2

## AccommodationEnum

- `ACCOMMODATION_UNAVAILABLE` (integer): 0
- `ACCOMMODATION_AVAILABLE` (integer): 1
- `ACCOMMODATION_REQUIRED` (integer): 2

## PolicyEnum

- `BIG_MANAGER` (integer): 4
- `SMALL_MANAGER` (integer): 2
