<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\GoogleLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\GoogleCalendarController;


// 谷歌登录
Route::get('oauth2/google', [GoogleLoginController::class, 'redirect'])->name('login.google');
Route::get('oauth2/google/callback', [GoogleLoginController::class, 'callback']);

Route::get('/', function () {
    return view('auth.login');
});


Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::middleware('auth.middleware')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/connect-google-calendar', [GoogleCalendarController::class, 'connect'])->name('connect.google.calendar');
    Route::get('/google-calendar-callback', [GoogleCalendarController::class, 'callback']);
    Route::get('/calendar', [GoogleCalendarController::class, 'showCalendar'])->name('calendar');

    Route::get('/get-events', [GoogleCalendarController::class, 'getEvents'])->name('get.events');
    Route::post('/events', [GoogleCalendarController::class, 'createEvent'])->name('events.create');
    Route::put('/events/{eventId}', [GoogleCalendarController::class, 'updateEvent'])->name('events.update');
    Route::delete('/events/{eventId}', [GoogleCalendarController::class, 'deleteEvent'])->name('events.delete');
    Route::post('/events', [GoogleCalendarController::class, 'store'])->name('events.store');

    Route::get('/reauthorize-google', [GoogleLoginController::class, 'reauthorize'])
        ->name('google.reauthorize')
        ->middleware('auth');
});
