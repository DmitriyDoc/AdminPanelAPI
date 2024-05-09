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

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

// PARSER QUERY
//Route::any('/idtype', 'App\Http\Controllers\Parser\ParserIdTypeController');
//
//Route::any('/info', 'App\Http\Controllers\Parser\ParserInfoController');
//Route::any('/infocelebs', 'App\Http\Controllers\Parser\ParserInfoCelebsController');
//Route::any('/celebscredits', 'App\Http\Controllers\Parser\ParserInfoCelebsCreditsController');
//Route::any('/idimages', 'App\Http\Controllers\Parser\ParserIdImagesController');
//Route::any('/images', 'App\Http\Controllers\Parser\ParserImagesController');
//
//Route::any('/updatecelebs', 'App\Http\Controllers\Parser\ParserUpdateCelebController');
Route::put('/updatemovie', [App\Http\Controllers\Parser\ParserUpdateMovieController::class,'update']);
Route::put('/updateceleb', [App\Http\Controllers\Parser\ParserUpdateCelebController::class,'update']);


// DASHBOARD QUERY
Route::get('/dashboard',[\App\Http\Controllers\DashboardController::class, 'index']);

// MEDIA QUERY
Route::get('/media/{type}/{slug}/{id}',[\App\Http\Controllers\MediaController::class, 'index']);
Route::delete('/media/{type}/{slug}',[\App\Http\Controllers\MediaController::class, 'destroy']);

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

});
Route::controller(\App\Http\Controllers\CategoriesController::class)->group(function () {
    Route::get('/categories', 'index');
    Route::get('/categories/sections', 'getSections');
    Route::get('/categories/show', 'show');
    Route::post('/categories', 'store');
    Route::post('/category', 'add');
});
Route::controller(\App\Http\Controllers\SectionsController::class)->group(function () {
    Route::get('/sections/{slug}', 'index');
    Route::delete('/sections', 'destroy');
});
Route::controller(\App\Http\Controllers\CollectionsController::class)->group(function () {
    Route::get('/collections/{slugSect}/{slugColl}', 'index');
    Route::delete('/collections', 'destroy');
});
Route::controller(\App\Http\Controllers\FranchiseController::class)->group(function () {
    Route::get('/franchise/{slugSect}/{slugFran}', 'index');
    Route::delete('/franchise', 'destroy');
});
// RESOURCES QUERY
//Route::apiResources([
//    '/celebs' => \App\Http\Controllers\CelebsController::class,
//    '/movies' => \App\Http\Controllers\MoviesController::class,
//]);


