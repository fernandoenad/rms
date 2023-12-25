<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController as GuestHome;
use App\Http\Controllers\Admin\HomeController as AdminHome;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\InquiryController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [GuestHome::class, 'index'])->name('guest.index');
Route::post('/', [GuestHome::class, 'lookup'])->name('guest.lookup');
Route::get('/application/{application}', [GuestHome::class, 'show'])->name('guest.application.show');
Route::post('/application/{application}', [GuestHome::class, 'store'])->name('guest.application.store');

Auth::routes();

Route::group(['middleware' => ['active']], function () {
    Route::get('/admin', [AdminHome::class, 'index'])->name('admin.index');

    Route::get('/admin/applications', [ApplicationController::class, 'index'])->name('admin.applications.index');
    Route::get('/admin/applications/create', [ApplicationController::class, 'create'])->name('admin.applications.create');
    Route::post('/admin/applications/', [ApplicationController::class, 'store'])->name('admin.applications.store');
    Route::post('/admin/applications/import', [ApplicationController::class, 'import'])->name('admin.applications.import');
    Route::get('/admin/applications/{application}', [ApplicationController::class, 'show'])->name('admin.applications.show');
    Route::get('/admin/applications/{application}/delete', [ApplicationController::class, 'delete'])->name('admin.applications.delete');
    Route::delete('/admin/applications/{application}', [ApplicationController::class, 'destroy'])->name('admin.applications.destroy');
    Route::get('/admin/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('admin.applications.edit');
    Route::put('/admin/applications/{application}', [ApplicationController::class, 'update'])->name('admin.applications.update');
    Route::patch('/admin/applications/{application}', [ApplicationController::class, 'saveInquiry'])->name('admin.applications.saveInquiry');

    Route::get('/admin/inquiries', [InquiryController::class, 'index'])->name('admin.inquiries.index');
});

Route::group(['middleware' => ['admin']], function () {
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/delete', [UserController::class, 'delete'])->name('admin.users.delete');
    Route::get('/admin/users/{user}/reset', [UserController::class, 'reset'])->name('admin.users.reset');
    Route::put('/admin/users/{user}/reset', [UserController::class, 'resetOk'])->name('admin.users.resetOk');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
});



