<?php

namespace Database\Seeders;

use App\Models\RestaurantCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RestaurantCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Popular
            'Fast Food',
            'Pakistani',
            'Chinese',
            'Italian',
            'BBQ',
            'Biryani',
            'Burgers',
            'Pizza',
            'Sandwiches',
            'Shawarma',
            'Rolls & Paratha Rolls',

            // Rice & Traditional
            'Karahi & Handi',
            'Pulao',
            'Haleem',
            'Nihari',
            'Qorma',
            'Tandoor',
            'Chapli Kabab',
            'Seafood',

            // International
            'American',
            'Turkish',
            'Arabian',
            'Thai',
            'Japanese',
            'Korean',
            'Mexican',
            'Indian',
            'Mediterranean',

            // Snacks & Street Food
            'Chaat',
            'Samosa & Pakora',
            'Fries',
            'Loaded Fries',
            'Hot Dogs',
            'Wraps',
            'Panini',

            // Healthy & Special
            'Healthy Food',
            'Salads',
            'Vegan',
            'Keto',
            'Organic',
            'Gluten Free',

            // Desserts
            'Desserts',
            'Cakes',
            'Cupcakes',
            'Brownies',
            'Donuts',
            'Ice Cream',
            'Waffles',
            'Pancakes',
            'Milkshakes',

            // Beverages
            'Beverages',
            'Juices',
            'Smoothies',
            'Tea',
            'Coffee',
            'Cold Coffee',
            'Mocktails',

            // Breakfast
            'Breakfast',
            'Brunch',
            'Halwa Puri',
            'Omelettes',

            // Others
            'Deals & Combos',
            'Family Deals',
            'Kids Menu',
            'Late Night',
            'Fine Dining',
            'Home Based',
            'Cloud Kitchen',
        ];

        foreach ($categories as $category) {
            RestaurantCategory::create(['name' => $category]);
        }
    }
}
