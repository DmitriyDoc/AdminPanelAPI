<?php

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
/**
 * ------------------------------------------------------------------------
 * Verify ACCESS
 * ------------------------------------------------------------------------
 */
Route::group(['middleware' => [\App\Http\Middleware\VerifyAPIAccess::class, 'throttle:60,1']], function () {

    /**
     * ------------------------------------------------------------------------
     * test routes
     * ------------------------------------------------------------------------
     */
    //Route::get('/transfer',[\App\Http\Controllers\DashboardController::class, 'test']);
    Route::get('/translate/celebs',[\App\Http\Controllers\DashboardController::class, 'testCelebs']);


    /**
     * ------------------------------------------------------------------------
     * auth routes
     * ------------------------------------------------------------------------
     */
    Route::post('login', [\App\Http\Controllers\AuthController::class, 'login']);
    Route::get('auth/verify', [\App\Http\Controllers\AuthController::class, 'verify']);

    /**
     * ------------------------------------------------------------------------
     * sanctum routes
     * ------------------------------------------------------------------------
     */
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('/updatemovie', [\App\Http\Controllers\Parser\ParserStartController::class,'parseMovieUpdate']);
        Route::get('/updatemovie/tracking', [\App\Http\Controllers\TrackingProgressBarController::class,'requestSessionKey']);

        Route::put('/updateceleb', [\App\Http\Controllers\Parser\ParserStartController::class,'parseCelebUpdate']);
        Route::get('/updateceleb/tracking', [\App\Http\Controllers\TrackingProgressBarController::class,'requestSessionKey']);

        Route::post('/parser', [\App\Http\Controllers\Parser\ParserStartController::class,'parseInit']);


        // DASHBOARD QUERY
        Route::get('/dashboard',[\App\Http\Controllers\DashboardController::class, 'index']);
        Route::get('/dashboard/tracking',[\App\Http\Controllers\TrackingProgressBarController::class, 'trackingDashboard'])->block();;

        // MEDIA QUERY
        Route::controller(\App\Http\Controllers\MediaController::class)->group(function () {
            Route::get('/media/show/images/{slug}/{id}', 'showImages');
            Route::get('/media/show/posters/{id}', 'showPosters');
            Route::get('/media/{type}/{slug}/{id}', 'index');
            Route::post('/media/poster_assign', 'store');
            Route::delete('/media/{type}/{slug}', 'destroy');
        });
        // Resources QUERY
        Route::controller(\App\Http\Controllers\MoviesController::class)->group(function () {
            Route::get('/movies/{slug}', 'index');
            Route::get('/movies/{slug}/show/{id}', 'show');
            Route::patch('/movies/{slug}/update/{id}', 'update');
            Route::delete('/movies/{slug}/{id}', 'destroy');

        });
        Route::controller(\App\Http\Controllers\CelebsController::class)->group(function () {
            Route::get('/persons/{slug}', 'index');
            Route::get('/persons/{slug}/show/{id}', 'show');
            Route::delete('/persons/{slug}/{id}', 'destroy');
            Route::patch('/persons/remove_items', 'removeFromFilmography');

        });
        Route::controller(\App\Http\Controllers\CategoriesController::class)->group(function () {
            Route::get('/categories', 'index');
            Route::get('/categories/sections', 'getSections');
            Route::get('/categories/select_franchise', 'showSelectFranchise');
            Route::get('/categories/select_collection', 'showSelectCollection');
            Route::post('/categories', 'store');
            Route::post('/categories/franchise', 'addFranchise');
            Route::post('/categories/collection', 'addCollection');
        });
        Route::controller(\App\Http\Controllers\SectionsController::class)->group(function () {
            Route::get('/sections/{slug}', 'index');
            Route::delete('/sections', 'destroy');
        });
        Route::controller(\App\Http\Controllers\CollectionsController::class)->group(function () {
            Route::get('/collections/{slugSect}/{slugColl}', 'index');
            Route::get('/collections', 'list');
            Route::delete('/collections/del', 'destroy');
        });
        Route::controller(\App\Http\Controllers\FranchiseController::class)->group(function () {
            Route::get('/franchise/{slugSect}/{slugFran}', 'index');
            Route::get('/franchise', 'list');
            Route::delete('/franchise/del', 'destroy');
        });
        Route::controller(\App\Http\Controllers\TagsController::class)->group(function () {
            Route::get('/tag/{tagName}', 'index');
            Route::get('/tags', 'list');
            //Route::delete('/tags/del', 'destroy');
        });
        /**
         * ------------------------------------------------------------------------
         * common routes
         * ------------------------------------------------------------------------
         */
        Route::get('logout', [
            \App\Http\Controllers\AuthController::class,
            'logout',
        ]);
        Route::get('user', function (Request $request) {
            return $request->user();
        });
        /**
         * ------------------------------------------------------------------------
         * users routes
         * ------------------------------------------------------------------------
         */
        Route::get('users', [
            \App\Http\Controllers\UserController::class,
            'index',
        ])->middleware('permission:users-all|users-view');

        Route::post('users', [
            \App\Http\Controllers\UserController::class,
            'store',
        ])->middleware('permission:users-all|users-create');

        Route::patch('users/{userId}', [
            \App\Http\Controllers\UserController::class,
            'update',
        ])->middleware('permission:users-all|users-edit');

        Route::delete('users/{userId}', [
            \App\Http\Controllers\UserController::class,
            'destroy',
        ])->middleware('permission:users-all|users-delete');

        /**
         * ------------------------------------------------------------------------
         * roles routes
         * ------------------------------------------------------------------------
         */
        Route::get('roles', [
            \App\Http\Controllers\RoleController::class,
            'index',
        ])->middleware('permission:roles-all|roles-view');

        Route::post('roles', [
            \App\Http\Controllers\RoleController::class,
            'store',
        ])->middleware('permission:roles-all|roles-create');

        Route::patch('roles/{roleId}', [
            \App\Http\Controllers\RoleController::class,
            'update',
        ])->middleware('permission:roles-all|roles-edit');

        Route::delete('roles/{roleId}', [
            \App\Http\Controllers\RoleController::class,
            'destroy',
        ])->middleware('permission:roles-all|roles-delete');

        /**
         * ------------------------------------------------------------------------
         * permissions routes
         * ------------------------------------------------------------------------
         */
        Route::get('permissions', [
            \App\Http\Controllers\PermissionController::class,
            'index',
        ])->middleware('permission:permissions-all|permissions-view');

        Route::post('permissions', [
            \App\Http\Controllers\PermissionController::class,
            'store',
        ])->middleware('permission:permissions-all|permissions-create');

        Route::patch('permissions/{permissionId}', [
            \App\Http\Controllers\PermissionController::class,
            'update',
        ])->middleware('permission:permissions-all|permissions-edit');

        Route::delete('permissions/{permissionId}', [
            \App\Http\Controllers\PermissionController::class,
            'destroy',
        ])->middleware('permission:permissions-all|permissions-delete');
    });


});


