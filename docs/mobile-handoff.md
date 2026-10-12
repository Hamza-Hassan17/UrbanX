# Mobile Handoff — Batch 1 (Parts 1–9)

Every payload below is copied from the actual `Validator::make` rules and `response()->json()` calls in the
controllers, not inferred. All routes are under `/api`; auth is the existing bearer token per route group
(customer / driver / restaurant-staff).

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
`payment_method`: `cod` \| `card` \| `cash` \| `jazzcash` \| `easypaisa`. `parcel_notes` and
`duration_minutes` are optional.

**Success (200)**
```json
{
  "ride_id": 1234,
  "delivery_fee": 245.00,
  "distance_actual_km": 6.4,
  "distance_charged_km": 7,
  "delivery_code": "4821",
  "message": "Parcel booked successfully!"
}
```
Show `delivery_code` on the tracking screen — the sender reads it to the receiver, who gives it to the
rider at drop-off.

**Error (400)** — distance beyond the admin-configured parcel limit:
```json
{ "message": "This address is beyond the 4 km parcel delivery limit." }
```

### 2. Order cancellation (new)

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

### 3. Food order placement — distance limit (changed behaviour)

Existing place-order endpoint now rejects orders whose delivery distance exceeds the admin-configured
limit (default 4 km):
```json
{ "message": "This address is beyond the 4 km food delivery limit." }
```
Surface this at checkout, not as a generic error.

### 4. Delivery code shown at checkout (changed)

Every placed food order now returns/includes a `delivery_code` (4-digit) on the order object — display it
on the order tracking screen, same as the parcel flow above. The customer reads it out to the rider at the
door.

---

## Delivery Rider App (food orders)

### 1. Order status update — delivery code required (changed)

`POST /api/restaurant/delivery/update-order-status`

**Request**
```json
{
  "order_id": 1234,
  "status": "delivered",
  "delivery_code": "4821"
}
```
`status`: `picked_up` \| `on_the_way` \| `delivered`. `delivery_code` is **required only when `status` is
`delivered`** — omit it for the earlier two statuses.

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

### 2. Cash collection confirmation (new)

`POST /api/restaurant/delivery/confirm-cash-collected`

Separate action from marking delivered — prompt for it right after delivery confirmation, only when
`payment_method == "cod"`.

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

### 3. Cash-in-hand limit block (new behaviour, no new endpoint)

Riders at/over their cash-in-hand limit get an empty offers list, and are blocked from accepting any ride
offered anyway. See "Driver / Rider App" section below — the poll and accept endpoints are shared between
delivery riders and taxi drivers.

---

## Driver / Rider App (parcels + taxi rides — shared endpoints)

### 1. Parcel status update — delivery code required (changed)

`POST /api/driver/update-ride-status`

**Request**
```json
{
  "ride_id": 1234,
  "status": "completed",
  "delivery_code": "4821"
}
```
`status`: `en_route` \| `arrived` \| `started` \| `completed`. `delivery_code` is only checked when
`status` is `completed` **and** the ride actually has a `delivery_code` set (a taxi ride won't have one —
only parcel rides do, so this one endpoint serves both). Same error shapes as the food-order version above
(`422` wrong code with remaining-attempts message, `403` once locked).

### 2. Parcel receiver unreachable (new)

`POST /api/driver/parcel-receiver-unreachable`

Alternate path when the rider can't reach the receiver at all — **does not complete the delivery**, only
flags it for admin review. Keep this as a visually distinct action from "Mark Delivered", not a fallback
inside the same code-entry screen.

**Request** — `multipart/form-data`
```
ride_id: 1234
photo: <image file, max 5MB>
```
**Success (200)**
```json
{ "message": "Flagged for admin review." }
```
**Error (422)** — not a parcel ride:
```json
{ "message": "This is not a parcel delivery." }
```

### 3. Parcel cash collection confirmation (new)

`POST /api/driver/confirm-parcel-cash-collected`

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
| `restaurant_orders.status` | order object | now includes `cancelled` (sender-initiated) alongside existing values |
| `delivery_code` | order/ride object | 4-digit string, set at creation |
| `delivery_code_locked` | order/ride object | `true` after 5 wrong code attempts — stop prompting, show support message |
| `receiver_unreachable_photo` / `flagged_for_review` | ride object (parcel) | set after the receiver-unreachable flow |
