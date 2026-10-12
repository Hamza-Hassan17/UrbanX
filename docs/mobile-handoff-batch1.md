# Mobile Handoff — Batch 1 (Parts 1–9)

Every payload below is copied from the actual `Validator::make` rules and `response()->json()` calls in the
controllers, not inferred. All routes are under `/api`; auth is the existing bearer token per route group
(customer / driver / restaurant-staff).

**Revised after security/logic review (2026-10-12)**, **revised again after a second review the same
day** — see the end of each affected section for what changed and why. Across both passes: client-trusted
distance on parcel booking and on the food checkout fee quote, a cash-collection loophole at
delivery/completion, a cash-amount integrity gap (rider's entered amount was trusted outright), a parcel
left permanently stuck with its rider when the receiver was unreachable, a missing rider notification on
an admin/restaurant-initiated cancellation, and a restaurant cancellation window that didn't close at
pickup — all fixed in code, not just documented. New endpoints: parcel fare quote, food delivery fee
quote (fixed an existing one, see Customer App #4), earnings.

---

## Customer App

### 1. Parcel booking (new)

`POST /api/customer/book-parcel`

**Request**
```json
{
  "pickup_latitude": 24.8607,
  "pickup_longitude": 67.0011,
  "dropoff_latitude": 24.8921,
  "dropoff_longitude": 67.0345,
  "distance_km": 6.4,
  "duration_minutes": 18,
  "sender_name": "Ali Raza",
  "sender_phone": "+923001234567",
  "receiver_name": "Sara Khan",
  "receiver_phone": "+923017654321",
  "package_type": "Documents",
  "package_size": "small",
  "parcel_notes": "Handle with care",
  "delivery_fee_paid_by": "sender",
  "payment_method": "cod"
}
```
`package_size`: `small` \| `medium` \| `large`. `delivery_fee_paid_by`: `sender` \| `receiver`.
`parcel_notes` and `duration_minutes` are optional.

**`payment_method` — only `cod` is accepted right now.** `card`, `cash`, `jazzcash`, `easypaisa` are still
listed in the validator (so the field doesn't 400 on an unrecognized value) but are explicitly rejected
with a clear message, since none of them have a working payment path behind them yet:
```json
{ "message": "Only cash on delivery (cod) is supported for parcels right now." }
```
Build the parcel booking screen with a single payment method shown (COD) until a gateway exists.

**`distance_km` / `duration_minutes` are now informational only — never trusted for pricing.** Send your
best client-side estimate if you have one (e.g. for a progress indicator), or omit them; the server always
recomputes the straight-line distance itself from `pickup_latitude/longitude` and
`dropoff_latitude/longitude` (same haversine formula used for the food-delivery distance limit) and uses
*that* for both the fee and the distance-limit check below. A client-sent `distance_km` is accepted but
ignored.

**Success (200)**
```json
{
  "ride_id": 1234,
  "delivery_fee": 240.00,
  "distance_actual_km": 2.5,
  "distance_charged_km": 3,
  "delivery_code": "4821",
  "message": "Parcel booked successfully!"
}
```
Show `delivery_code` on the tracking screen — the sender reads it to the receiver, who gives it to the
rider at drop-off.

**Error (400)** — distance beyond the admin-configured parcel limit (default 4 km; this is the real
server-computed distance, so a 6+ km pickup/dropoff pair is correctly rejected even if the client claimed a
smaller number):
```json
{ "message": "This address is beyond the 4 km parcel delivery limit." }
```

### 2. Parcel fare quote (new)

`POST /api/customer/quote-parcel-fee`

Lets the checkout screen show the delivery fee **before** the customer commits to booking — `book-parcel`
itself only returns the fee after creating the ride. Same server-side distance calculation and pricing as
booking; this endpoint creates nothing.

**Request**
```json
{
  "pickup_latitude": 24.8607,
  "pickup_longitude": 67.0011,
  "dropoff_latitude": 24.8707,
  "dropoff_longitude": 67.0111
}
```
**Success (200)**
```json
{
  "distance_actual_km": 1.5,
  "distance_charged_km": 2,
  "delivery_fee": 195.00,
  "max_distance_km": 4
}
```
Distance and fee use the first-km-fee + per-km-fee pricing from Pricing & Fees, rounded **up** to the next
whole km (e.g. 1.5 km actual → 2 km charged). `max_distance_km` is given so the checkout screen can show a
"too far" state before the customer even fills in sender/receiver details.

**Error (400)** — same shape as booking, beyond the limit:
```json
{ "message": "This address is beyond the 4 km parcel delivery limit." }
```

### 3. Order cancellation (new)

`POST /api/customer/cancel-order/{order_id}` — only works while the order is still `pending` (restaurant
hasn't accepted it yet).

**Request**
```json
{ "reason": "Customer called to cancel" }
```
`reason` required, max 500 chars.

**Success (200)**
```json
{
  "message": "Order cancelled successfully",
  "order": { "id": 1234, "status": "cancelled", "...": "...rest of the order object" }
}
```
**Error (422)** — already accepted:
```json
{ "message": "This order can no longer be cancelled -- the restaurant has already accepted it." }
```

### 4. Food delivery fee quote at checkout (fixed — was using the wrong pricing entirely)

`GET /api/restaurant/calculate-delivery-fare`

This is the endpoint the checkout screen calls to show the delivery fee before placing a food order. It
existed before Batch 1, but was never updated to use the Pricing & Fees settings added in Part 1 — it was
still pricing off `VehicleType::first_km_price`/`other_km_price` (a leftover from an older system) and
trusted a client-sent `distance_km` outright. Both are fixed now:

**Request**
```
GET /api/restaurant/calculate-delivery-fare?restaurant_id=7&delivery_lat=24.8921&delivery_lang=67.0345
```
`restaurant_id`, `delivery_lat`, `delivery_lang` are now **required**. `distance_km` is still accepted (so
older app builds don't 400) but is ignored — the server computes the real distance itself via haversine
from the restaurant's stored coordinates to `delivery_lat`/`delivery_lang`, exactly like `placeOrder`'s own
distance-limit check, and prices it using `food_first_km_fee`/`food_per_km_fee` from Pricing & Fees. The
number this endpoint quotes now always matches what `placeOrder` will actually charge.

**Success (200)**
```json
{
  "distance_actual_km": 1.46,
  "distance_charged_km": 2,
  "total_fare": 195.00,
  "is_boost": false,
  "boost_multiplier": 1,
  "boosted_fare": 195.00
}
```
`is_boost`/`boost_multiplier`/`boosted_fare` are an older, unrelated busy-hour surcharge feature, kept
as-is — `boosted_fare` is informational only and was never actually charged into an order either before or
after this fix.

**Error (400)** — beyond the limit, same shape as the other distance-limit errors:
```json
{ "message": "This restaurant doesn't deliver to your address -- it's 5.2 km away, beyond the 4 km delivery limit." }
```

**Also fixed in `placeOrder` itself**: the actual order's `delivery_fee` was previously always `0`
regardless of distance (it read from a cart field that nothing ever set), so `total_price` was silently
short by the delivery charge. `placeOrder` now computes the fee the same way this quote endpoint does and
folds it into `total_price` correctly — existing already-placed orders are unaffected (their stored
`total_price` doesn't change), only orders placed after this fix are priced correctly.

### 5. Food order placement — distance limit (unchanged, confirmed correct)

`placeOrder` already computed the delivery distance server-side (haversine from the restaurant's stored
coordinates to the delivery address) before this review, and continues to — it was not affected by the
client-trusted-distance issue fixed in parcel booking and the checkout quote above. It rejects orders whose
delivery distance exceeds the admin-configured limit (default 4 km):
```json
{ "message": "This address is beyond the 4 km food delivery limit." }
```
Surface this at checkout, not as a generic error.

### 6. Delivery code shown at checkout (changed)

Every placed food order now returns/includes a `delivery_code` (4-digit) on the order object — display it
on the order tracking screen, same as the parcel flow above. The customer reads it out to the rider at the
door.

### 7. What you receive when an assigned order is cancelled

A customer's own cancellation (#3 above) is a direct response to *their* request, so no separate push is
needed for it. But an order can also be cancelled by the **restaurant** or an **admin** after the customer
already placed it — for that, listen for the existing private-channel broadcast your app should already be
subscribed to for order tracking:

**Channel**: `private-restaurant-order.{order_id}`, event `order.updated`
```json
{
  "order_id": 1234,
  "order_number": "ORD-ABC123",
  "status": "cancelled",
  "restaurant_id": 7,
  "customer_id": 42,
  "total_price": 1150.00,
  "rider": null,
  "order_status_updated_at": "2026-10-12T10:15:00+00:00"
}
```
`status: "cancelled"` is the signal to stop polling/tracking and show the cancellation screen. There's no
separate `cancelled_by` or `cancel_reason` field on this broadcast payload (it's the same generic
order-updated event every other status change uses) — fetch the order detail endpoint if you need to show
the reason text to the customer.

A push notification (title "Order Cancelled", body includes the reason) also arrives via the existing
notification channel, addressed to the customer (when the restaurant or admin cancelled) or to the
restaurant (when the customer or admin cancelled) — not to both when the canceller is one of those two
parties.

**If a rider was already assigned when the cancellation happened** — see "Delivery Rider App" section 5
below (`AssignedOrderCancelled`, a dedicated channel the rider app should subscribe to). That's new as of
the second review pass; before it, a rider whose order got cancelled out from under them by the restaurant
or an admin kept no formal signal that their job was gone (they'd eventually notice the order detail screen
had changed, but nothing told them to stop and move on).

---

## Delivery Rider App (food orders)

### 1. Order status update — delivery code AND cash now required together (changed)

`POST /api/restaurant/delivery/update-order-status`

**Request**
```json
{
  "order_id": 1234,
  "status": "delivered",
  "delivery_code": "4821",
  "cash_collected_amount": 1150.00
}
```
`status`: `picked_up` \| `on_the_way` \| `delivered`. `delivery_code` is **required only when `status` is
`delivered`** — omit it for the earlier two statuses.

**`cash_collected_amount` is now required in this same request** when the order is `payment_method: cod`
and cash hasn't already been confirmed via the separate endpoint below. The status update, the
`payment_status → collected` flip, and the rider cash ledger entry all happen in one atomic transaction —
there is no longer a way to mark a COD order delivered without the cash amount being recorded somewhere.
If you omit it on a COD order that hasn't been confirmed yet:
```json
{ "message": "cash_collected_amount is required to mark a cash-on-delivery order as delivered." }
```
(HTTP 422). For a non-COD order, or a COD order already confirmed via the separate endpoint, omit the field
entirely — it's simply ignored.

**Cash amount integrity (new)** — `cash_collected_amount` is compared against the amount actually due
(`order.total_price`, computed server-side), not trusted outright. If the rider enters a different number
than what's due:
- The delivery **still completes normally** — this never blocks the rider.
- The rider cash ledger always records the **amount due**, never the amount the rider entered — so the
  ledger can't be under-reported by typing a smaller number.
- The order is flagged (`cash_discrepancy_flagged: true`, with `cash_entered_amount` and `cash_due_amount`
  both stored) for admin review in the dashboard.

There's no error response for a mismatch and nothing in the success response tells you a mismatch
happened — the app doesn't need to react to this at all, it's purely a backend/admin-side flag. Just make
sure the rider is entering the real cash amount in good faith; the field exists to catch honest counting
mistakes and theft alike, not to be worked around.

**The separate `confirm-cash-collected` endpoint (below) still works**, for backward compatibility with
already-shipped app builds that call it as its own step before marking delivered. **New app builds should
stop calling it separately** and just send `cash_collected_amount` in this one request instead — one less
round trip, and it's now the only way the server actually enforces that cash was collected.

**Success (200)**
```json
{ "message": "Delivery status updated.", "order": { "...": "..." } }
```
**Error (422)** — wrong code, attempts remaining:
```json
{ "message": "Incorrect delivery code. 3 attempt(s) remaining." }
```
**Error (403)** — after 5 wrong attempts, order is locked:
```json
{ "message": "Too many incorrect codes -- this order needs an admin override to mark delivered." }
```
Once locked, stop prompting for the code — show the locked message and tell the rider to contact support.
Only an admin override (dashboard-side) clears it.

### 2. Cash collection confirmation (kept for backward compatibility only)

`POST /api/restaurant/delivery/confirm-cash-collected`

**New app builds don't need to call this anymore** — send `cash_collected_amount` directly in the status
update above instead. This endpoint still works standalone for already-shipped builds.

**Request**
```json
{ "order_id": 1234, "amount": 1095.00 }
```
**Success (200)**
```json
{ "message": "Cash collection confirmed." }
```
**Error (422)**
```json
{ "message": "This order is not cash on delivery." }
```
```json
{ "message": "Cash already confirmed for this order." }
```

### 3. Cash-in-hand limit block (no new endpoint)

Riders at/over their cash-in-hand limit get an empty offers list, and are blocked from accepting any ride
offered anyway. See "Driver / Rider App" section below — the poll and accept endpoints are shared between
delivery riders and taxi drivers.

### 4. Earnings breakdown (new — see "Driver / Rider App" section 6)

Food-delivery earnings are included in the same `/api/driver/earnings` endpoint documented below.

### 5. Assigned order cancelled — notification + channel (new)

If a food order the rider is actively delivering gets cancelled by the **customer**, the **restaurant**, or
an **admin**, the rider is released from it immediately and told via two channels:

**Push notification** — title "Order Cancelled", body "Order #{order_number} was cancelled. You've been
released from this delivery."

**Broadcast channel**: `private-driver.{driver_id}` (your own id — subscribe to this once at login/online,
not per-order), event `order.cancelled`:
```json
{
  "order_id": 1234,
  "status": "cancelled",
  "cancelled_by": "admin",
  "reason": "Customer requested a refund"
}
```
`cancelled_by`: `customer` \| `restaurant` \| `admin`. **What the rider app should do**: clear whatever
"current job" state it's showing for this order immediately — treat it the same as finishing a delivery,
not as an error. The rider is already released server-side (no need to call any release/unassign
endpoint) and can accept new offers right away.

---

## Driver / Rider App (parcels + taxi rides — shared endpoints)

### 1. Parcel status update — delivery code AND cash now required together (changed)

`POST /api/driver/update-ride-status`

**Request**
```json
{
  "ride_id": 1234,
  "status": "completed",
  "delivery_code": "4821",
  "cash_collected_amount": 245.00
}
```
`status`: `en_route` \| `arrived` \| `started` \| `completed`. `delivery_code` is only checked when
`status` is `completed` **and** the ride actually has a `delivery_code` set (a taxi ride won't have one —
only parcel rides do, so this one endpoint serves both).

**`cash_collected_amount` is now required in this same request** when completing a `payment_method: cod`
parcel that hasn't already had its cash confirmed via the separate endpoint below — same atomic
status-update + ledger-write guarantee as the food-order version above. Omit it on a taxi ride, a non-COD
parcel, or an already-confirmed one.
```json
{ "message": "cash_collected_amount is required to complete a cash-on-delivery parcel." }
```
(HTTP 422). Same error shapes otherwise as the food-order version (`422` wrong code with
remaining-attempts message, `403` once locked).

**Cash amount integrity (new)** — same behaviour as the food-order version: `cash_collected_amount` is
compared against the amount actually due (`ride.total_fare`, computed server-side). A mismatch never
blocks completion; the ledger always records the amount due (not what the rider entered), and the ride is
flagged (`cash_discrepancy_flagged`, `cash_entered_amount`, `cash_due_amount`) for admin review.

**When to collect the cash — pickup or drop-off?** This depends on `delivery_fee_paid_by` from the booking:
- **`delivery_fee_paid_by: "receiver"`** (the common case) — collect at drop-off, same moment you mark the
  parcel `completed`. Send `cash_collected_amount` in that same request, as documented above.
- **`delivery_fee_paid_by: "sender"`** — the rider should collect from the **sender at pickup**, not wait
  until drop-off. Call the standalone `confirm-parcel-cash-collected` endpoint (below) right after pickup
  (e.g. when you'd otherwise send `status: "started"` or `"en_route"`). Once that call succeeds,
  `payment_status` is already `collected`, so the later `completed` request doesn't need
  `cash_collected_amount` at all — it's simply skipped since the cash was already confirmed. Build the
  rider app to check `delivery_fee_paid_by` right after accepting the job and prompt for cash at the
  correct end accordingly.

**The separate `confirm-parcel-cash-collected` endpoint (below) still works** for backward compatibility
*and* is the mechanism for the sender-funded, pickup-time case above; new builds for the receiver-funded
case should send `cash_collected_amount` inline at completion instead.

### 2. Parcel receiver unreachable (changed — now actually resolves the job)

`POST /api/driver/parcel-receiver-unreachable`

Alternate path when the rider can't reach the receiver at all — **does not complete the delivery**, only
flags it for admin review. Keep this as a visually distinct action from "Mark Delivered", not a fallback
inside the same code-entry screen.

**What happens to the ride**: its status moves to `delivery_failed` (a new status value — see table at the
end of this doc) and **the rider is immediately released** (the app should treat this response the same as
finishing a job — show the normal post-delivery screen, not an error state). The rider is free to accept
new offers right away; this ride does not block them. The parcel itself sits in `delivery_failed` — nothing
re-attempts delivery automatically. An admin resolves it from the dashboard either by cancelling it
("returned to sender") or by putting it back in the dispatch queue for a different rider; the sender/
receiver experience on your side is whatever your existing cancelled/requested-again UI already does for
those two ride statuses.

**Request** — `multipart/form-data`
```
ride_id: 1234
photo: <image file, max 5MB>
```
**Success (200)**
```json
{ "message": "Flagged for admin review. You have been released from this parcel and are now available for new offers." }
```
**Error (422)** — not a parcel ride:
```json
{ "message": "This is not a parcel delivery." }
```

### 3. Parcel cash collection confirmation

`POST /api/driver/confirm-parcel-cash-collected`

**Corrected** — this is NOT just a backward-compatibility fallback. It's still required for the
sender-funded case: when `delivery_fee_paid_by: "sender"`, call this right after pickup (see section 1's
"When to collect the cash" note) since the cash is collected before the parcel is ever completed. For the
receiver-funded case, new builds can skip it and send `cash_collected_amount` inline at completion
instead — that part of the original wording still holds.

**Request**
```json
{ "ride_id": 1234, "amount": 245.00 }
```
Same success/error shapes as the food-order version above.

### 4. Ride-offer poll — cash limit block (changed behaviour)

The existing `getLatestRides` poll returns an empty list with an explanatory message instead of the usual
offers when the rider's cash-in-hand balance (confirmed cash collections minus office settlements) is at
or over the admin-configured limit:
```json
{
  "rides": [],
  "message": "Your cash-in-hand balance is over the limit. Please settle with the office before accepting new deliveries."
}
```
Show that `message` prominently — otherwise it looks like there's just no work available.

### 5. Accept ride — cash limit block (changed behaviour)

`POST /api/driver/accept-ride` — second line of defense if a ride was fetched just before the limit hit:
```json
{ "message": "Your cash-in-hand balance is over the limit. Please settle with the office before accepting new deliveries." }
```
(HTTP 403)

### 6. Earnings breakdown (new)

`GET /api/driver/earnings?start_date=2026-10-01&end_date=2026-10-31&type=parcel`

One shared endpoint for the Driver and Delivery apps (a delivery rider is a driver account, same auth
guard) — returns every completed job in range with its full money breakdown, read from the same
fare-breakdown snapshot the admin Payroll page uses (never recalculated from current commission rates, so
a rate change never alters a past job's numbers). `start_date`/`end_date`/`type` are all optional;
`type` filters to `taxi_ride` \| `parcel` \| `food_delivery` when given, otherwise all three are included.

**Success (200)**
```json
{
  "rows": [
    {
      "type": "parcel",
      "id": 503,
      "completed_at": "2026-10-12T12:53:35+05:00",
      "gross": 300.00,
      "commission_rate": 20,
      "commission_amount": 60.00,
      "sst_rate": 5,
      "sst_method": "inclusive",
      "sst_amount": 14.29,
      "net": 225.71
    },
    {
      "type": "food_delivery",
      "id": 42,
      "order_number": "ORD-ABC123",
      "completed_at": "2026-10-11T18:20:00+05:00",
      "gross": 240.00,
      "commission_rate": 20,
      "commission_amount": 48.00,
      "sst_rate": 5,
      "sst_method": "inclusive",
      "sst_amount": 11.43,
      "net": 180.57
    }
  ],
  "totals": {
    "gross": 540.00,
    "commission": 108.00,
    "sst": 25.72,
    "net": 406.28,
    "count": 2
  },
  "payout_status": {
    "paid_amount": 0,
    "is_fully_paid": false
  }
}
```
`type: "taxi_ride"` rows use the driver commission rate instead of the platform-share rate (different
setting, same shape). `payout_status.paid_amount` sums any `driver_payouts` rows recorded by Admin/Finance
for the queried period (via the dashboard "Mark as Paid" action) — use it to show the rider what's already
been paid out vs. what they've earned but not yet been paid for the period.

**`sst_method` (new)** — either `inclusive` (default, current behaviour: SST is backed out of the rider's
share as if it were already included in it) or `exclusive` (SST added on top of the share as its own
deduction instead). This is now an admin-configurable setting (Pricing & Fees page), not confirmed with the
client yet as to which is correct — it's per-job, frozen at the time that job completed, so changing the
setting later never alters past jobs' numbers. No app change needed unless you want to show which method
produced a given `sst_amount` (e.g. in a tooltip) — just display the field if present, ignore it otherwise.

---

## Partner App (restaurant staff)

### 1. Order cancellation — window now closes at pickup (changed)

`POST /api/restaurant/order/{order_id}/cancel`

**The restaurant can only cancel before the order is picked up** (`status` not yet `picked_up` or
`on_the_way`). Once a rider has physically picked up the food, only an admin can cancel it from the
dashboard — a restaurant can no longer unwind an order that's already out the door. Build the cancel
button to hide or disable itself once the order status reaches `picked_up`.

**Request**
```json
{ "reason": "Kitchen out of stock" }
```
**Success (200)**
```json
{ "message": "Order cancelled successfully", "order": { "...": "..." } }
```
**Error (422)** — already finalized:
```json
{ "message": "This order is already finalized and cannot be cancelled." }
```
**Error (422)** — already picked up (new):
```json
{ "message": "This order has already been picked up -- only an admin can cancel it now." }
```

### 2. Voucher creation — `funded_by` (changed, dashboard-only so far)

No Partner-API voucher-creation endpoint exists yet — vouchers are created from the Admin/Restaurant
dashboard. If a Partner API for vouchers gets added later, it will need a `funded_by` field
(`platform` \| `restaurant`) alongside the existing voucher fields, since that's what the restaurant
payable report (Finance) keys off to know whose margin absorbed the discount.

---

## New statuses & fields reference

| Field | Where | Values |
|---|---|---|
| `restaurant_orders.payment_status` / `rides.payment_status` | order/ride object | now includes `collected`, alongside existing `unpaid`, `paid`, `failed` |
| `restaurant_orders.status` | order object | now includes `cancelled`; see `cancelled_by` below for who |
| `restaurant_orders.cancelled_by` | order object | `customer` \| `restaurant` \| `admin` |
| `rides.status` | ride object | now also includes `delivery_failed` (parcel flagged receiver-unreachable, awaiting admin resolution) alongside the existing `requested`, `accepted`, `en_route`, `arrived`, `started`, `completed`, `cancelled` |
| `delivery_code` | order/ride object | 4-digit string, set at creation |
| `delivery_code_locked` | order/ride object | `true` after 5 wrong code attempts — stop prompting, show support message |
| `receiver_unreachable_photo` / `flagged_for_review` | ride object (parcel) | set after the receiver-unreachable flow; ride's `driver_id` is cleared at the same time |
| `cash_discrepancy_flagged` / `cash_entered_amount` / `cash_due_amount` | order/ride object | set when a rider's entered cash amount didn't match the amount due; admin-review-only, no app behaviour needed |
| `sst_method` | `fare_breakdown` snapshot (earnings rows) | `inclusive` \| `exclusive` — which SST calculation method applied to that job; display-only |
