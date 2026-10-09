<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GithubController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Dashboard\AnnoucementController;
use App\Http\Controllers\Dashboard\BoostPriceController;
use App\Http\Controllers\Dashboard\ChauffeursBooking;
use App\Http\Controllers\Dashboard\ChauffeursVehicle;
use App\Http\Controllers\Dashboard\ComplainController;
use App\Http\Controllers\Dashboard\CustomRideController;
use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\RolePermission\PermissionController;
use App\Http\Controllers\Dashboard\RolePermission\RoleController;
use App\Http\Controllers\Dashboard\SettingController;
use App\Http\Controllers\Dashboard\User\ArchivedUserController;
use App\Http\Controllers\Dashboard\User\UserController;
use App\Http\Controllers\Dashboard\DriverController;
use App\Http\Controllers\Dashboard\PromoCodeController;
use App\Http\Controllers\Dashboard\RideController;
use App\Http\Controllers\Dashboard\FinanceController;
use App\Http\Controllers\Dashboard\PricingFeesController;
use App\Http\Controllers\Dashboard\PayrollController;
use App\Http\Controllers\Dashboard\SupportRequestController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\VehicleTypeController;
use App\Http\Controllers\Dashboard\VehicleTypeIconController;
use App\Http\Controllers\Dashboard\Restaurant\CategoryController as RestaurantCategoryController;
use App\Http\Controllers\Dashboard\Restaurant\RestaurantsController;
use App\Http\Controllers\Dashboard\Restaurant\RestaurantVoucherController;
use App\Http\Controllers\FirebaseController;
use App\Http\Middleware\CheckAccountActivation;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/lang/{lang}', function ($lang) {
    // dd($lang);
    if(! in_array($lang, ['en','fr','ar','de'])){
        abort(404);
    }else{
        session(['locale' => $lang]);
        App::setLocale($lang);
        Log::info("Locale set to: " . $lang);
        return redirect()->back();
    }
})->name('lang');

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage link created successfully';
});

Route::get('/current-time', function () {
    return response()->json([
        'time' => Carbon::now()->format('h:iA') // Returns time in 12-hour format with AM/PM
    ]);
});

Auth::routes();
Route::get('/', function () {
    return redirect()->route('dashboard');
});
Route::get('/test-route', function () {
    return view('dashboard.test.test_ride_location');
});
// Guest Routes
Route::group(['middleware' => ['guest']], function () {

    //User Login Authentication Routes
    Route::get('login', [LoginController::class, 'login'])->name('login');
    Route::post('login-attempt', [LoginController::class, 'login_attempt'])->name('login.attempt');

    //User Register Authentication Routes
    Route::get('register', [RegisterController::class, 'register'])->name('register');
    Route::post('registration-attempt', [RegisterController::class, 'register_attempt'])->name('register.attempt');

    // Google Authentication Routes
    Route::get('auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google.login');
    Route::get('auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.login.callback');
    // Github Authentication Routes
    Route::get('auth/github', [GithubController::class, 'redirectToGithub'])->name('auth.github.login');
    Route::get('auth/github/callback', [GithubController::class, 'handleGithubCallback'])->name('auth.github.login.callback');
    // Facebook Authentication Routes
    // Route::controller(FacebookController::class)->group(function () {
    //     Route::get('auth/facebook', 'redirectToFacebook')->name('auth.facebook');
    //     Route::get('auth/facebook/callback', 'handleFacebookCallback');
    // });

});

// Authentication Routes
Route::group(['middleware' => ['auth']], function () {
    Route::get('login-verification', [AuthController::class, 'login_verification'])->name('login.verification');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('verify-account', [AuthController::class, 'verify_account'])->name('verify.account');
    Route::post('resend-code', [AuthController::class, 'resend_code'])->name('resend.code');

    // Verified notification
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verification_verify'])->middleware(['signed'])->name('verification.verify');
    Route::get('email/verify', [AuthController::class, 'verification_notice'])->name('verification.notice');
    Route::post('email/verification-notification', [AuthController::class, 'verification_send'])->middleware(['throttle:2,1'])->name('verification.send');
    // Verified notification
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/deactivated', function () {
        return view('errors.deactivated');
    })->name('deactivated');
    Route::get('/no-workspace', function () {
        return view('dashboard.no-workspace');
    })->name('no-workspace');

    Route::post('/workspace/switch', [\App\Http\Controllers\Dashboard\WorkspaceController::class, 'switch'])
        ->name('workspace.switch');

    Route::middleware(['check.activation', 'no.cache', 'workspace'])->group(function () {

        Route::resource('profile', ProfileController::class);
        Route::post('profile/setting/account/{id}', [ProfileController::class, 'accountDeactivation'])->name('account.deactivate');
        Route::post('profile/security/password/{id}', [ProfileController::class, 'passwordUpdate'])->name('update.password');

        Route::get('/get/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/notifications/click/{id}', [NotificationController::class, 'notificationClickHandle'])->name('notification.click');
        Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
        Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
        Route::post('/notifications/delete-all', [NotificationController::class, 'deleteAll'])->name('notifications.deleteAll');
        Route::post('/notifications/{id}/delete', [NotificationController::class, 'deleteNotification'])->name('notifications.delete');
        Route::get('/notifications/send-test-noti/{id}', [NotificationController::class, 'testNotification']);

        Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard');

        // Admin Dashboard Authentication Routes
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

            Route::resource('user', UserController::class);
            Route::resource('archived-user', ArchivedUserController::class);
            Route::get('user/restore/{id}', [ArchivedUserController::class, 'restoreUser'])->name('archived-user.restore');
            Route::get('user/status/{id}', [UserController::class, 'updateStatus'])->name('user.status.update');
            Route::get('admin-users', [UserController::class, 'adminUsers'])->name('admin-users.index');
            Route::get('customers/{id}', [\App\Http\Controllers\Dashboard\CustomerProfileController::class, 'show'])->name('customers.show');
            Route::put('admin-users/{id}/workspaces', [UserController::class, 'updateWorkspaces'])->name('admin-users.workspaces.update');
            Route::get('restaurant-owners', [UserController::class, 'restaurantOwners'])->name('restaurant-owners.index');

            // Role & Permission Start
            Route::resource('permissions', PermissionController::class);

            Route::resource('roles', RoleController::class);
            //Role & Permission End

            // Setting Routes
            Route::resource('setting', SettingController::class);
            Route::put('company/setting/{id}', [SettingController::class, 'updateCompanySettings'])->name('setting.company.update');
            Route::put('recaptcha/setting/{id}', [SettingController::class, 'updateRecaptchaSettings'])->name('setting.recaptcha.update');
            Route::put('system/setting/{id}', [SettingController::class, 'updateSystemSettings'])->name('setting.system.update');
            Route::put('email/setting/{id}', [SettingController::class, 'updateEmailSettings'])->name('setting.email.update');

            //Terms & Conditions
            Route::get('terms', [\App\Http\Controllers\Dashboard\TermsController::class, 'index'])->name('terms.index');
            Route::post('terms', [\App\Http\Controllers\Dashboard\TermsController::class, 'store'])->name('terms.store');
            Route::post('send-mail/setting', [SettingController::class, 'sendTestMail'])->name('setting.send_test_mail');

            // User Dashboard Authentication Routes
            Route::get('drivers/pending-verifications', [DriverController::class, 'pendingVerifications'])->name('drivers.pending-verifications');
            // 'update' excluded -- city (its only use) is now set automatically
            // from the driver's registration location, not admin-editable.
            Route::resource('drivers', DriverController::class)->except(['update']);
            Route::post('drivers/{id}/verification/approve', [DriverController::class, 'approveVerification'])->name('drivers.verification.approve');
            Route::post('drivers/{id}/verification/reject', [DriverController::class, 'rejectVerification'])->name('drivers.verification.reject');
            Route::get('drivers/{id}/documents/pdf', [DriverController::class, 'exportDocumentsPdf'])->name('drivers.documents.pdf');

            // Delivery workspace -- Riders / Rider Verifications (same driver
            // records, same controller/views, filtered to is_delivery=true)
            Route::get('delivery-riders', [DriverController::class, 'deliveryIndex'])->name('delivery-riders.index');
            Route::get('delivery-riders/pending-verifications', [DriverController::class, 'deliveryPendingVerifications'])->name('delivery-riders.pending-verifications');

            //PromoCode Routes
            Route::resource('promo-codes', PromoCodeController::class);
            Route::get('promo-codes/status/{id}', [PromoCodeController::class, 'updateStatus'])->name('promo-codes.status.update');

            //VehicleTypeController Routes
            Route::resource('vehicle-types', VehicleTypeController::class);
            Route::get('vehicle-types/status/{id}', [VehicleTypeController::class, 'updateStatus'])->name('vehicle-types.status.update');

            //Vehicle Type Icon Pool (super-admin only)
            Route::get('vehicle-type-icons', [VehicleTypeIconController::class, 'index'])->name('vehicle-type-icons.index');
            Route::post('vehicle-type-icons', [VehicleTypeIconController::class, 'store'])->name('vehicle-type-icons.store');
            Route::delete('vehicle-type-icons/{id}', [VehicleTypeIconController::class, 'destroy'])->name('vehicle-type-icons.destroy');

            //Create Notification
            Route::get('/notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
            Route::post('/notifications/store', [NotificationController::class, 'store'])->name('notifications.store');
            Route::get('/notifications/search-users', [NotificationController::class, 'searchUsers'])->name('notifications.search-users');

            //Chauffeurs Vehicle Routes
            Route::resource('chauffeur-vehicles', ChauffeursVehicle::class);
            Route::get('chauffeur-vehicles/status/{id}', [ChauffeursVehicle::class, 'updateStatus'])->name('chauffeur-vehicles.status.update');
            Route::resource('chauffeur-bookings', ChauffeursBooking::class);
            Route::post('chauffeur-bookings/status/{id}', [ChauffeursBooking::class, 'updateStatus'])->name('chauffeur-bookings.status.update');
            Route::get('chauffeur-bookings/download-receipt/{id}', [ChauffeursBooking::class, 'downloadReceipt'])->name('chauffeur-bookings.download-receipt');
            Route::post('chauffeur-bookings/transactions/{id}/mark-received', [ChauffeursBooking::class, 'markTransactionReceived'])->name('chauffeur-bookings.transactions.mark-received');

            //Complain Routes
            Route::resource('complains', ComplainController::class);
            Route::post('complains/status/{id}', [ComplainController::class, 'updateStatus'])->name('complains.status.update');

            //Boost Hours
            Route::resource('boost-hours', BoostPriceController::class);

            //Dashboard Custom Rides Routes
            Route::get('custom-rides', [CustomRideController::class, 'index'])->name('custom-rides.index');
            Route::get('custom-rides/stats', [CustomRideController::class, 'dispatchStats'])->name('custom-rides.stats');
            Route::get('custom-rides/queue', [CustomRideController::class, 'queue'])->name('custom-rides.queue');
            Route::post('custom-rides/queue/presets', [CustomRideController::class, 'savePreset'])->name('custom-rides.queue.presets');
            Route::get('custom-rides/live-tracking', [CustomRideController::class, 'liveTrackingData'])->name('custom-rides.live-tracking');

            //Live Tracking page -- split out from Custom Rides/Manual Ride
            // Assignment (they used to share one page/URL, just anchor-scrolled
            // to different sections). This is the dedicated monitoring-only
            // view; custom-rides.live-tracking above stays as its polling data
            // endpoint, now consumed by this page instead of the old one.
            Route::get('live-tracking', [CustomRideController::class, 'liveTracking'])->name('live-tracking.index');
            // Was POST /api/request-ride (unauthenticated -- so once requestCustomRide()
            // got an authorize() check it threw "unauthorized" for everyone). This is
            // a dashboard action, belongs on a session-authed web route.
            Route::post('custom-rides', [CustomRideController::class, 'requestCustomRide'])->name('custom-rides.store');

            //AnnouncementController Routes
            Route::resource('announcements', AnnoucementController::class);
            Route::get('announcements/status/{id}', [AnnoucementController::class, 'updateStatus'])->name('announcements.status.update');

            //Rides
            Route::resource('rides', RideController::class);

            //Operator/Driver Reports
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

            //Payroll / Accounting Exports
            Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
            Route::get('payroll/export-pdf', [PayrollController::class, 'exportPdf'])->name('payroll.export-pdf');
            Route::get('payroll/export-excel', [PayrollController::class, 'exportExcel'])->name('payroll.export-excel');
            Route::post('payroll/bulk-send', [PayrollController::class, 'bulkSend'])->name('payroll.bulk-send');
            Route::post('payroll/mark-paid', [PayrollController::class, 'markAsPaid'])->name('payroll.mark-paid');

            //Finance (Tax & Commission settings, ride-level reports, payroll tab)
            Route::get('finance', fn () => redirect()->route('dashboard.finance.tax-commission'))->name('finance.index');
            Route::get('finance/tax-commission', [FinanceController::class, 'taxCommission'])->name('finance.tax-commission');
            Route::put('finance/tax-commission', [FinanceController::class, 'updateTaxCommission'])->name('finance.tax-commission.update');
            Route::get('finance/reports', [FinanceController::class, 'reports'])->name('finance.reports');
            Route::get('finance/reports/export-pdf', [FinanceController::class, 'exportReportPdf'])->name('finance.reports.export-pdf');
            Route::get('finance/reports/export-excel', [FinanceController::class, 'exportReportExcel'])->name('finance.reports.export-excel');
            Route::get('finance/restaurant-payable', [FinanceController::class, 'restaurantPayable'])->name('finance.restaurant-payable');
            Route::post('finance/restaurant-payable/mark-paid', [FinanceController::class, 'markRestaurantPayoutAsPaid'])->name('finance.restaurant-payable.mark-paid');

            //Pricing & Fees (Batch 1 Part 1, super-admin only)
            Route::get('pricing-fees', [PricingFeesController::class, 'index'])->name('pricing-fees.index');
            Route::put('pricing-fees', [PricingFeesController::class, 'update'])->name('pricing-fees.update');

            //Driver Support Requests
            Route::get('support-requests', [SupportRequestController::class, 'index'])->name('support-requests.index');
            Route::get('support-requests/{id}', [SupportRequestController::class, 'show'])->name('support-requests.show');
            Route::post('support-requests/{id}/approve', [SupportRequestController::class, 'approve'])->name('support-requests.approve');
            Route::post('support-requests/{id}/reject', [SupportRequestController::class, 'reject'])->name('support-requests.reject');
            Route::post('support-requests/{id}/close', [SupportRequestController::class, 'close'])->name('support-requests.close');
            Route::post('support-requests/{id}/reply', [SupportRequestController::class, 'reply'])->name('support-requests.reply');

            //Restaurant Categories
            Route::resource('restaurant-categories', RestaurantCategoryController::class);
            Route::get('restaurant-categories/status/{id}', [RestaurantCategoryController::class, 'updateStatus'])->name('restaurant-categories.status.update');

            //Restaurants
            Route::resource('restaurants', RestaurantsController::class);
            Route::get('restaurants/status/{id}', [RestaurantsController::class, 'updateStatus'])->name('restaurants.status.update');
            Route::put('restaurants/menus/update/{id}', [RestaurantsController::class, 'updateRestaurantMenu'])->name('restaurants.menus.update');
            Route::put('restaurants/items/update/{id}', [RestaurantsController::class, 'updateRestaurantItem'])->name('restaurants.items.update');
            Route::put('restaurants/orders/update/{id}', [RestaurantsController::class, 'updateRestaurantOrder'])->name('restaurants.orders.update');
            Route::put('restaurants/schedule/update/{id}', [RestaurantsController::class, 'updateRestaurantSchedule'])->name('restaurants.schedule.update');
            Route::put('restaurants/review/update/{id}', [RestaurantsController::class, 'updateRestaurantReview'])->name('restaurants.reviews.update');

            //Restaurant Voucher
            Route::resource('restaurant-vouchers', RestaurantVoucherController::class);
            Route::get('restaurant-vouchers/status/{id}', [RestaurantVoucherController::class, 'updateStatus'])->name('restaurant-vouchers.status.update');

            //Delivery workspace -- Orders Queue (Phase 2 of the admin workspace split)
            Route::get('delivery', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'index'])->name('delivery.index');
            Route::get('delivery/queue', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'queue'])->name('delivery.queue');
            Route::post('delivery/queue/presets', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'savePreset'])->name('delivery.queue.presets');
            Route::post('delivery/order/{order_id}/cancel', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'cancelOrder'])->name('delivery.order.cancel');
            Route::post('delivery/order/{order_id}/override-delivery', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'overrideFoodDelivery'])->name('delivery.order.override-delivery');
            Route::post('delivery/ride/{ride_id}/override-delivery', [\App\Http\Controllers\Dashboard\DeliveryController::class, 'overrideParcelDelivery'])->name('delivery.ride.override-delivery');

            //Rider Cash (Batch 1 Part 8)
            Route::get('rider-cash', [\App\Http\Controllers\Dashboard\RiderCashController::class, 'index'])->name('rider-cash.index');
            Route::get('rider-cash/{riderId}', [\App\Http\Controllers\Dashboard\RiderCashController::class, 'show'])->name('rider-cash.show');
            Route::post('rider-cash/{riderId}/settle', [\App\Http\Controllers\Dashboard\RiderCashController::class, 'recordSettlement'])->name('rider-cash.settle');
        });
    });

});

// Frontend Pages Routes
Route::name('frontend.')->group(function () {

});

Route::get('/firebase-test', [FirebaseController::class, 'test']);


//Artisan Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/clear-cache', function () {
        Artisan::call('cache:clear');
        return "Application cache cleared!";
    })->name('clear.cache');

    Route::get('/clear-config', function () {
        Artisan::call('config:clear');
        return "Configuration cache cleared!";
    })->name('clear.config');

    Route::get('/clear-view', function () {
        Artisan::call('view:clear');
        return "View cache cleared!";
    })->name('clear.view');

    Route::get('/clear-route', function () {
        Artisan::call('route:clear');
        return "Route cache cleared!";
    })->name('clear.route');

    Route::get('/clear-optimize', function () {
        Artisan::call('optimize:clear');
        return "Optimization cache cleared!";
    })->name('clear.optimize');
});

