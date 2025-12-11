<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'car_brand_id' => 6, // BMW
                'title' => 'BMW X5',
                'car_model' => 'X5',
                'car_fuel_type' => 'Diesel',
                'year' => '2020',
                'price_per_hour' => 20.00,
                'price_per_day' => 150.00,
                'price_per_week' => 900.00,
                'price_per_month' => 3000.00,
                'transmission' => 'automatic',
                'color' => 'Black',
                'seats' => 5,
                'main_image' => 'uploads/vehicles/bmw-x5.png',
                'address' => '123 Main St, Cityville',
                'description' => 'A luxurious and spacious SUV perfect for family trips.',
                'is_featured' => '1',
                'is_active' => 'active',
            ],
            [
                'car_brand_id' => 23, // Ferrari
                'title' => 'Ferrari F8 Tributo',
                'car_model' => 'F8 Tributo',
                'car_fuel_type' => 'Petrol',
                'year' => '2021',
                'price_per_hour' => 100.00,
                'price_per_day' => 700.00,
                'price_per_week' => 4000.00,
                'price_per_month' => 12000.00,
                'transmission' => 'automatic',
                'color' => 'Red',
                'seats' => 2,
                'main_image' => 'uploads/vehicles/ferrari-f8.png',
                'address' => '456 Luxury Ave, Richville',
                'description' => 'Experience the thrill of driving a high-performance sports car.',
                'is_featured' => '1',
                'is_active' => 'active',
            ],
            [
                'car_brand_id' => 73, // Tesla
                'title' => 'Tesla Model 3',
                'car_model' => 'Model 3',
                'car_fuel_type' => 'Electric',
                'year' => '2022',
                'price_per_hour' => 30.00,
                'price_per_day' => 200.00,
                'price_per_week' => 1200.00,
                'price_per_month' => 4000.00,
                'transmission' => 'automatic',
                'color' => 'White',
                'seats' => 5,
                'main_image' => 'uploads/vehicles/tesla-model3.png',
                'address' => '789 Green Rd, Ecotown',
                'description' => 'A sleek and efficient electric car with cutting-edge technology.',
                'is_featured' => '1',
                'is_active' => 'active',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
