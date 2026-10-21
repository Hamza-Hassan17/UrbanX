# Mobile Handoff — Batch 3 (Parts 1–6)

Every payload below is taken from the actual `Validator::make` rules and `response()->json()` calls in the
controllers. All routes are under `/api` with the existing bearer token. Money values are PKR. Decimal
columns (prices, amounts) come back as JSON strings like `"500.00"`, the same as every existing price field.

Everything in this batch is **additive**: new endpoints, new optional request fields, new response fields.
Already-shipped builds keep working without changes, with the few behaviour changes listed next.

---

## ⚠️ Breaking behaviour changes

None of these break an already-shipped build's normal use, but each one changes something an old build
can now see.

### 1. Customer — `place-order` can reject a cart that was fine when it was built

`POST /api/restaurant/customer/place-order` now re-checks every cart line against the live menu and the new
minimum order, and can return **HTTP 400** with one of these messages (all customer-readable, show as-is):

```json
{ "message": "Chicken Burger is currently unavailable.", "cart_item_id": 812 }
{ "message": "\"Extra Cheese\" is currently unavailable. Please choose another option.", "cart_item_id": 812 }
{ "message": "An item in your cart is no longer on the menu. Please remove it and try again." }
{ "message": "Add Rs 150 more to reach the minimum order of Rs 500.", "minimum_order_amount": 500, "food_subtotal": 350, "shortfall": 150 }
```

The order total is also recomputed from current menu prices. If the restaurant changed a price after the
item was added to the cart, the order is charged the current price, not the carted one.

### 2. Customer — `cancel-ride` now checks ownership

`POST /api/customer/cancel-ride` had its "is this your ride" check commented out, so any logged-in user could
cancel any ride. It is enforced again:

```json
{ "message": "You are not authorized to cancel this ride." }
```
**HTTP 403.** A customer cancelling their own ride or parcel is unaffected.

### 3. Delivery — food delivery fare is now the server's delivery fee

The `rides` row created for a food order used to copy `subtotal`/`total_fare` from what the Customer app sent
to `place-order`. It now uses the server-computed delivery fee (the same value as `order.delivery_fee`). If
the Delivery app shows the ride's `total_fare` as the rider's fare, it now always matches the order's
delivery fee.

### 4. Customer — a ride/parcel can come back as `no_driver_found`, but only once the setting is On

The new status (Part 6) only ever appears when an admin switches **No-driver handling** to On. It is **Off**
by default and stays off until the client confirms, so no build sees it today. See Customer §5 before it's
switched on.

### 5. Driver / Delivery — `get-rides` can return `online_status: "offline"`

Only for a driver who used the new **go offline** endpoint. Drivers on old builds never call it, stay
`online_status = null`, and keep receiving offers exactly as today (see Driver §1).

---

## Customer App

### 1. Report a problem on an order or ride (Part 1)

`POST /api/submit-complain` — existing endpoint, two new optional fields. Send **one** of `ride_id` or
`restaurant_order_id` (both → validation error). `service` is then set by the server from the record; any
`service` you send is ignored when a link is given.

```json
{
  "name": "Ali Khan",
  "subject": "Rider was rude",
  "complain_text": "The rider shouted at me at the gate.",
  "restaurant_order_id": 345
}
```

Success:
```json
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

Errors: `422` `{ "message": "This order was not found on your account." }` /
`{ "message": "This ride was not found on your account." }`; `400` validation (`errors.ride_id`:
"The ride id field prohibits restaurant order id from being present.").

`service` values: `ride` (taxi), `parcel`, `food` (a food order, or the delivery ride of a food order).
A general complaint without a link still works exactly as before.

**`has_complaint` (new boolean)** on:
- `GET /api/customer/get-ride-history` → each `ride_history[]` item
- `GET /api/restaurant/customer/orders` → each `orders[]` item
- `GET /api/restaurant/customer/order-details/{order_id}` → `order`

**Screen:** "Report a problem" button on order detail and ride/parcel history detail. When
`has_complaint` is true, show "Problem reported" (link to the complaints list) instead of the button.

### 2. Item add-ons and out-of-stock (Part 2)

**Menu responses** — `GET /api/restaurant/customer/menu-items/{menu_id}` and
`GET /api/restaurant/customer/menu-item-details/{item_id}` — each item now has `option_groups` (empty array
when the item has none):

```json
{
  "id": 55,
  "name": "Zinger Burger",
  "price": "650.00",
  "discount_price": "600.00",
  "is_available": "1",
  "option_groups": [
    {
      "id": 7,
      "restaurant_item_id": 55,
      "name": "Size",
      "is_required": true,
      "selection_type": "single",
      "min_select": 1,
      "max_select": 1,
      "sort_order": 0,
      "options": [
        { "id": 21, "option_group_id": 7, "name": "Regular", "price_adjustment": "0.00", "is_available": true, "sort_order": 0 },
        { "id": 22, "option_group_id": 7, "name": "Large", "price_adjustment": "150.00", "is_available": true, "sort_order": 1 }
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

Rules the app should mirror (the server enforces them anyway):
- Item `is_available` is the string `"1"` / `"0"` (unchanged, as before). Show `"0"` items as "Out of stock",
  not addable.
- Option `is_available` is a real boolean. Show unavailable options greyed out, not selectable.
- `single`: pick exactly one if `is_required`, at most one if optional.
- `multiple`: between `min_select` and `max_select` (`max_select: null` = no limit). A required group always
  needs at least one.
- Line price = item base price (`discount_price`, else `price`) + sum of chosen `price_adjustment`.

**Add to cart** — `POST /api/restaurant/customer/add-to-cart`, new optional field:
```json
{ "restaurant_id": 4, "menu_item_id": 55, "quantity": 2, "option_ids": [22, 24] }
```
Omit `option_ids` for items with no groups (old builds keep working). The same item with the same choices
merges into one cart line; different choices become separate lines.

New errors (**HTTP 400**, show the message):
```json
{ "message": "Please choose an option for \"Size\"." }
{ "message": "Please choose at least 2 options for \"Sauces\"." }
{ "message": "You can choose up to 3 options for \"Extras\"." }
{ "message": "You can choose only one option for \"Size\"." }
{ "message": "\"Extra Cheese\" is currently unavailable. Please choose another option." }
{ "message": "Zinger Burger is currently unavailable." }
{ "message": "Zinger Burger has no add-ons to choose from." }
{ "message": "One of the selected add-ons does not belong to Zinger Burger. Please reopen the item and choose again." }
```

**Cart responses** (`carts`, `cart-details`) — each cart item now has:
```json
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
`selected_options` is `null` for items without add-ons. `price_per_item` already includes the add-ons.

**Order responses** (`place-order`, `orders`, `order-details`) — each `items[]` entry now has `item_name`,
`base_price` and `selected_options` (same shape as above), frozen at order time. Show these, not the live
menu, on order history.

**Screens:** item options bottom sheet (groups, required/optional label, single = radio, multiple =
checkboxes with "choose up to N", greyed-out unavailable options, live price); cart line shows the chosen
options under the item name; order detail shows them too.

### 3. Minimum order (Part 3)

- Restaurant listing, search, home and `restaurant-details` responses include
  `"minimum_order_amount": "500.00"` (or `null` = no minimum).
- `GET /api/restaurant/customer/carts` → each cart has `minimum_order_amount`.
- `GET /api/restaurant/customer/cart-details/{cart_id}` → `cart.minimum_order_amount` and
  `cart.minimum_order_message` (`null` when the minimum is met, else e.g.
  `"Add Rs 150 more to reach the minimum order of Rs 500."`).
- The minimum is on the **food subtotal**, before delivery fee and discounts.
- `place-order` below the minimum → **HTTP 400** with the message and numbers shown in Breaking §1.

**Screen:** show "Minimum order Rs 500" on the restaurant page; in the cart show `minimum_order_message` and
disable checkout until it is null.

### 4. Complaint flag on history

See §1 (`has_complaint`).

### 5. No driver found — ⛔ NOT YET ACTIVE (switched off until the client confirms)

Build the screen, but it only becomes reachable when an admin turns **No-driver handling** On.

When On, a taxi ride or parcel nobody accepts (max re-offer attempts reached, or no eligible driver left)
gets `status: "no_driver_found"`:
- `ride.status.updated` on the existing private `ride.{id}` channel with `"status": "no_driver_found"`.
- Push: title **"No drivers available right now"**, body "We could not find a driver for your ride. You can
  try again or cancel." (`parcel` instead of `ride` for parcels), type `rides`, id = ride id.

Retry — `POST /api/customer/retry-dispatch`:
```json
{ "ride_id": 1204 }
```
```json
{ "message": "Looking for a driver again.", "ride_id": 1204, "status": "requested", "offered_to_driver": true }
```
Errors: `403` `{ "message": "You are not authorized to retry this ride." }`; `400`
`{ "message": "This ride is not waiting for a retry." }`.

Cancel — existing `POST /api/customer/cancel-ride` with `ride_id` works on `no_driver_found`.

Food orders never get this status.

**Screen:** "No drivers available right now" with **Try again** (retry-dispatch, then back to the searching
screen) and **Cancel** (cancel-ride).

---

## Driver App (taxi)

### 1. Go online / go offline (Part 4)

Online is separate from the existing busy/available `driver_status` (`POST /api/driver/update-status` is
unchanged).

`POST /api/driver/go-online` — optional location so the first offer can be matched by distance:
```json
{ "latitude": 24.8607, "longitude": 67.0011 }
```
```json
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
Error `403` `{ "message": "You cannot go online until your verification is approved." }` (only while
driver verification is switched on).

`POST /api/driver/go-offline` (no body):
```json
{ "message": "You are offline and will not receive new jobs.", "online_status": "offline", "uses_online_toggle": true, "driver_status": "available", "presence": "offline", "...": "..." }
```

`GET /api/driver/online-status` → the same payload without `message`. Call it on app start to restore the
toggle.

- `presence` is `online` / `busy` / `offline`.
- `uses_online_toggle: false` means this driver has never used the toggle; `online_status` is still reported
  as `"online"`.

While offline:
- `GET /api/driver/get-rides` → `{ "rides": [], "online_status": "offline" }`
- `POST /api/driver/accept-ride` → **403** `{ "message": "You are offline. Go online to accept jobs." }`
- No new-job pushes.

**Auto-offline:** a driver who used the toggle and is online is switched offline after
**N minutes without a location update** (admin setting, default 10). They get a push titled
**"You are now offline"** (type `driver_status`). Keep sending `POST /api/driver/location/ping` (every
15–30s, with or without `ride_id`) while online so this doesn't trigger.

**Old builds:** a driver who never calls go-online/go-offline has `online_status = null`, is treated as online,
and receives offers exactly as before. Auto-offline never touches them.

**Screen:** Online / Offline toggle on the home screen; when offline show "You're offline — go online to
receive jobs"; handle the auto-offline push by flipping the toggle off.

### 2. Complaints

Drivers can also send `ride_id` to `POST /api/submit-complain` for a ride they drove (same shape as Customer §1).

---

## Delivery App

### 1. Go online / go offline (Part 4)

Same as Driver §1, under the delivery prefix:
- `POST /api/restaurant/delivery/go-online`
- `POST /api/restaurant/delivery/go-offline`
- `GET /api/restaurant/delivery/online-status`

(The `/api/driver/...` versions work too: riders are driver accounts.)

While offline:
- `GET /api/restaurant/delivery/get-rides` → `{ "rides": [], "online_status": "offline" }`
- `POST /api/restaurant/delivery/accept-ride` → **403** `{ "message": "You are offline. Go online to accept jobs." }`

`POST /api/restaurant/delivery/update-location` (existing) now also counts as the location update for
auto-offline. Keep calling it during a delivery; between deliveries use `POST /api/driver/location/ping`
without `ride_id`.

**Screen:** Online / Offline toggle, same behaviour as the Driver app.

### 2. Fare shown for food deliveries

See Breaking §3.

---

## Partner App (restaurant staff)

### 1. Manage add-ons (Part 2)

All scoped to the logged-in owner's restaurant; anything else returns `404`.

**List** — `GET /api/restaurant/menu-item/{item_id}/option-groups`
```json
{ "message": "Option Groups", "item_id": 55, "option_groups": [ { "id": 7, "name": "Size", "is_required": true, "selection_type": "single", "min_select": 1, "max_select": 1, "sort_order": 0, "options": [ { "id": 21, "name": "Regular", "price_adjustment": "0.00", "is_available": true, "sort_order": 0 } ] } ] }
```

**Add group** — `POST /api/restaurant/menu-item/{item_id}/option-group/add` (options optional, can be added later):
```json
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
→ `{ "message": "Option group added successfully", "option_group": { ...with options } }`

**Update group** — `POST /api/restaurant/option-group/update/{group_id}` (same fields minus `options`)
→ `{ "message": "Option group updated successfully", "option_group": {...} }`

**Delete group** — `POST /api/restaurant/option-group/delete/{group_id}` → `{ "message": "Option group deleted successfully" }`

**Add option** — `POST /api/restaurant/option-group/{group_id}/option/add`
```json
{ "name": "Large", "price_adjustment": 150, "is_available": true, "sort_order": 1 }
```
→ `{ "message": "Option added successfully", "option": {...} }`

**Update option** — `POST /api/restaurant/option/update/{option_id}` (same fields) →
`{ "message": "Option updated successfully", "option": {...} }`

**Delete option** — `POST /api/restaurant/option/delete/{option_id}` → `{ "message": "Option deleted successfully" }`

Field rules:
- `name` required.
- `is_required` true/false.
- `selection_type` `single` | `multiple`.
- `min_select` ≥ 0 and `max_select` ≥ 1 or null (multiple only).
- `price_adjustment` numeric, can be negative or 0.

The server normalises the group:
- `single` → min 1 if required (0 if optional), max 1.
- Optional → min 0.
- Required → min at least 1.

Contradictory min/max → **400** `{ "message": "Minimum selections cannot be more than maximum selections." }`.
Validation failures → **400** `{ "message": "Validation failed", "errors": {...} }`.

Deleting a group or option never changes past orders; they keep their own copy of what was chosen.

`GET /api/restaurant/menu-items` now includes `option_groups` on each item.

### 2. Out-of-stock toggles (Part 2)

- Item: `POST /api/restaurant/menu-item/{item_id}/toggle-availability`
- Option: `POST /api/restaurant/option/{option_id}/toggle-availability`

Send `{ "is_available": false }` to set it, or an empty body to flip it.
```json
{ "message": "Item marked as out of stock", "item_id": 55, "is_available": "0" }
{ "message": "Option marked as available", "option_id": 23, "is_available": true }
```
(Item `is_available` stays the `"1"`/`"0"` string used everywhere else for items; option is a boolean.)

### 3. Incoming orders show chosen options (Part 2)

`GET /api/restaurant/orders` and `GET /api/restaurant/order-details/{order_id}` → each `items[]` entry now has
`item_name`, `base_price` and `selected_options`:
```json
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
`selected_options` is `null` for items without add-ons, and for orders placed before this release.

### 4. Minimum order (Part 3)

`POST /api/restaurant/shop/update` — new optional field `minimum_order_amount` (number; empty, `0` or
`null` clears it). Only changed when sent, so a build that doesn't send it never clears an existing minimum.
The response's `restaurant` now includes `minimum_order_amount`; `GET /api/restaurant/shop` returns it too.

**Screens:** add-on management per item (groups list, add/edit group form with required / single-multiple /
min / max, options list with price and availability switch); quick "Out of stock" switch on the item list
and on each option; incoming-order card shows options under each item; "Minimum order" field on the shop
settings screen.

---

## New statuses and settings reference

| What | Values | Notes |
|---|---|---|
| `rides.status` | adds `no_driver_found` | Only while No-driver handling is On (Off by default). |
| `users.online_status` | `online` / `offline` / `null` | `null` = never used the toggle → treated as online. |
| `presence` (online-status payload) | `online` / `busy` / `offline` | `busy` comes from existing `driver_status`. |
| `complains.service` | `ride` / `rental` / `food` / `parcel` | Set from the linked record. `rental` can't be linked yet (chauffeur bookings aren't rides), only sent as before. |
| Dispatch setting "Auto-offline after N minutes" | default 10 | Admin → Pricing & Fees → Dispatch. |
| Dispatch setting "No-driver handling" | Off (default) / On | **Switched off until the client confirms.** |
