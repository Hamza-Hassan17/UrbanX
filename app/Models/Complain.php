<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'complain_text',
        'status',
        'service',
        'ride_id',
        'restaurant_order_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function restaurantOrder()
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    /**
     * Ride IDs (from $rideIds) that already have a complaint from $userId,
     * either directly or through a food order delivered by that ride.
     */
    public static function rideIdsWithComplaint(int $userId, array $rideIds): array
    {
        if (empty($rideIds)) {
            return [];
        }

        return static::where('user_id', $userId)
            ->whereIn('ride_id', $rideIds)
            ->pluck('ride_id')
            ->unique()
            ->values()
            ->all();
    }

    public static function orderIdsWithComplaint(int $userId, array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        return static::where('user_id', $userId)
            ->whereIn('restaurant_order_id', $orderIds)
            ->pluck('restaurant_order_id')
            ->unique()
            ->values()
            ->all();
    }
}
