# Mobile Handoff — Batch 2 (Parts 1–5)

Every payload below is copied from the actual `Validator::make` rules and `response()->json()` calls in the
controllers, not inferred. All routes are under `/api`; auth is the existing bearer token per route group.

---

## ⚠️ Breaking behaviour changes — Driver and Delivery apps

These affect the existing ride-offer poll/accept/reject flow every already-shipped Driver and Delivery app
build already uses. Read this section before anything else below.

### 1. Offers now expire

`GET /api/driver/get-rides` (the existing poll) now returns two new fields on each ride:

```json
{
  "rides": [
    {
      "id": 1234,
      "...": "...all the existing ride fields, unchanged...",
      "offer_expires_at": "2026-10-12T13:59:24+00:00",
      "offer_seconds_remaining": 27
    }
  ]
}
```

- `offer_expires_at` / `offer_seconds_remaining` are **new fields only** — every existing field on the ride
  object is unchanged. An app that doesn't read these two fields keeps working exactly as before, with no
  countdown shown.
- `offer_seconds_remaining` counts down from the admin-configured timeout (default 30s) from the moment
  this specific driver was offered the ride. **Update the app to show this as a countdown and prompt the
  rider to accept before it hits zero** — once it expires, the job moves to the next nearest driver (see
  below), and this rider will not get it back.
- For an admin-assigned ride (the pre-existing "Assign Trip" feature), both fields are `null` — there's no
  timeout on a manually assigned job.

### 2. Accepting an expired offer is now rejected

`POST /api/driver/accept-ride`

**New error response** when the offer backing this `ride_id` has expired:
```json
{ "message": "This offer has expired." }
```
**HTTP status: `410` (Gone)** — not 400/422/403 like other accept-ride failures. If the app's error handling
only checks for specific codes, add a case for `410` here. Before this change, accepting a ride after
missing it just silently assigned it to you (or, more often, raced against whoever else tapped Accept
first) — now there's an explicit, no-second-chances rejection for a job you were offered but didn't
respond to in time.

### 3. Rejecting an offer now hands it to the next driver immediately

`POST /api/driver/reject-ride` behaves the same from this app's point of view (same request, same success
response) — but server-side, rejecting now immediately offers the job to the next nearest eligible driver,
instead of just marking it rejected and waiting for someone else to poll into it. No action needed on your
side; mentioned so you know rejecting is no longer a "soft" action with an indefinite gap before anyone
else sees the job.

### 4. What an un-updated app build experiences

**Nothing breaks.** We tested this explicitly:

- A build that never reads `offer_expires_at`/`offer_seconds_remaining` just doesn't show a countdown —
  every other field and the overall polling behavior is identical to before.
- A build that taps Accept straight from a push notification (`NewRideRequested`), without ever having
  polled `get-rides` for that ride, still works exactly as before — there's no pending offer record
  in that case, so `accept-ride` falls through to the same direct-accept path it always used.
- A build that polls, then accepts within the timeout window, succeeds exactly as before.
- The only *new* way a request can fail is the `410` case above — accepting a specific offer late, after
  that specific driver's own window ran out. An old build will simply show its generic error-handling
  fallback for an unrecognized status code, same as it would for any other error it doesn't have a
  specific case for; it will not crash or behave unpredictably.

**Recommended but not required:** update to show the countdown and handle `410` with a clear "this job was
taken while you were deciding" message, rather than the generic error fallback.

---

## Customer App

### 1. Taxi ride request — optional payment method (new field)

`POST /api/customer/request-ride`

```json
{
  "...": "...all existing fields, unchanged...",
  "payment_method": "cash"
}
```
`payment_method`: `cash` \| `card`, **optional** — omit it and it defaults to `cash`. This app has never had
a card payment gateway wired up for taxi rides (same situation parcels were in before Batch 1 restricted
those to COD-only), so `cash` is the only value that reflects anything real right now. Added so the backend
can track whether a completed ride was a cash fare — needed for Part 5 (driver outstanding balance) — not
because card payment actually works yet. Safe to leave this field out entirely; nothing breaks.

---

## Driver App (taxi)

### 1. Earnings — balance fields (new)

`GET /api/driver/earnings` (existing endpoint from Batch 1) now also returns:

```json
{
  "rows": ["...unchanged..."],
  "totals": ["...unchanged..."],
  "payout_status": ["...unchanged..."],
  "cash_balance": 0,
  "cash_limit": 5000,
  "cash_over_limit": false,
  "outstanding_balance": 105.33,
  "outstanding_limit": 5000,
  "outstanding_over_limit": false
}
```
Both balance pairs are always present regardless of driver type — `cash_balance`/`cash_limit` is the Batch 1
rider cash-in-hand figure (always 0 for a taxi-only driver), `outstanding_balance`/`outstanding_limit` is
new: what this driver owes the platform (commission + SST kept out of pocket on cash fares), compared
against the admin-configured limit. Show whichever pair is non-zero/relevant for the account you're
displaying — the response doesn't try to guess which one matters for a given driver.

### 2. Ride-offer poll — outstanding-balance block (new, same pattern as the existing cash-limit block)

`GET /api/driver/get-rides`

A taxi driver at or over their outstanding-balance limit gets the same empty-list-with-message treatment
Batch 1 already does for delivery riders over their cash limit:
```json
{
  "rides": [],
  "message": "Your outstanding balance is over the limit. Please settle with the office before accepting new deliveries."
}
```
Show this message prominently, same as the existing cash-limit one.

### 3. Accept ride — outstanding-balance block (new)

`POST /api/driver/accept-ride` — second line of defense, same pattern as the existing cash-limit check:
```json
{ "message": "Your outstanding balance is over the limit. Please settle with the office before accepting new deliveries." }
```
(HTTP `403`)

---

## Delivery App

Everything in the "Breaking behaviour changes" section above applies here too — parcels and food-delivery
jobs go through the exact same `get-rides`/`accept-ride`/`reject-ride` endpoints as taxi rides, so
the offer-expiry behavior is identical. No delivery-specific changes beyond that in this batch.

---

## Partner App (restaurant staff)

### 1. Restaurant verification — submit/update documents (new)

`POST /api/restaurant/verification/submit` — `multipart/form-data`. Every field is optional on each call,
so the owner can fill the form over several visits — the backend only moves the status to `submitted` once
every required field is present.

**Request**
```
owner_cnic_front: <image file>
owner_cnic_back: <image file>
ntn_number: "1234567-8"
food_authority_license: <image or PDF file>
bank_account_title: "Spice Garden Restaurant"
bank_iban: "PK00TEST0000000000000000"
logo: <image file>
storefront_photo: <image file, optional even for final submission>
```
`storefront_photo` is the only field that stays optional even for a complete submission — everything else
is required before status flips to `submitted`.

**Success (200) — still missing documents**
```json
{
  "message": "Documents saved. Still missing: owner_cnic_front, owner_cnic_back",
  "status": "not_submitted",
  "missing": ["owner_cnic_front", "owner_cnic_back"]
}
```
**Success (200) — complete, now submitted for review**
```json
{
  "message": "Verification submitted successfully.",
  "status": "submitted",
  "missing": []
}
```
**Error (404)** — no restaurant profile yet:
```json
{ "message": "Restaurant profile not found -- complete your shop details first." }
```

### 2. Restaurant verification — get status (new)

`GET /api/restaurant/verification/status`

```json
{
  "status": "rejected",
  "rejection_reason": "CNIC photo is blurry, please resubmit a clearer copy.",
  "submitted_at": "2026-10-10T09:00:00+00:00",
  "reviewed_at": "2026-10-11T14:30:00+00:00",
  "is_approved": false
}
```
`status`: `not_submitted` \| `submitted` \| `approved` \| `rejected`. `is_approved` is the actual live flag
that controls whether the restaurant is visible to customers and can receive orders — it's possible (though
unusual) for `status` to show `rejected` from the most recent review while `is_approved` is still `true`
from an earlier approval, if the owner resubmitted after already being live; always gate the "you're live"
UI state off `is_approved`, not off `status` alone.

**Screens needed:**
- A verification form collecting all the documents/fields above, allowing partial saves.
- A status screen: pending ("waiting for review"), rejected (show `rejection_reason`, let them resubmit —
  same submit endpoint, same fields, any already-uploaded file can be left out to keep it), approved
  ("you're live").
- Until `is_approved` is `true`, the restaurant won't appear in customer search/listing and any order
  placed against it will be rejected — there's no separate "preview as unapproved" mode, so don't let the
  owner think customers can see them yet.

---

## New fields reference

| Field | Where | Notes |
|---|---|---|
| `offer_expires_at` / `offer_seconds_remaining` | ride object, `get-rides` response | `null` for an admin-assigned ride; countdown otherwise |
| `payment_method` | taxi `request-ride` request | optional, defaults to `cash` |
| `cash_balance`/`cash_limit`/`outstanding_balance`/`outstanding_limit` | `earnings` response | always both pairs, regardless of driver type |
| `is_approved` | restaurant verification status response | the actual live/visible flag — gate UI on this, not `status` |
