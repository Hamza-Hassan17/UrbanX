<?php

use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\LogoutController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\Frontend\ChauffersController;
use App\Http\Controllers\API\Frontend\ComplainController;
use App\Http\Controllers\API\Frontend\Customer\RideController;
use App\Http\Controllers\API\Frontend\Driver\RideController as DriverRideController;
use App\Http\Controllers\API\Frontend\DriverDetailsController;
use App\Http\Controllers\API\Frontend\NotificationController;
use App\Http\Controllers\API\Frontend\ProfileController;
use App\Http\Controllers\API\Frontend\Restaurant\RestaurantController;
use App\Http\Controllers\Dashboard\CustomRideController;
use App\Http\Controllers\Dashboard\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [LogoutController::class, 'logout']);

    //Resent OTP API
    Route::get('/resend-otp', [LoginController::class, 'resend_otp']);
    Route::post('/otp-verification', [LoginController::class, 'verify_otp']);

    //Notifications API
    Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);

    //Profile API
    Route::get('/profile', [ProfileController::class, 'getUserProfile']);
    Route::post('/profile/update', [ProfileController::class, 'updateUserProfile']);
    Route::post('/password/update', [ProfileController::class, 'updateUserPassword']);

    //Complain API
    Route::get('/get-complains', [ComplainController::class, 'getComplains']);
    Route::post('/submit-complain', [ComplainController::class, 'submitComplain']);

    //Driver Routes
    Route::group(['prefix' => 'driver'], function () {
        //Driver Vehicle
        Route::get('/vehicle', [DriverDetailsController::class, 'getVehicleDetails']);
        Route::post('/vehicle/update', [DriverDetailsController::class, 'updateVehicleDetails']);

        //Driver License
        Route::get('/license', [DriverDetailsController::class, 'getLicenseDetails']);
        Route::post('/license/update', [DriverDetailsController::class, 'updateLicenseDetails']);

        //Driver Personal Information
        Route::get('/personal-information', [DriverDetailsController::class, 'getPersonalInformation']);
        Route::post('/personal-information/update', [DriverDetailsController::class, 'updatePersonalInformation']);

        //Driver CNIC
        Route::get('/cnic', [DriverDetailsController::class, 'getCNICDetails']);
        Route::post('/cnic/update', [DriverDetailsController::class, 'updateCNICDetails']);

        //Ride Offers
        Route::get('/get-rides', [DriverRideController::class, 'getLatestRides']);
        Route::get('/get-single-ride/{ride_id}', [DriverRideController::class, 'getSingleRideDetails']);
        Route::post('/offer-ride', [DriverRideController::class, 'OfferToRide']);
        Route::post('/update-ride-status', [DriverRideController::class, 'updateRideStatus']);
        Route::get('/get-ride-details/{ride_id}', [DriverRideController::class, 'getRideDetails']);

        //Driver Ride History
        Route::get('/ride-history', [DriverRideController::class, 'getRideHistory']);

        //Status Update
        Route::post('/update-status', [DriverDetailsController::class, 'updateDriverStatus']);

        //Ride reject
        Route::post('/reject-ride', [DriverRideController::class, 'rejectRide']);

    });

    //Customer Routes
    Route::group(['prefix' => 'customer'], function () {
        //Customer Personal Information
        route::post('/request-ride', [RideController::class, 'requestRide']);
        route::post('/calculate-distance-fare', [RideController::class, 'calculateDistanceFare']);
        route::post('/get-vehicle-types', [RideController::class, 'getVehicleTypes']);
        route::post('/apply-promo-code', [RideController::class, 'promoCodeApply']);
        route::post('/get-ride-offers', [RideController::class, 'rideOffers']);
        route::post('/accept-ride-offer', [RideController::class, 'acceptRideOffer']);
        route::post('/ride-extra-charge', [RideController::class, 'extraChargeRide']);
        route::post('/reject-ride-offer', [RideController::class, 'rejectRideOffer']);
        route::post('/expire-ride-offer', [RideController::class, 'expireRideOffer']);
        route::post('/cancel-ride', [RideController::class, 'cancelRide']);
        route::post('/post-review', [RideController::class, 'postReview']);
        route::get('/get-ride-history', [RideController::class, 'getRideHistory']);
    });

    //Chauffeurs Routes
    Route::group(['prefix' => 'chauffeurs'], function () {
        //Driver Vehicle
        Route::get('/home', [ChauffersController::class, 'getHomeData']);
        Route::get('/get-vehicles/{brand?}', [ChauffersController::class, 'getVehicles']);
        Route::get('/get-vehicle-details/{vehicle_id}', [ChauffersController::class, 'getVehicleDetails']);
        Route::post('/booking', [ChauffersController::class, 'createBooking']);
        Route::post('/transaction', [ChauffersController::class, 'createTransaction']);
        Route::get('/download-receipt/{booking_id}', [ChauffersController::class, 'downloadReceipt']);
        Route::get('/get-booking-history', [ChauffersController::class, 'getBookingHistory']);
        Route::get('/favourite-vehicles', [ChauffersController::class, 'getFavouriteVehicles']);
        Route::get('/add-to-favourite/{vehicle_id}', [ChauffersController::class, 'addToFavourite']);
    });

    //Chauffeurs Routes
    Route::group(['prefix' => 'restaurant'], function () {
        Route::get('/home', [RestaurantController::class, 'getHomeData']);

        Route::get('/shop', [RestaurantController::class, 'getShopDetails']);
        Route::post('/shop/update', [RestaurantController::class, 'updateShopDetails']);

        Route::get('/menus', [RestaurantController::class, 'getMenus']);
        Route::post('/menu/add', [RestaurantController::class, 'addMenu']);
        Route::post('/menu/update/{menu_id}', [RestaurantController::class, 'updateMenu']);

        Route::get('/menu-items', [RestaurantController::class, 'getMenuItems']);
        Route::post('/menu-item/add', [RestaurantController::class, 'addMenuItem']);
        Route::post('/menu-item/update/{item_id}', [RestaurantController::class, 'updateMenuItem']);

        Route::get('/orders', [RestaurantController::class, 'getOrders']);
        Route::get('/order-details/{order_id}', [RestaurantController::class, 'getOrderDetails']);

        Route::get('/toggle-status', [RestaurantController::class, 'toggleStatus']);
    });

});

Route::post('/calculate-distance-fare', [RideController::class, 'calculateDistanceFare']);
Route::post('/request-ride', [CustomRideController::class, 'requestCustomRide']);
// Authentication Routes (Login and Register) for guests
Route::post('/login', [LoginController::class, 'login_attempt']);
Route::post('/register', [RegisterController::class, 'register_attempt']);
// Route::post('/forget-password', [ForgetPasswordController::class, 'forgetPassEmail']);
// Route::post('/reset-password', [ForgetPasswordController::class, 'resetPassword']);

//test routes
Route::get('/ride/route', [HomeController::class, 'getRoute']);
