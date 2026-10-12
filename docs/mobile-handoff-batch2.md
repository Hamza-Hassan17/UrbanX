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

### 5. "Legacy direct accept" will eventually be switched off — update before then

Right now, `accept-ride` still accepts a direct tap with no prior poll/offer (the old push-only flow),
controlled by a new admin setting — **Dispatch → "Allow Legacy Direct Accept"** — that defaults to **on**.
This is deliberate, to give already-shipped app builds time to update before anything actually breaks for
them.

**Once that setting is switched off** (we'll coordinate the timing with you), `accept-ride` will require a
real, unexpired pending offer for that specific driver and ride. An old build tapping Accept with no such
offer on record will get:
```json
{ "message": "You were not offered this ride." }
```
**HTTP status: `403`.** This is distinct from the `410` "This offer has expired." case in #2 above — `403`
means no offer was ever created for you on this job at all (e.g. you never polled), `410` means you had one
and it ran out.

**Admin-assigned rides are unaffected either way** — a ride the admin manually assigned to a driver has no
formal offer record to begin with and was never subject to this check, on or off.

**What to do now:** make sure your app always polls `get-rides` before showing an Accept button, so a real
offer exists by the time the driver taps it. Once you've shipped that, let us know and we'll flip the
switch — until then, nothing changes for you.

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

### 2. ⚠️ Not yet decided — what happens when a job exhausts all re-offer attempts with no driver

**This is current behaviour, documented as-is for visibility — we have not changed anything here and are
holding off until the client confirms what should happen.** Flagging it now so you know exactly what the
app receives (or doesn't) in this case today.

When a ride/parcel/food-delivery job has been offered to every eligible nearby driver in turn (Part 2's
automatic re-offer) and none of them accepted — either because `reoffer_attempts` hits the admin-configured
max, or because there's simply nobody left eligible to offer it to — **the customer app receives nothing
further.** Concretely:

- `status` stays `requested` indefinitely. There is no `failed`, `no_driver_found`, or similar status.
- The only broadcast the customer's own ride channel (`private-ride.{ride_id}`) receives during the whole
  re-offer sequence is a `ride.offer.created` event **each time a new driver is offered the job** — same
  event, same shape, whether it's the 1st driver tried or the 5th. There's no distinct signal for "this was
  the last eligible driver and nobody is left to try." A customer app that renders every `ride.offer.created`
  as "a driver is reviewing your request" will keep showing that same reassuring state even after the
  system has quietly given up.
- No push notification is sent to the customer in this case either.
- The only place this becomes visible at all is admin-side: once the job crosses the stale-unassigned
  threshold or the `max_reoffer_attempts` count, it shows up on the Rides/Delivery → Alerts page (Batch 2
  Part 3) for an admin to notice and act on manually (e.g. via "Assign Trip"). There is currently no
  automatic customer-facing fallback (cancel-and-refund, "no drivers available" message, widening the
  search radius, etc.).

We're not changing this yet — please confirm with the client what the intended behavior should be (an
explicit failure status the app can show, a timeout-and-cancel flow, something else), and we'll implement
whichever is decided.

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
  "message": "Your outstanding balance is over the limit. Please settle with the office before accepting new rides."
}
```
Show this message prominently, same as the existing cash-limit one.

### 3. Accept ride — outstanding-balance block (new)

`POST /api/driver/accept-ride` — second line of defense, same pattern as the existing cash-limit check:
```json
{ "message": "Your outstanding balance is over the limit. Please settle with the office before accepting new rides." }
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

**Bank detail changes after approval go to a pending state, not live immediately.** If the restaurant is
already approved and the owner changes `bank_account_title` or `bank_iban` to a different value than what's
currently on file, the restaurant **stays live/approved** but the new value is held as pending instead of
replacing the live one — payouts and the Restaurant Payable report keep using the old, approved details
until an admin reviews and approves the change from the dashboard. This only applies to an *already
approved* restaurant changing an *existing* value; a restaurant submitting its bank details for the first
time (nothing approved yet) saves normally, no pending state involved.

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
  "is_approved": false,
  "pending_bank_change": false
}
```
`status`: `not_submitted` \| `submitted` \| `approved` \| `rejected`. `is_approved` is the actual live flag
that controls whether the restaurant is visible to customers and can receive orders — it's possible (though
unusual) for `status` to show `rejected` from the most recent review while `is_approved` is still `true`
from an earlier approval, if the owner resubmitted after already being live; always gate the "you're live"
UI state off `is_approved`, not off `status` alone.

`pending_bank_change` (new) is `true` whenever a submitted bank detail change (see above) is still awaiting
admin review. Show something like "Your new bank details are pending approval" on the status screen when
this is `true` — the owner's live `bank_account_title`/`bank_iban` aren't returned by this endpoint (only
the flag is), since the app has no reason to show the old values back to the owner while they wait.

**Screens needed:**
- A verification form collecting all the documents/fields above, allowing partial saves.
- A status screen: pending ("waiting for review"), rejected (show `rejection_reason`, let them resubmit —
  same submit endpoint, same fields, any already-uploaded file can be left out to keep it), approved
  ("you're live"), and a "bank details pending approval" note when `pending_bank_change` is `true`.
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
| `pending_bank_change` | restaurant verification status response | `true` while a submitted bank detail change awaits admin approval |

**Error codes to add handling for, if not already present:**

| Code | When | Message |
|---|---|---|
| `410` | `accept-ride` on an offer that expired | "This offer has expired." |
| `403` | `accept-ride` with no offer on record at all, **once "Allow Legacy Direct Accept" is switched off** (not yet — see the Breaking Changes section) | "You were not offered this ride." |
