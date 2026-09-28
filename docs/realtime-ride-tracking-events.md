# Realtime Ride Tracking — Event Contract (for Flutter dev)

Backend is on Laravel broadcasting over a self-hosted **Soketi** (Pusher-protocol) server. Use any Pusher-protocol client (`pusher_channels_flutter` or laravel_echo-equivalent) — same auth token as REST.

## Auth

Broadcasting auth endpoint: `POST /api/broadcasting/auth` (mobile-specific route, separate from the web session-based one).
Send your existing Sanctum bearer token on this request exactly like any other API call — the server resolves the user from it and authorizes the channel.

## Channels

| Channel | Who can subscribe | What it carries |
|---|---|---|
| `private-ride.{rideId}` | The ride's passenger, its assigned driver, and admin/dispatcher roles | `driver.location`, `ride.status`, `ride.progress`, `ride.route`, `nearby.drivers` |
| `private-driver.{driverId}` | That driver only | `ride.requested`, `ride.unavailable` |

Subscribe to `private-ride.{rideId}` as soon as a ride exists (right after requesting a ride, or right after a driver accepts one) — `nearby.drivers` and everything else all arrive on this one channel.

## Events

### `ride.requested` — on `private-driver.{driverId}`
Fired once per matching nearby driver when a customer requests a ride.

```json
{
  "ride_id": 8812,
  "vehicle_type_id": 4,
  "pickup": { "latitude": "24.86071", "longitude": "67.00114" },
  "dropoff": { "latitude": "24.89120", "longitude": "67.07230" },
  "distance_km": 9.40,
  "duration_minutes": 15,
  "total_fare": 480.0,
  "requested_at": "2026-09-28T10:15:00+05:00"
}
```
Show the accept popup. No `accept_deadline` field currently — if you need one, ask backend to add it (trivial addition).

### `ride.unavailable` — on `private-driver.{driverId}`
Dismiss the accept popup for this `ride_id` — either another driver accepted it, or the rider cancelled before anyone accepted.

```json
{ "ride_id": 8812 }
```

### `ride.status` — on `private-ride.{rideId}`
Fired on every status transition. Full ride snapshot each time (not a diff).

```json
{
  "ride_id": 8812,
  "passenger_id": 6,
  "driver_id": 1,
  "vehicle_type_id": 4,
  "pickup": { "latitude": "24.86071", "longitude": "67.00114" },
  "dropoff": { "latitude": "24.89120", "longitude": "67.07230" },
  "distance_km": 9.40,
  "duration_minutes": 15,
  "total_fare": 480.0,
  "status": "arrived",
  "ride_type": "ride",
  "cancelled_by": null,
  "cancel_reason": null,
  "free_wait_until": 1759049460,
  "driver": {
    "id": 55,
    "name": "Imran",
    "phone_masked": "+92 3xx xxx x521",
    "vehicle": { "make": "Suzuki", "model": "Alto", "color": "White", "plate": "ABC-123" }
  },
  "requested_at": "2026-09-28T10:15:00+05:00",
  "accepted_at": "2026-09-28T10:16:10+05:00",
  "arrived_at": "2026-09-28T10:21:40+05:00",
  "started_at": null,
  "completed_at": null
}
```

**Status values** (this app's actual enum — not the brief's illustrative one): `requested → accepted → en_route → arrived → started → completed`, or `cancelled` at any point before `completed`.

- `driver` is `null` until a driver is assigned (status `requested`), then populated on every event from `accepted` onward.
- `free_wait_until` (Unix seconds) is only set when `status = "arrived"` — count down from it in the UI; `null` otherwise. Free-wait window is currently a flat 5 minutes.
- `cancelled_by` is `"passenger"`, `"driver"`, or `"admin"` — only set when `status = "cancelled"`.
- No live/metered fare — `total_fare` is fixed and agreed upfront; it does not change during the ride.

### `driver.location` — on `private-ride.{rideId}`
Fired on every driver GPS ping while a ride is active (target cadence: every 3-5s en route, but only as fast as the driver app actually sends pings — see the ingest endpoint below).

```json
{
  "ride_id": 8812,
  "lat": 24.86071,
  "lng": 67.00114,
  "heading": 142,
  "speed_kmh": 34,
  "ts": 1759049280
}
```
`heading`/`speed_kmh` are `null` if the driver app didn't send them (both optional on ingest).

### `ride.progress` — on `private-ride.{rideId}`
Fired at most every ~20s while status is `en_route` or `started`.

```json
{
  "ride_id": 8812,
  "phase": "to_pickup",
  "eta_min": 6,
  "remaining_km": 2.4,
  "travelled_km": 0.0,
  "elapsed_min": 0,
  "live_fare": null,
  "updated_at": 1759049280
}
```
- `phase`: `"to_pickup"` (status `en_route`) or `"to_dropoff"` (status `started`).
- `travelled_km`/`elapsed_min` are only meaningful for `to_dropoff` (both `0` during `to_pickup`).
- `live_fare` is always `null` — no metered billing in this app.

### `ride.route` — on `private-ride.{rideId}`
Fired once on accept (`to_pickup` leg) and once on trip start (`to_dropoff` leg). Not sent continuously.

```json
{
  "ride_id": 8812,
  "phase": "to_pickup",
  "polyline": "krvvC{c}wKKIfAe@z@e@tEsB~BsAfA...",
  "reason": "accept"
}
```
`polyline` is a **Google-encoded polyline** (standard algorithm — same format `flutter_polyline_points`/`google_maps_flutter` decode natively). `reason` is `"accept"` or `"start"`. No mid-ride reroute events yet (not built in this pass).

### `nearby.drivers` — on `private-ride.{rideId}`
Fired while the ride's status is `requested` (searching), roughly every ~8-10s, driven by nearby drivers' idle pings.

```json
{
  "drivers": [
    { "lat": 24.861, "lng": 67.002 }
  ],
  "nearest_eta_min": 4
}
```
No driver ids/names, coordinates rounded to ~100m, capped at 10 points, by design (privacy). `nearest_eta_min` is a straight-line/avg-speed estimate, not routed.

## REST fallback

### `GET /api/rides/{id}/live`
Call this on reconnect/app-resume, or whenever the socket is disconnected, to resync state. Authorized for the ride's passenger or assigned driver only (Sanctum bearer token).

```json
{
  "status": "started",
  "driver_location": { "lat": 24.86071, "lng": 67.00114, "heading": 142, "speed_kmh": 34, "ts": 1759049280 },
  "pickup": { "latitude": "24.86071", "longitude": "67.00114" },
  "dropoff": { "latitude": "24.89120", "longitude": "67.07230" },
  "distance_km": 9.40,
  "duration_minutes": 15,
  "total_fare": 480.0,
  "driver": { "id": 55, "name": "Imran" }
}
```

## What the driver app must send

### `POST /api/driver/location/ping` (Sanctum bearer token)

**During an active ride** (status `en_route` or `started`):
```json
{
  "ride_id": 8812,
  "latitude": 24.86071,
  "longitude": 67.00114,
  "heading": 142,
  "speed_kmh": 34
}
```
Send every **3-5 seconds** while actively driving to/from the passenger. `heading`/`speed_kmh` are optional but strongly recommended (used for `driver.location`'s payload and marker rotation).

**While online and idle** (available for rides, not currently on one) — omit `ride_id`:
```json
{
  "latitude": 24.86071,
  "longitude": 67.00114
}
```
Send every **10-15 seconds**. This is new — previously the driver app never sent a location update unless on an active ride, so "available driver" positions used by matching and `nearby.drivers` were stale from login time. **This is the one required behavior change for the driver app.**

Response is always `200 { "message": "Location updated successfully." }` or a validation/403 error — no other data comes back from this endpoint; everything else arrives over the socket.

### Rider app
No new endpoints to call. Just subscribe to `private-ride.{rideId}` once a ride is requested, and listen for the events above.

## Known gaps / not built in this pass
- Mid-ride reroute detection (`ride.route` with `reason: "reroute"`) isn't implemented — `ride.route` only fires on accept/start.
- `ride.completed` isn't a separate event — the final state comes through `ride.status` with `status: "completed"` (no fare breakdown object; `total_fare` is already fixed and known from earlier events).
- No `accept_deadline` on `ride.requested` yet.
- No explicit ride state-machine validation (invalid transitions aren't rejected with a 409) — out of scope for this pass, ask if this becomes a real problem.
