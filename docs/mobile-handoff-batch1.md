# Mobile Handoff — Batch 1 (Parts 1–9)

Every payload below is copied from the actual `Validator::make` rules and `response()->json()` calls in the
controllers, not inferred. All routes are under `/api`; auth is the existing bearer token per route group
(customer / driver / restaurant-staff).

**Revised after security/logic review (2026-10-12)** — see the end of each affected section for what
changed and why. Three real issues were fixed in the code (not just documented): client-trusted distance
on parcel booking, a cash-collection loophole at delivery/completion, and a parcel left permanently stuck
with its rider when the receiver was unreachable. Two new endpoints were added (parcel fare quote,
earnings).

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

### 4. Food order placement — distance limit (changed behaviour)

Existing place-order endpoint already computed the delivery distance server-side (haversine from the
restaurant's stored coordinates to the delivery address) before this review, and continues to — it was not
affected by the client-trusted-distance issue fixed in parcel booking above. It rejects orders whose
delivery distance exceeds the admin-configured limit (default 4 km):
```json
{ "message": "This address is beyond the 4 km food delivery limit." }
```
Surface this at checkout, not as a generic error.

### 5. Delivery code shown at checkout (changed)

Every placed food order now returns/includes a `delivery_code` (4-digit) on the order object — display it
on the order tracking screen, same as the parcel flow above. The customer reads it out to the rider at the
door.

### 6. What you receive when an assigned order is cancelled

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

**The separate `confirm-parcel-cash-collected` endpoint (below) still works** for backward compatibility;
new builds should send `cash_collected_amount` here instead.

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

### 3. Parcel cash collection confirmation (kept for backward compatibility only)

`POST /api/driver/confirm-parcel-cash-collected`

**New app builds don't need to call this anymore** — send `cash_collected_amount` directly in the status
update above instead.

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

---

## Partner App (restaurant staff)

### 1. Order cancellation (new)

`POST /api/restaurant/order/{order_id}/cancel` — works any time before the order reaches `delivered`,
`completed`, `cancelled` or `rejected`.

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
