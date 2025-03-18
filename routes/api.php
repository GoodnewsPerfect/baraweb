<?php

use App\Http\Controllers\AddressInfoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViewController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CreatorController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return 'bara web test';
});
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('reset-password/{token}', function (string $token) {
    return redirect('https://baraweb.waltanforte.com/reset-password/' . $token);
})->name('password.reset');    
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.update');


Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);
Route::get('/products/trending', [ProductController::class, 'getTrendingProducts']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('products/{slug}', [ProductController::class, 'show']);
Route::get('menu', [MenuController::class, 'index']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('subcategories', [SubCategoryController::class, 'index']);
Route::get('subcategories/{id}', [SubCategoryController::class, 'show']);
Route::delete('subcategories/{identifier}', [SubCategoryController::class, 'destroy']);
Route::get('product-types', [ProductTypeController::class, 'index']);
Route::get('product-types/{id}', [ProductTypeController::class, 'show']);
Route::get('tags', [TagController::class, 'index']);
Route::get('tags/{id}', [TagController::class, 'show']);

Route::get('/banners', [BannerController::class, 'index']);
Route::get('/banners/{id}', [BannerController::class, 'show']);

// creators
Route::get('/creators', [CreatorController::class, 'index']);
Route::get('/creators/{identifier}', [CreatorController::class, 'show']);

//others
Route::get('shipping-policy', [PolicyController::class, 'getShippingPolicy']);
Route::get('return-policy', [PolicyController::class, 'getReturnPolicy']);
Route::get('faqs', [PolicyController::class, 'getFaqs']);
Route::get('/search', [SearchController::class, 'search']);

//stripe
Route::post('/webhook/stripe', [StripeController::class, 'handleWebhook']);
    // paystack order
Route::post('/orders/create', [OrderController::class, 'createOrder']);
Route::get('/orders/callback', [OrderController::class, 'paystackCallback'])->name('paystack.callback');
Route::post('/orders/verify-payment', [OrderController::class, 'verifyPayment']);

// Authenticated routes
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.resend');
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // Protected address route
    Route::get('/addresses', [AddressInfoController::class, 'index']);
    Route::get('/user/{userId}/addresses', [AddressInfoController::class, 'userAddresses']);
    Route::get('/my-addresses', [AddressInfoController::class, 'myAddresses']);
    Route::post('/addresses', [AddressInfoController::class, 'store']);
    Route::put('/addresses/{id}', [AddressInfoController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressInfoController::class, 'destroy']);
    Route::patch('/addresses/{id}/set-delivery', [AddressInfoController::class, 'setDeliveryAddress']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::put('orders/{id}', [OrderController::class, 'update']);
    Route::delete('orders/{id}', [OrderController::class, 'destroy']);

    Route::get('order-items', [OrderItemController::class, 'index']);
    Route::post('order-items', [OrderItemController::class, 'store']);
    Route::get('order-items/{id}', [OrderItemController::class, 'show']);
    Route::put('order-items/{id}', [OrderItemController::class, 'update']);
    Route::delete('order-items/{id}', [OrderItemController::class, 'destroy']);

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
    Route::put('transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('transactions/{id}', [TransactionController::class, 'destroy']);

    Route::get('views', [ViewController::class, 'index']);
    Route::post('views', [ViewController::class, 'store']);
    Route::get('views/{id}', [ViewController::class, 'show']);
    Route::put('views/{id}', [ViewController::class, 'update']);
    Route::delete('views/{id}', [ViewController::class, 'destroy']);
    
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites/check/{productId}', [FavoriteController::class, 'check']);

    Route::post('reviews', [ReviewController::class, 'store']);
    Route::get('products/{productId}/reviews', [ReviewController::class, 'index']);
    Route::get('user/reviews', [ReviewController::class, 'userReviews']);
    Route::get('reviews/product/{identifier}', [ReviewController::class, 'getProductReviews']);
    Route::delete('reviews/{id}', [ReviewController::class, 'destroy']);

    //stripe
    Route::post('/checkout/create-session', [StripeController::class, 'createCheckoutSession']);
    Route::post('/create-payment-intent', [StripeController::class, 'createPaymentIntent']);
    
});

// Admin routes
Route::middleware(['auth:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'getAllUsers']);
    Route::post('/make-admin/{id}', [AdminController::class, 'makeAdmin']);

    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{id}', [BannerController::class, 'update']);
    Route::delete('/banners/{id}', [BannerController::class, 'destroy']);

    Route::get('/newsletter/subscribers', [NewsletterController::class, 'index']);

    Route::get('/blogs', [BlogController::class, 'index']);
    Route::get('/blogs/{id}', [BlogController::class, 'show']);
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::post('/blogs/{id}', [BlogController::class, 'update']);
    Route::delete('/blogs/{id}', [BlogController::class, 'destroy']);
    Route::delete('/blogs/media/{id}', [BlogController::class, 'deleteMedia']);

    Route::post('/blog-categories', [BlogController::class, 'storeCategory']);
Route::get('/blog-categories', [BlogController::class, 'indexCategory']);
Route::get('/blog-categories/{id}', [BlogController::class, 'showCategory']);
Route::put('/blog-categories/{id}', [BlogController::class, 'updateCategory']);
Route::delete('/blog-categories/{id}', [BlogController::class, 'destroyCategory']);

    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
    Route::delete('products/images/{id}', [ProductController::class, 'deleteImage']);
    Route::delete('products/variants/{id}', [ProductController::class, 'deleteVariant']);
    Route::delete('products/variants/images/{id}', [ProductController::class, 'deleteVariantImage']);


    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);

    Route::post('subcategories', [SubCategoryController::class, 'store']);
    Route::put('subcategories/{id}', [SubCategoryController::class, 'update']);
    Route::delete('subcategories/{id}', [SubCategoryController::class, 'destroy']);

    Route::post('product-types', [ProductTypeController::class, 'store']);
    Route::put('product-types/{id}', [ProductTypeController::class, 'update']);
    Route::delete('product-types/{id}', [ProductTypeController::class, 'destroy']);

    Route::get('/all-orders', [OrderController::class, 'adminAllOrders']);

    Route::post('tags', [TagController::class, 'store']);
    Route::put('tags/{id}', [TagController::class, 'update']);
    Route::delete('tags/{id}', [TagController::class, 'destroy']);

    Route::get('users', [UserController::class, 'index']);
    Route::post('users', [UserController::class, 'store']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // Shipping Policy Routes
    Route::post('shipping-policy', [PolicyController::class, 'updateShippingPolicy']);

    // Return Policy Routes
    Route::post('return-policy', [PolicyController::class, 'updateReturnPolicy']);

    // FAQ Routes
    Route::post('faqs', [PolicyController::class, 'createFaq']);
    Route::put('faqs/{faq}', [PolicyController::class, 'updateFaq']);
    Route::delete('faqs/{faq}', [PolicyController::class, 'deleteFaq']);

    //inactives
    Route::get('shipping-policy/inactive', [PolicyController::class, 'getInactiveShippingPolicies']);
    Route::get('return-policy/inactive', [PolicyController::class, 'getInactiveReturnPolicies']);
    Route::get('faqs/inactive', [PolicyController::class, 'getInactiveFaqs']);

    // Creators
    Route::post('/creators', [CreatorController::class, 'store']);
    Route::put('/creators/{identifier}', [CreatorController::class, 'update']);
    Route::delete('/creators/{identifier}', [CreatorController::class, 'destroy']);

    /// Orders
    Route::put('/orders/{id}/status', [OrderController::class, 'updateOrderStatus']);
});
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{id}', [BlogController::class, 'show']);