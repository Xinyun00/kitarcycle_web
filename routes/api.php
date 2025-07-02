<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrganizerController;
use App\Http\Controllers\Api\PickupController;
use App\Http\Controllers\Api\RecyclerController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WasteClassificationController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TierLevelController;
use App\Http\Controllers\API\RewardController;
use App\Http\Controllers\Api\RewardRedemptionsController;
use App\Http\Controllers\Api\RewardRedemptionItemsController;
use App\Http\Controllers\Api\RewardCartItemsController;
use App\Http\Controllers\Api\RewardCheckoutController;
use App\Http\Controllers\Api\PointsController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/login', function (Request $request) {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

Route::prefix('organizer')->group(function () {
    Route::post('/register', [OrganizerController::class, 'register']);
    Route::post('/login', [OrganizerController::class, 'login']);
    Route::get('/by-category', [OrganizerController::class, 'getOrganizersByCategory']);
    Route::get('/organizers', [OrganizerController::class, 'getAllOrganizers']);
    Route::middleware('auth:api_organizer')->group(function () {
        // Example: Get authenticated organizer's own profile
        Route::get('/me', function (Request $request) {
            return response()->json(['success' => true, 'organizer' => $request->user()]);
        });
    });
    Route::get('/{id}', [OrganizerController::class, 'show']);
    Route::put('/{id}', [OrganizerController::class, 'updateProfile']);
    Route::middleware('auth:api_organizer')->delete('/delete', [OrganizerController::class, 'deleteAccount']);
});

Route::prefix('user')->group(function () {
    Route::post('/register', [RecyclerController::class, 'register']); // Register a new recycler
    Route::post('/login', [RecyclerController::class, 'login']); // Login recycler
    Route::get('/users', [RecyclerController::class, 'index']); // Get all recyclers
    Route::get('/{id}', [RecyclerController::class, 'show']); // Get a specific recycler by ID
    Route::middleware('auth:api')->put('/{id}', [RecyclerController::class, 'updateProfile']);
    Route::middleware('auth:api')->delete('/delete', [RecyclerController::class, 'deleteAccount']); // Delete account (authenticated user)
});

Route::group(['prefix' => 'events'], function () {
    //Events
    Route::get('/events', [EventController::class, 'index']); // Get all events
    Route::post('/events', [EventController::class, 'store']); // Create an event
    Route::get('/upcoming', [EventController::class, 'getUpcomingEvents']);
    Route::get('/past', [EventController::class, 'getPastEvents']);
    Route::get('/user/{userId}', [EventController::class, 'getEventsWithSubscriptionStatus']);
    Route::get('/events/{organizerId}', [EventController::class, 'getEventsByOrganizer']); //View All Event by Organizer
    Route::get('/{id}', [EventController::class, 'show']); // Get a single event
    Route::put('/events/{id}', [EventController::class, 'update']); // Update an event
    Route::post('/{id}/cancel', [EventController::class, 'cancelEvent']);
    Route::delete('/events/{id}', [EventController::class, 'delete']); // Delete an event
});

Route::prefix('drivers')->group(function () {
    Route::get('/organizer/{organizerId}', [DriverController::class, 'index']); // list drivers by organizer
    Route::post('/drivers', [DriverController::class, 'store']); // create new driver
    Route::get('/{id}', [DriverController::class, 'show']); // view single driver
    Route::get('/{id}/schedules', [DriverController::class, 'getDriverSchedules']); // view all schedules for a driver
    Route::put('/{id}', [DriverController::class, 'update']); // update driver
    Route::delete('/{id}', [DriverController::class, 'destroy']); // delete driver
    Route::put('/{id}/schedules', [DriverController::class, 'updateSchedules']);
    Route::post('/schedules/book', [DriverController::class, 'bookSchedule']);
    Route::delete('/schedules/{id}', [DriverController::class, 'deleteSchedule']); // Delete a single driver schedule
});

Route::prefix('pickups')->group(function () {
    Route::get('/pickups', [PickupController::class, 'index']);
    Route::post('/pickups', [PickupController::class, 'store']);
    Route::get('/{id}', [PickupController::class, 'show']);
    Route::put('/{id}', [PickupController::class, 'update']); // Update pickup details
    Route::post('/{id}/weight', [PickupController::class, 'updateWeight']); // Update pickup weight and complete
    Route::get('/{id}/calculate-points', [PickupController::class, 'calculatePickupPoints']);
    Route::get('/user/{userId}', [PickupController::class, 'getPickupsByUser']); // Get pickups by user
    Route::post('/user/pickup', [PickupController::class, 'pickup']); // Create a new pickup request
    Route::put('/{id}/status', [PickupController::class, 'updateStatus']); // Update pickup status
    Route::post('/{id}/reject', [PickupController::class, 'rejectPickup']); //Reject Pickup
    Route::post('/{id}/cancel', [PickupController::class, 'cancelPickup']);
    Route::get('/organizers/{id}/stats', [PickupController::class, 'getOrganizerStats']); //Get total recyclers and total completed collections
    Route::get('organizers/{id}/monthly-collections', [PickupController::class, 'getMonthlyCollections']);
    Route::delete('/{id}', [PickupController::class, 'destroy']); // Delete pickup
});

Route::prefix('categories')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);       // GET /api/categories
    Route::post('/categories', [CategoryController::class, 'store']);      // POST /api/categories
    Route::get('/{id}', [CategoryController::class, 'show']);    // GET /api/categories/{id}
    Route::put('/{id}', [CategoryController::class, 'update']);  // PUT /api/categories/{id}
    Route::delete('/{id}', [CategoryController::class, 'destroy']); // DELETE /api/categories/{id}
});

Route::prefix('subscriptions')->group(function () {
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::delete('/unsubscribe', [SubscriptionController::class, 'unsubscribe']);
    Route::get('/{userId}', [SubscriptionController::class, 'userSubscriptions']);
    Route::get('/status/{userId}/{eventId}', [SubscriptionController::class, 'getSubscriptionStatus']);
    Route::post('/cleanup-past', [SubscriptionController::class, 'cleanupPastSubscriptions']);
});

Route::post('/classify-waste', [WasteClassificationController::class, 'classify'])->middleware('api');

Route::prefix('tier-levels')->group(function () {
    Route::get('/', [TierLevelController::class, 'index']); // Get all tier levels
    Route::get('/{tierLevel}', [TierLevelController::class, 'show']); // Get specific tier level
    Route::post('/', [TierLevelController::class, 'store']); // Create new tier level
    Route::put('/{tierLevel}', [TierLevelController::class, 'update']); // Update tier level
    Route::post('/assign/{user}', [TierLevelController::class, 'assignTierToUser']); // Assign tier to user
    Route::delete('/{tierLevel}', [TierLevelController::class, 'destroy']); // Delete tier level
});

Route::prefix('rewards')->group(function () {
    // --- Static routes must be defined FIRST ---
    Route::get('/list', [RewardController::class, 'index']);
    Route::post('/create', [RewardController::class, 'store']);
    Route::get('/cart-items', [RewardCartItemsController::class, 'index']);
    Route::post('/cart-items', [RewardCartItemsController::class, 'store']);
    Route::post('/checkout', [RewardCheckoutController::class, 'checkout']);
    Route::get('/redemptions', [RewardRedemptionsController::class, 'index']);
    Route::post('/redemptions', [RewardRedemptionsController::class, 'store']);
    Route::get('/redemption-items', [RewardRedemptionItemsController::class, 'index']);
    Route::post('/redemption-items', [RewardRedemptionItemsController::class, 'store']);

    // --- Parameterized routes must be defined AFTER ---
    Route::delete('/cart-items/{cartItem}', [RewardCartItemsController::class, 'destroy']);
    Route::get('/{reward}', [RewardController::class, 'show']);
    Route::put('/{reward}', [RewardController::class, 'update']);
    Route::post('/{id}/update-image', [RewardController::class, 'updateImage']);
    Route::delete('/{reward}', [RewardController::class, 'destroy']);
    Route::get('/cart-items/{cartItem}', [RewardCartItemsController::class, 'show']);
    Route::put('/cart-items/{cartItem}', [RewardCartItemsController::class, 'update']);
    Route::get('/redemptions/{redemption}', [RewardRedemptionsController::class, 'show']);
    Route::put('/redemptions/{redemption}', [RewardRedemptionsController::class, 'update']);
    Route::delete('/redemptions/{redemption}', [RewardRedemptionsController::class, 'destroy']);
    Route::get('/redemption-items/{item}', [RewardRedemptionItemsController::class, 'show']);
    Route::put('/redemption-items/{item}', [RewardRedemptionItemsController::class, 'update']);
    Route::delete('/redemption-items/{item}', [RewardRedemptionItemsController::class, 'destroy']);
});

// Notification
Route::prefix('notifications')->group(function () {
    Route::middleware('auth:api,api_organizer')->group(function () {
        // FCM Token Management
        Route::post('/fcm-token', [NotificationController::class, 'storeFcmToken']);
        Route::delete('/fcm-token', [NotificationController::class, 'removeFcmToken']);
        Route::delete('/fcm-tokens', [NotificationController::class, 'removeAllFcmTokens']);
        Route::get('/devices', [NotificationController::class, 'getRegisteredDevices']);

        // Notification Management
        Route::get('/', [NotificationController::class, 'getNotifications']);
        Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-multiple', [NotificationController::class, 'markMultipleAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::delete('/{notificationId}', [NotificationController::class, 'deleteNotification']);
        Route::delete('/', [NotificationController::class, 'deleteMultipleNotifications']);
        Route::delete('/clear-all', [NotificationController::class, 'clearAllNotifications']);

        // Notification Preferences
        Route::get('/preferences', [NotificationController::class, 'getNotificationPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updateNotificationPreferences']);
        Route::post('/test', [NotificationController::class, 'sendTestNotification']);
    });
});

Route::prefix('points')->group(function () {
    Route::get('/{userId}/current', [PointsController::class, 'getCurrentPoints']);
    Route::get('/leaderboard', [PointsController::class, 'getLeaderboard']);
    Route::get('/monthly/{userId}', [PointsController::class, 'getMonthlyPoints']);
    Route::get('/history/{userId}', [PointsController::class, 'getPointsHistory']);
    Route::get('/transactions/{userId}', [PointsController::class, 'getUnifiedHistory']);
});
