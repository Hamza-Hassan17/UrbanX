Batch 1,2, and 3:

All routes under /api, bearer auth per existing route group.

⚠ Breaking changes — read first
Driver/Delivery apps

1. Offers now expire. GET /api/driver/get-rides adds two fields per ride:

```
{
  "rides": [
    {
      "id": 1234,
      "...": "...existing fields unchanged...",
      "offer_expires_at": "2026-10-12T13:59:24+00:00",
      "offer_seconds_remaining": 27
    }
  ]
}
```
null for both on admin-assigned rides. Old app builds ignoring these fields keep working
unchanged, just no countdown.

2. Accepting an expired offer:
POST /api/driver/accept-ride

```
{ "message": "This offer has expired." }
```
HTTP 410.

3. Rejecting hands off instantly — POST /api/driver/reject-ride same request/response, but next
driver gets offered immediately server-side. No app change needed.

4. Un-updated app builds: nothing breaks; push-to-accept flow (no prior poll) still works via
direct-accept fallback.

5. "Legacy Direct Accept" will be switched off later. Once disabled, accept with no real offer on
record returns:

```
{ "message": "You were not offered this ride." }
```
HTTP 403. Admin-assigned rides unaffected. Action needed: always poll get-rides before
showing Accept.

6. Earnings — platform share is now a flat PKR amount (changed). GET /api/driver/earnings:
food_delivery rows no longer have commission_rate; parcel rows have commission_rate: null. Both
now carry commission_flat_pkr (the flat amount taken, e.g. 50). Taxi rows unchanged
(commission_rate %, commission_flat_pkr: null). Jobs completed before this change keep their old
snapshot (commission_rate 20, commission_flat_pkr null). Action needed: read commission_flat_pkr
when commission_rate is null/missing. Full example under Driver/Rider App → 6.

7. Online/offline (new). A driver/rider who taps "go offline" gets
{ "rides": [], "online_status": "offline" } from get-rides and 403 from accept-ride. Old builds
never call go-offline, stay online exactly as today. Details under Driver/Rider App → 7.

8. Food delivery fare: the rides row of a food order now takes subtotal/total_fare from the
server's delivery fee (same as order.delivery_fee), not from what the Customer app sent.

8a. Parcels moved from the Delivery app to the Driver app (changed). Once an admin picks the
"Parcel vehicle type" (Pricing & Fees → Dispatch, a taxi type, e.g. Bike), new parcels go only
to taxi drivers of that type and have "ride_type": "parcel" (was "delivery"). Delivery riders no
longer get new parcels. Until it's picked, parcels behave exactly as before.
- Driver app: build the parcel screens using the same endpoints as before (Driver/Rider App →
  1–3: update-ride-status with delivery_code + cash_collected_amount,
  parcel-receiver-unreachable, confirm-parcel-cash-collected). A parcel arrives through the
  normal GET /api/driver/get-rides poll; tell it apart by ride_type "parcel" (or
  delivery_code present) and show sender/receiver/package fields from the ride.
- Money: parcels now settle like taxi rides — commission % and the outstanding balance, not
  cash-in-hand. Earnings rows for these parcels: "type": "parcel", commission_rate set,
  commission_flat_pkr null.
- Customer app: POST /api/customer/book-parcel response adds "ride_type". Treat both
  "parcel" and "delivery" (older parcels) as a parcel on tracking/history screens.
- GET /api/driver/ride-history now returns all of the driver's jobs (rides, parcels,
  deliveries), not only taxi rides.

Customer app (breaking)

9. Place order can reject a cart that was fine earlier (changed). POST
/api/restaurant/customer/place-order re-checks items, add-ons and the minimum order against the
live menu. HTTP 400, show message as-is:

```
{ "message": "Chicken Burger is currently unavailable.", "cart_item_id": 812 }
{ "message": "\"Extra Cheese\" is currently unavailable. Please choose another option.", "cart_item_id": 812 }
{ "message": "An item in your cart is no longer on the menu. Please remove it and try again." }
{ "message": "Add Rs 150 more to reach the minimum order of Rs 500.", "minimum_order_amount": 500, "food_subtotal": 350, "shortfall": 150 }
```
Total is charged at current menu prices.

10. Cancel ride ownership (changed). POST /api/customer/cancel-ride on someone else's ride:

```
{ "message": "You are not authorized to cancel this ride." }
```
HTTP 403. Cancelling your own ride/parcel unaffected.

Customer App

1. Book parcel (new)
POST /api/customer/book-parcel

```
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
package_size: small|medium|large. delivery_fee_paid_by: sender|receiver. Only cod accepted;
others validate but reject:

```
{ "message": "Only cash on delivery (cod) is supported for parcels right now." }
```
distance_km/duration_minutes informational only — server recomputes.

Success (200):

```
{
  "ride_id": 1234,
  "delivery_fee": 240.00,
  "distance_actual_km": 2.5,
  "distance_charged_km": 3,
  "delivery_code": "4821",
  "message": "Parcel booked successfully!"
}
```
Error (400) beyond limit:

```
{ "message": "This address is beyond the 4 km parcel delivery limit." }
```

2. Quote parcel fee (new)
POST /api/customer/quote-parcel-fee

```
{
  "pickup_latitude": 24.8607,
  "pickup_longitude": 67.0011,
  "dropoff_latitude": 24.8707,
  "dropoff_longitude": 67.0111
}
```
Success (200):

```
{
  "distance_actual_km": 1.5,
  "distance_charged_km": 2,
  "delivery_fee": 195.00,
  "max_distance_km": 4
}
```

3. Cancel order (new)
POST /api/restaurant/customer/cancel-order/{order_id} — only while pending

```
{ "reason": "Customer called to cancel" }
```
Success (200):

```
{ "message": "Order cancelled successfully", "order": { "id": 1234, "status": "cancelled" } }
```
Error (422):

```
{ "message": "This order can no longer be cancelled -- the restaurant has already accepted it." }
```

4. Food delivery fee quote (fixed)
GET
/api/restaurant/customer/calculate-delivery-fare?restaurant_id=7&delivery_lat=24.8921&delivery_lang=67.0345
restaurant_id/delivery_lat/delivery_lang now required; distance_km ignored if sent.

Success (200):

```
{
  "distance_actual_km": 1.46,
  "distance_charged_km": 2,
  "total_fare": 195.00,
  "is_boost": false,
  "boost_multiplier": 1,
  "boosted_fare": 195.00
}
```
Error (400):

```
{ "message": "This restaurant doesn't deliver to your address -- it's 5.2 km away, beyond the 4
km delivery limit." }
```

5. Food order distance limit (unchanged)

```
{ "message": "This address is beyond the 4 km food delivery limit." }
```

6. Delivery code at checkout
Every placed food order now includes delivery_code (4-digit) on the order object.

7. Order-cancelled broadcast
Channel private-restaurant-order.{order_id}, event order.updated:

```
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
Plus push notification "Order Cancelled".

8. Taxi ride request — payment_method (new field)
POST /api/customer/request-ride

```
{
  "...": "...existing fields unchanged...",
  "payment_method": "cash"
}
```
Optional, cash|card, defaults cash.

9. ⛔ No driver found — built, switched OFF until client confirms (was "not yet decided")
Admin setting "No-driver handling" is Off by default. While Off, nothing changes: status stays
requested, job only shows on admin Alerts page. Build the screen now; it goes live only when an
admin switches it On.

When On (taxi rides and parcels only, never food orders): once max re-offer attempts are reached
or no eligible driver is left, status becomes no_driver_found. Channel private-ride.{ride_id},
event ride.status.updated, with "status": "no_driver_found". Push: "No drivers available right
now" / "We could not find a driver for your ride. You can try again or cancel." ("parcel"
instead of "ride" for parcels).

Retry — POST /api/customer/retry-dispatch

```
{ "ride_id": 1204 }
```
Success (200):

```
{ "message": "Looking for a driver again.", "ride_id": 1204, "status": "requested", "offered_to_driver": true }
```
Error (403):

```
{ "message": "You are not authorized to retry this ride." }
```
Error (400):

```
{ "message": "This ride is not waiting for a retry." }
```
Cancel — existing POST /api/customer/cancel-ride with ride_id works on no_driver_found.

10. Report a problem on an order/ride (new)
POST /api/submit-complain — existing endpoint, two new optional fields. Send one of ride_id or
restaurant_order_id, not both.

```
{
  "name": "Ali Khan",
  "subject": "Rider was rude",
  "complain_text": "The rider shouted at me at the gate.",
  "restaurant_order_id": 345
}
```
Success (200):

```
{
  "message": "Complain submitted successfully",
  "complain": {
    "name": "Ali Khan",
    "subject": "Rider was rude",
    "complain_text": "The rider shouted at me at the gate.",
    "status": "pending",
    "id": 91,
    "service": "food",
    "ride_id": 1204,
    "restaurant_order_id": 345
  }
}
```
service set by server from the linked record: ride|parcel|food (any service sent is ignored when
linked). Error (422):

```
{ "message": "This order was not found on your account." }
{ "message": "This ride was not found on your account." }
```
Complaint without a link still works as before.

has_complaint (true/false, new) on: GET /api/customer/get-ride-history (each ride_history item),
GET /api/restaurant/customer/orders (each order), GET
/api/restaurant/customer/order-details/{order_id} (order). true → show "Problem reported"
instead of the button.

11. Item add-ons on the menu (new)
GET /api/restaurant/customer/menu-items/{menu_id} and
GET /api/restaurant/customer/menu-item-details/{item_id} — each item adds option_groups
(empty array if none):

```
{
  "id": 55,
  "name": "Zinger Burger",
  "price": "650.00",
  "discount_price": "600.00",
  "is_available": "1",
  "option_groups": [
    {
      "id": 7,
      "name": "Size",
      "is_required": true,
      "selection_type": "single",
      "min_select": 1,
      "max_select": 1,
      "sort_order": 0,
      "options": [
        { "id": 21, "name": "Regular", "price_adjustment": "0.00", "is_available": true, "sort_order": 0 },
        { "id": 22, "name": "Large", "price_adjustment": "150.00", "is_available": true, "sort_order": 1 }
      ]
    },
    {
      "id": 8,
      "name": "Extras",
      "is_required": false,
      "selection_type": "multiple",
      "min_select": 0,
      "max_select": 3,
      "options": [
        { "id": 23, "name": "Extra Cheese", "price_adjustment": "80.00", "is_available": false, "sort_order": 0 },
        { "id": 24, "name": "Jalapeños", "price_adjustment": "50.00", "is_available": true, "sort_order": 1 }
      ]
    }
  ]
}
```
Item is_available stays "1"/"0" string; "0" = out of stock, not addable. Option is_available is
a real boolean; false = greyed out. single: exactly one if required, at most one if optional.
multiple: between min_select and max_select (max_select: null = no limit); required needs at least
one. Line price = discount_price (else price) + chosen price_adjustment.

12. Add to cart with add-ons (new field)
POST /api/restaurant/customer/add-to-cart

```
{ "restaurant_id": 4, "menu_item_id": 55, "quantity": 2, "option_ids": [22, 24] }
```
Omit option_ids for items without add-ons. Same item + same choices = one cart line; different
choices = separate lines. Errors (400):

```
{ "message": "Please choose an option for \"Size\"." }
{ "message": "Please choose at least 2 options for \"Sauces\"." }
{ "message": "You can choose up to 3 options for \"Extras\"." }
{ "message": "You can choose only one option for \"Size\"." }
{ "message": "\"Extra Cheese\" is currently unavailable. Please choose another option." }
{ "message": "Zinger Burger is currently unavailable." }
{ "message": "Zinger Burger has no add-ons to choose from." }
{ "message": "One of the selected add-ons does not belong to Zinger Burger. Please reopen the item and choose again." }
```

13. Cart and order show chosen add-ons (new fields)
Cart items (carts, cart-details) add selected_options and options_signature; order items
(place-order, orders, order-details) add item_name, base_price, selected_options — frozen at
order time:

```
{
  "id": 812,
  "restaurant_item_id": 55,
  "quantity": 2,
  "price_per_item": "800.00",
  "total_price": "1600.00",
  "selected_options": [
    { "group_id": 7, "group_name": "Size", "option_id": 22, "option_name": "Large", "price_adjustment": 150 },
    { "group_id": 8, "group_name": "Extras", "option_id": 24, "option_name": "Jalapeños", "price_adjustment": 50 }
  ],
  "options_signature": "22,24"
}
```
selected_options null for items without add-ons. price_per_item already includes add-ons.

14. Minimum order (new)
Restaurant listing, search, home and restaurant-details responses add
"minimum_order_amount": "500.00" (null = no minimum). GET /api/restaurant/customer/carts adds
minimum_order_amount per cart. GET /api/restaurant/customer/cart-details/{cart_id} adds
cart.minimum_order_amount and cart.minimum_order_message:

```
{ "minimum_order_amount": "500.00", "minimum_order_message": "Add Rs 150 more to reach the minimum order of Rs 500." }
```
minimum_order_message null when met. Minimum is on the food subtotal, before delivery fee and
discounts. Below it, place-order → 400 (see Breaking changes → 9).

Delivery Rider App (food orders)

1. Update order status — cash inline (changed)
POST /api/restaurant/delivery/update-order-status

```
{
  "order_id": 1234,
  "status": "delivered",
  "delivery_code": "4821",
  "cash_collected_amount": 1150.00
}
```
status: picked_up|on_the_way|delivered. delivery_code required only for delivered.
cash_collected_amount required inline for COD unless already confirmed separately.

```
{ "message": "cash_collected_amount is required to mark a cash-on-delivery order as
delivered." }
```
(422). Mismatch vs amount due never blocks; ledger always records amount due; flags
cash_discrepancy_flagged, cash_entered_amount, cash_due_amount.

Success (200):

```
{ "message": "Delivery status updated.", "order": { "...": "..." } }
```
Error (422) wrong code:

```
{ "message": "Incorrect delivery code. 3 attempt(s) remaining." }
```
Error (403) locked:

```
{ "message": "Too many incorrect codes -- this order needs an admin override to mark
delivered." }
```

2. Confirm cash collected (legacy-compat only)
POST /api/restaurant/delivery/confirm-cash-collected

```
{ "order_id": 1234, "amount": 1095.00 }
```
Success:

```
{ "message": "Cash collection confirmed." }
```
Errors (422):

```
{ "message": "This order is not cash on delivery." }

{ "message": "Cash already confirmed for this order." }
```

3. Cash-in-hand limit — poll block
GET /api/driver/get-rides:

```
{
  "rides": [],
  "message": "Your cash-in-hand balance is over the limit. Please settle with the office before
accepting new deliveries."
}
```

4. Cash-in-hand limit — accept block
POST /api/driver/accept-ride (403):

```
{ "message": "Your cash-in-hand balance is over the limit. Please settle with the office before
accepting new deliveries." }
```

5. Assigned order cancelled (new)
Channel private-driver.{driver_id}, event order.cancelled:

```
{
  "order_id": 1234,
  "status": "cancelled",
  "cancelled_by": "admin",
  "reason": "Customer requested a refund"
}
```
Push: "Order Cancelled" / "Order #{order_number} was cancelled. You've been released from
this delivery."

6. Go online / go offline (new)
Same as Driver/Rider App → 7, under the delivery prefix:
POST /api/restaurant/delivery/go-online
POST /api/restaurant/delivery/go-offline
GET /api/restaurant/delivery/online-status
(The /api/driver/... versions work too.) While offline:

```
{ "rides": [], "online_status": "offline" }
```
from GET /api/restaurant/delivery/get-rides, and POST /api/restaurant/delivery/accept-ride (403):

```
{ "message": "You are offline. Go online to accept jobs." }
```
POST /api/restaurant/delivery/update-location (existing) also counts as a location update for
auto-offline. Between deliveries keep calling POST /api/driver/location/ping without ride_id.

7. Food delivery fare (changed)
total_fare on a food order's ride = order.delivery_fee, computed by the server (see Breaking
changes → 8).

Driver/Rider App (parcel + taxi shared)

1. Update ride status — cash inline (changed)
POST /api/driver/update-ride-status

```
{
  "ride_id": 1234,
  "status": "completed",
  "delivery_code": "4821",
  "cash_collected_amount": 245.00
}
```
status: en_route|arrived|started|completed. delivery_code only checked for completed on rides
that have one (parcels only).

```
{ "message": "cash_collected_amount is required to complete a cash-on-delivery parcel." }
```
(422). Same cash-integrity behavior as food-order version. Collect at drop-off if
delivery_fee_paid_by: "receiver"; collect at pickup via endpoint below if delivery_fee_paid_by:
"sender".

2. Parcel receiver unreachable (changed)
POST /api/driver/parcel-receiver-unreachable — multipart/form-data

```
ride_id: 1234
photo: <image file, max 5MB>
```
Success (200):

```
{ "message": "Flagged for admin review. You have been released from this parcel and are now
available for new offers." }
```
Error (422):

```
{ "message": "This is not a parcel delivery." }
```
Ride moves to delivery_failed, rider released immediately.

3. Confirm parcel cash collected
POST /api/driver/confirm-parcel-cash-collected

```
{ "ride_id": 1234, "amount": 245.00 }
```
Required for sender-funded parcels at pickup time; same success/error shapes as food-order
version above.

4. Ride-offer poll — cash/outstanding block
GET /api/driver/get-rides:

```
{
  "rides": [],
  "message": "Your cash-in-hand balance is over the limit. Please settle with the office before
accepting new deliveries."
}
```
Taxi outstanding-balance version:

```
{
  "rides": [],
  "message": "Your outstanding balance is over the limit. Please settle with the office before
accepting new rides."
}
```

5. Accept ride — cash/outstanding block
POST /api/driver/accept-ride (403):

```
{ "message": "Your cash-in-hand balance is over the limit. Please settle with the office before
accepting new deliveries." }

{ "message": "Your outstanding balance is over the limit. Please settle with the office before
accepting new rides." }
```
Plus (from Breaking Changes above): 410 expired-offer, 403 "You are offline. Go online to accept
jobs.", and later 403 "You were not offered this ride."

6. Earnings breakdown (updated)
GET /api/driver/earnings?start_date=2026-10-01&end_date=2026-10-31&type=parcel
type: taxi_ride|parcel|food_delivery, optional.

Success (200):

```
{
  "rows": [
    {
      "type": "taxi_ride",
      "id": 501,
      "completed_at": "2026-10-12T11:10:00+05:00",
      "gross": 600.00,
      "commission_rate": 7.63,
      "commission_flat_pkr": null,
      "commission_amount": 45.78,
      "sst_rate": 5,
      "sst_method": "inclusive",
      "sst_amount": 28.57,
      "net": 525.65
    },
    {
      "type": "parcel",
      "id": 503,
      "completed_at": "2026-10-12T12:53:35+05:00",
      "gross": 300.00,
      "commission_rate": null,
      "commission_flat_pkr": 50,
      "commission_amount": 50.00,
      "sst_rate": 5,
      "sst_method": "inclusive",
      "sst_amount": 14.29,
      "net": 235.71
    },
    {
      "type": "food_delivery",
      "id": 42,
      "order_number": "ORD-ABC123",
      "completed_at": "2026-10-11T18:20:00+05:00",
      "gross": 240.00,
      "commission_flat_pkr": 50,
      "commission_amount": 50.00,
      "sst_rate": 5,
      "sst_method": "inclusive",
      "sst_amount": 11.43,
      "net": 178.57
    }
  ],
  "totals": {
    "gross": 1140.00,
    "commission": 145.78,
    "sst": 54.29,
    "net": 939.93,
    "count": 3
  },
  "payout_status": {
    "paid_amount": 0,
    "is_fully_paid": false
  },
  "cash_balance": 0,
  "cash_limit": 5000,
  "cash_over_limit": false,
  "outstanding_balance": 105.33,
  "outstanding_limit": 5000,
  "outstanding_over_limit": false
}
```
Taxi: commission_rate (%), commission_flat_pkr null. Parcel: commission_rate null,
commission_flat_pkr set. Food: no commission_rate key, commission_flat_pkr set. Jobs completed
before the flat-PKR change keep commission_rate 20 and commission_flat_pkr null.
cash_balance/cash_limit pair and outstanding_balance/outstanding_limit pair always both
present regardless of driver type.

7. Go online / go offline (new)
Separate from busy/available (POST /api/driver/update-status unchanged).

POST /api/driver/go-online — location optional:

```
{ "latitude": 24.8607, "longitude": 67.0011 }
```
Success (200):

```
{
  "message": "You are online and will receive new jobs.",
  "online_status": "online",
  "uses_online_toggle": true,
  "driver_status": "available",
  "presence": "online",
  "online_status_changed_at": "2026-10-21T09:15:02+00:00",
  "last_location_at": "2026-10-21T09:15:02+00:00",
  "auto_offline_minutes": 10
}
```
Error (403), only while driver verification is switched on:

```
{ "message": "You cannot go online until your verification is approved." }
```
POST /api/driver/go-offline — no body:

```
{ "message": "You are offline and will not receive new jobs.", "online_status": "offline", "uses_online_toggle": true, "driver_status": "available", "presence": "offline", "...": "..." }
```
GET /api/driver/online-status — same payload without message; call on app start to restore the
toggle. presence: online|busy|offline. uses_online_toggle: false = never used the toggle
(online_status still reported "online").

While offline: get-rides → { "rides": [], "online_status": "offline" }; accept-ride → 403
"You are offline. Go online to accept jobs."; no new-job pushes.

Auto-offline: a driver who used the toggle and is online is switched offline after N minutes
without a location update (admin setting, default 10). Push: "You are now offline" (type
driver_status). Keep calling POST /api/driver/location/ping every 15–30s while online (ride_id
optional).

Old builds: never call go-online/go-offline → online_status null → treated as online, offers
as today, auto-offline never touches them.

8. Report a problem on a ride (new)
Drivers can send ride_id to POST /api/submit-complain for a ride they drove (same shape as
Customer App → 10).

Partner App (restaurant staff)

1. Order cancellation — window closes at pickup (changed)
POST /api/restaurant/order/{order_id}/cancel

```
{ "reason": "Kitchen out of stock" }
```
Success (200):

```
{ "message": "Order cancelled successfully", "order": { "...": "..." } }
```
Error (422) already finalized:

```
{ "message": "This order is already finalized and cannot be cancelled." }
```
Error (422) already picked up:

```
{ "message": "This order has already been picked up -- only an admin can cancel it now." }
```

2. Restaurant verification — submit (new)
POST /api/restaurant/verification/submit — multipart/form-data, all fields optional per call

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
Bank-detail change on an already-approved restaurant goes to pending, not live.

Success (200) — missing docs:

```
{
  "message": "Documents saved. Still missing: owner_cnic_front, owner_cnic_back",
  "status": "not_submitted",
  "missing": ["owner_cnic_front", "owner_cnic_back"]
}
```
Success (200) — complete:

```
{
  "message": "Verification submitted successfully.",
  "status": "submitted",
  "missing": []
}
```
Error (404):

```
{ "message": "Restaurant profile not found -- complete your shop details first." }
```

3. Restaurant verification — status (new)
GET /api/restaurant/verification/status

```
{
  "status": "rejected",
  "rejection_reason": "CNIC photo is blurry, please resubmit a clearer copy.",
  "submitted_at": "2026-10-10T09:00:00+00:00",
  "reviewed_at": "2026-10-11T14:30:00+00:00",
  "is_approved": false,
  "pending_bank_change": false
}
```
status: not_submitted|submitted|approved|rejected. Gate "live" UI on is_approved, not status.
pending_bank_change: true → show "bank details pending approval" note.

4. Manage add-ons (new)
All scoped to the logged-in owner's restaurant; anything else → 404.

List — GET /api/restaurant/menu-item/{item_id}/option-groups

```
{ "message": "Option Groups", "item_id": 55, "option_groups": [ { "id": 7, "name": "Size", "is_required": true, "selection_type": "single", "min_select": 1, "max_select": 1, "sort_order": 0, "options": [ { "id": 21, "name": "Regular", "price_adjustment": "0.00", "is_available": true, "sort_order": 0 } ] } ] }
```
Add group — POST /api/restaurant/menu-item/{item_id}/option-group/add (options optional):

```
{
  "name": "Extras",
  "is_required": false,
  "selection_type": "multiple",
  "min_select": 0,
  "max_select": 3,
  "sort_order": 1,
  "options": [
    { "name": "Extra Cheese", "price_adjustment": 80, "is_available": true, "sort_order": 0 },
    { "name": "Jalapeños", "price_adjustment": 50 }
  ]
}
```
Success: { "message": "Option group added successfully", "option_group": { "...": "with options" } }

Update group — POST /api/restaurant/option-group/update/{group_id} (same fields, no options) →
{ "message": "Option group updated successfully", "option_group": {...} }
Delete group — POST /api/restaurant/option-group/delete/{group_id} →
{ "message": "Option group deleted successfully" }
Add option — POST /api/restaurant/option-group/{group_id}/option/add

```
{ "name": "Large", "price_adjustment": 150, "is_available": true, "sort_order": 1 }
```
→ { "message": "Option added successfully", "option": {...} }
Update option — POST /api/restaurant/option/update/{option_id} (same fields) →
{ "message": "Option updated successfully", "option": {...} }
Delete option — POST /api/restaurant/option/delete/{option_id} →
{ "message": "Option deleted successfully" }

Fields: name required; is_required true/false; selection_type single|multiple; min_select ≥ 0,
max_select ≥ 1 or null (multiple only); price_adjustment numeric, can be 0 or negative. Server
normalises: single → min 1 if required (0 if optional), max 1; optional → min 0; required → min
at least 1. Error (400):

```
{ "message": "Minimum selections cannot be more than maximum selections." }
```
Validation failures (400): { "message": "Validation failed", "errors": {...} }. Deleting a group or
option never changes past orders. GET /api/restaurant/menu-items now includes option_groups on
each item.

5. Out-of-stock toggles (new)
Item: POST /api/restaurant/menu-item/{item_id}/toggle-availability
Option: POST /api/restaurant/option/{option_id}/toggle-availability
Body { "is_available": false } to set, or empty to flip.

```
{ "message": "Item marked as out of stock", "item_id": 55, "is_available": "0" }
{ "message": "Option marked as available", "option_id": 23, "is_available": true }
```
Item is_available stays "1"/"0" string; option is a boolean.

6. Incoming orders show chosen add-ons (new fields)
GET /api/restaurant/orders and GET /api/restaurant/order-details/{order_id} — each items[] entry
adds item_name, base_price, selected_options:

```
{
  "id": 990,
  "restaurant_item_id": 55,
  "item_name": "Zinger Burger",
  "quantity": 2,
  "price_per_item": "800.00",
  "base_price": "600.00",
  "total_price": "1600.00",
  "selected_options": [
    { "group_id": 7, "group_name": "Size", "option_id": 22, "option_name": "Large", "price_adjustment": 150 },
    { "group_id": 8, "group_name": "Extras", "option_id": 24, "option_name": "Jalapeños", "price_adjustment": 50 }
  ]
}
```
selected_options null for items without add-ons and for orders placed before this release.

7. Minimum order (new field)
POST /api/restaurant/shop/update — optional minimum_order_amount (number; empty, 0 or null
clears it). Only changed when sent, so a build that doesn't send it never clears it. Response
restaurant and GET /api/restaurant/shop both return minimum_order_amount.


## 8. Statuses aur Fields — Ek Nazar Mein

| Field | Values |
|---|---|
| `payment_status` | `unpaid`, `paid`, `failed`, **`collected`** (naya) |
| order `status` | **`cancelled`** shamil |
| `cancelled_by` | `customer` / `restaurant` / `admin` |
| ride `status` | **`delivery_failed`** shamil (parcel receiver nahi mila); **`no_driver_found`** (naya, setting abhi OFF hai) |
| `delivery_code` | 4-digit code |
| `delivery_code_locked` | `true` = 5 ghalat koshishen, admin unlock karega |
| `offer_expires_at`, `offer_seconds_remaining` | offer countdown |
| `is_approved` | restaurant live hai ya nahi |
| `pending_bank_change` | nayi bank details approval mein |
| `commission_flat_pkr` | parcel/food earnings mein platform ka flat PKR hissa (naya) |
| `online_status` | `online` / `offline` / `null` (null = toggle kabhi use nahi kiya, online samjha jata hai) |
| `presence` | `online` / `busy` / `offline` |
| `has_complaint` | order/ride par complaint pehle se hai ya nahi |
| complaint `service` | `ride` / `parcel` / `food` (server khud set karta hai) |
| `option_groups` | item ke add-ons (`is_required`, `selection_type` single/multiple, `min_select`, `max_select`) |
| option `is_available` | `true` / `false` (boolean); item `is_available` ab bhi `"1"` / `"0"` |
| `selected_options` | cart/order item par chune hue add-ons, order ke waqt ke naam aur price ke saath |
| `minimum_order_amount` | restaurant ka minimum order (food subtotal par); `null` = koi minimum nahi |

## 9. Error Codes jo App Handle Kare

| Code | Kab | Message |
|---|---|---|
| 400 | Address 4 km se door | "...beyond the 4 km ... delivery limit." |
| 400 | Minimum order se kam | "Add Rs X more to reach the minimum order of Rs Y." |
| 400 | Item ya add-on out of stock | "... is currently unavailable." |
| 400 | Add-on rules poore nahi (required choose nahi kiya, zyada choose kiye) | "Please choose an option for ..." / "You can choose up to ..." |
| 400 | Retry jab ride no_driver_found mein nahi | "This ride is not waiting for a retry." |
| 403 | Cash ya outstanding limit cross | "...balance is over the limit..." |
| 403 | 5 ghalat delivery codes | "Too many incorrect codes..." |
| 403 | Baghair offer ke accept (legacy OFF hone ke baad) | "You were not offered this ride." |
| 403 | Offline driver/rider ne accept kiya | "You are offline. Go online to accept jobs." |
| 403 | Kisi aur ki ride cancel/retry | "You are not authorized to cancel this ride." / "...retry this ride." |
| 410 | Expire offer accept kiya | "This offer has expired." |
| 422 | Ghalat delivery code | "Incorrect delivery code. X attempt(s) remaining." |
| 422 | COD par cash amount nahi bheja | "cash_collected_amount is required..." |
| 422 | Order cancel ka waqt guzar gaya | "...can no longer be cancelled..." |
| 422 | Complaint mein kisi aur ka order/ride | "This order was not found on your account." / "This ride was not found on your account." |

## 10. Mobile Dev ke liye Checklist

**Customer App**
- [ ] Checkout par delivery fee (food + parcel quote APIs)
- [ ] Cart total = server ka `total_price`
- [ ] Order cancel button (accept se pehle tak)
- [ ] Cancel notification / screen
- [ ] Parcel booking form + price pehle dikhana
- [ ] Delivery code tracking screen par
- [ ] Sirf COD option
- [ ] 4 km se door ka message
- [ ] Order/ride par "Report a problem" button; `has_complaint` true ho to "Problem reported"
- [ ] Item add-ons sheet (required/optional, single/multiple, out of stock greyed out, live price)
- [ ] Cart aur order detail mein chune hue add-ons dikhana
- [ ] Restaurant page par minimum order; cart mein `minimum_order_message`, poora hone tak checkout band
- [ ] Place order ke naye 400 messages (out of stock, minimum order) as-is dikhana
- [ ] "No drivers available right now" screen — Try again + Cancel (abhi active nahi, setting OFF)

**Delivery App**
- [ ] Offer countdown + 410 handle
- [ ] Accept se pehle hamesha poll
- [ ] Delivery code daalnay ki screen + lock message
- [ ] Delivered ke saath cash amount
- [ ] Parcel: sender pays → pickup par cash; receiver pays → drop-off par
- [ ] "Receiver nahi mila" alag button + photo
- [ ] `private-driver.{id}` channel subscribe — cancel par job saaf karna
- [ ] Cash limit message
- [ ] Earnings screen + cash balance (`commission_flat_pkr` parhna)
- [ ] Online / Offline toggle; app start par `online-status` se restore
- [ ] Online rehte hue location ping chalu rakhna; "You are now offline" push par toggle off

**Driver App (Taxi)**
- [ ] Offer countdown + 410 handle
- [ ] Accept se pehle hamesha poll
- [ ] Outstanding balance limit message
- [ ] Earnings screen + outstanding balance
- [ ] Online / Offline toggle; app start par `online-status` se restore
- [ ] Online rehte hue location ping chalu rakhna; "You are now offline" push par toggle off

**Partner App**
- [ ] Order cancel (pickup se pehle tak)
- [ ] Verification form (partial save)
- [ ] Verification status screen (`is_approved` par live)
- [ ] Bank details pending note
- [ ] Har item ke add-ons manage karna (groups + options, required / single-multiple / min / max)
- [ ] Item aur har option par quick "Out of stock" switch
- [ ] Incoming order card par har item ke neeche chune hue add-ons
- [ ] Shop settings mein "Minimum order" field
