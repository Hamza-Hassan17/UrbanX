<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 20px; }
        h2 { font-size: 14px; margin: 20px 0 8px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; width: 160px; }
        .images { margin-top: 6px; }
        .images img { width: 160px; margin: 0 8px 8px 0; border: 1px solid #d1d5db; }
        .muted { color: #9ca3af; }
    </style>
</head>
<body>
    <h1>KYC Documents — {{ $driver->name }}</h1>
    <p class="subtitle">Driver ID: {{ $driver->id }} &middot; Generated {{ now()->format('M d, Y H:i') }}</p>

    <h2>Vehicle</h2>
    @if ($driver->driverVehicle)
        <table>
            <tr><th>Vehicle Type</th><td>{{ $driver->driverVehicle->vehicleType->name ?? '—' }}</td></tr>
            <tr><th>Vehicle Name</th><td>{{ $driver->driverVehicle->vehicle_name ?? '—' }}</td></tr>
            <tr><th>Make / Model</th><td>{{ $driver->driverVehicle->vehicle_make ?? '—' }} {{ $driver->driverVehicle->vehicle_model ?? '' }}</td></tr>
            <tr><th>Plate Number</th><td>{{ $driver->driverVehicle->vehicle_plate_number ?? '—' }}</td></tr>
        </table>
        @php $vehicleImages = $driver->driverVehicle->vehicle_images ? json_decode($driver->driverVehicle->vehicle_images, true) : []; @endphp
        @if ($vehicleImages)
            <div class="images">
                @foreach ($vehicleImages as $image)
                    <img src="{{ public_path('storage/' . $image) }}">
                @endforeach
            </div>
        @endif
        @if ($driver->driverVehicle->registration_paper)
            <p><strong>Registration Paper</strong></p>
            <div class="images"><img src="{{ public_path('storage/' . $driver->driverVehicle->registration_paper) }}"></div>
        @endif
    @else
        <p class="muted">No vehicle details submitted.</p>
    @endif

    <h2>License</h2>
    @if ($driver->driverLicense)
        <table>
            <tr><th>Name</th><td>{{ $driver->driverLicense->name }}</td></tr>
            <tr><th>License Number</th><td>{{ $driver->driverLicense->license_number }}</td></tr>
            <tr><th>Address</th><td>{{ $driver->driverLicense->address }}</td></tr>
        </table>
        <div class="images">
            <img src="{{ public_path('storage/' . $driver->driverLicense->front_picture) }}">
            <img src="{{ public_path('storage/' . $driver->driverLicense->back_picture) }}">
        </div>
    @else
        <p class="muted">No license submitted.</p>
    @endif

    <h2>CNIC</h2>
    @if ($driver->driverCnic)
        <table>
            <tr><th>Name</th><td>{{ $driver->driverCnic->name }}</td></tr>
            <tr><th>CNIC Number</th><td>{{ $driver->driverCnic->cnic_number }}</td></tr>
        </table>
        <div class="images">
            <img src="{{ public_path('storage/' . $driver->driverCnic->front_picture) }}">
            <img src="{{ public_path('storage/' . $driver->driverCnic->back_picture) }}">
        </div>
    @else
        <p class="muted">No CNIC submitted.</p>
    @endif

    <h2>Selfie</h2>
    @if ($driver->driverSelfie)
        <div class="images"><img src="{{ public_path('storage/' . $driver->driverSelfie->picture) }}"></div>
    @else
        <p class="muted">No selfie submitted.</p>
    @endif
</body>
</html>
