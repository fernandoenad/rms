<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController as GuestHome;
use App\Http\Controllers\Guest\ApplicationController as GuestApplication;
use App\Http\Controllers\Guest\VacancyController as GuestVacancy;
use App\Http\Controllers\Guest\ApplicationReportController as GuestApplicationReport;
use App\Http\Controllers\Admin\HomeController as AdminHome;
use App\Http\Controllers\Admin\ApplicationController as AdminApplication;
use App\Http\Controllers\Admin\UserController as AdminUser;
use App\Http\Controllers\Admin\InquiryController as AdminInquiry;
use App\Http\Controllers\Admin\VacancyController as AdminVacancy;
use App\Http\Controllers\Admin\VacancyReportController as AdminVacancyReport;

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

// root route
Route::get('/', [GuestHome::class, 'index'])->name('guest.index');

// not yet applied
Route::get('/vacancies', [GuestVacancy::class, 'index'])->name('guest.vacancies.index');
Route::get('/vacancies/{vacancy}', [GuestVacancy::class, 'show'])->name('guest.vacancies.show');
Route::get('/vacancies/{vacancy}/apply', [GuestVacancy::class, 'apply'])->name('guest.vacancies.apply');

Route::get('/applications', [GuestApplication::class, 'index'])->name('guest.applications.index');

Route::get('/reports', [GuestApplicationReport::class, 'index'])->name('guest.reports.index');
Route::get('/reports/{office}', [GuestApplicationReport::class, 'show'])->name('guest.reports.show');

// already applied
Route::post('/applications/{vacancy}/apply', [GuestApplication::class, 'store'])->name('guest.applications.store');

Route::post('/applications', [GuestApplication::class, 'lookup'])->name('guest.applications.lookup');
Route::get('/applications/my', [GuestApplication::class, 'my'])->name('guest.applications.my');
Route::get('/applications/{application}', [GuestApplication::class, 'show'])->name('guest.applications.show');
Route::post('/applications/{application}/inquire', [GuestApplication::class, 'inquire'])->name('guest.applications.inquire');


// auth routes
Auth::routes(['register' => false]);

// user login
Route::group(['middleware' => ['active']], function () {
    Route::get('/admin', [AdminHome::class, 'index'])->name('admin.index');
    Route::get('/admin/get-notifications', [AdminHome::class, 'get_notifications'])->name('admin.get_notifications');
    Route::get('/admin/change_password', [AdminHome::class, 'change_password'])->name('admin.change_password');
    Route::put('/admin/change_password', [AdminHome::class, 'change_password_ok'])->name('admin.change_password_ok');

    Route::get('/admin/applications', [AdminApplication::class, 'index'])->name('admin.applications.index');
    Route::put('/admin/applications', [AdminApplication::class, 'search'])->name('admin.applications.search');
    Route::get('/admin/applications/create', [AdminApplication::class, 'create'])->name('admin.applications.create');
    Route::post('/admin/applications/', [AdminApplication::class, 'store'])->name('admin.applications.store');
    Route::post('/admin/applications/import', [AdminApplication::class, 'import'])->name('admin.applications.import');
    Route::get('/admin/applications/{application}', [AdminApplication::class, 'show'])->name('admin.applications.show');
    Route::get('/admin/applications/{application}/delete', [AdminApplication::class, 'delete'])->name('admin.applications.delete');
    Route::get('/admin/applications/{application}/edit', [AdminApplication::class, 'edit'])->name('admin.applications.edit');
    Route::get('/admin/applications/{application}/revert', [AdminApplication::class, 'revert'])->name('admin.applications.revert');
    Route::put('/admin/applications/{application}', [AdminApplication::class, 'update'])->name('admin.applications.update');
    Route::patch('/admin/applications/{application}', [AdminApplication::class, 'saveInquiry'])->name('admin.applications.saveInquiry');

    Route::get('/admin/applications/{vacancy}/show', [AdminApplication::class, 'vacancy_show'])->name('admin.applications.vacancy.show');
    Route::get('/admin/applications/{vacancy}/show/tagged', [AdminApplication::class, 'vacancy_show_tagged'])->name('admin.applications.vacancy.show.tagged');
    Route::get('/admin/applications/{vacancy}/show/carview', [AdminApplication::class, 'vacancy_show_carview'])->name('admin.applications.vacancy.show.carview');
    Route::get('/admin/applications/{vacancy}/show/carview2', [AdminApplication::class, 'vacancy_show_carview2'])->name('admin.applications.vacancy.show.carview2');

    Route::get('/admin/vacancies/reports', [AdminVacancyReport::class, 'index'])->name('admin.vacancies.reports.index');
    Route::get('/admin/vacancies/reports/nonassessed', [AdminVacancyReport::class, 'nonassessed'])->name('admin.vacancies.reports.nonassessed');
    Route::get('/admin/vacancies/reports/list', [AdminVacancyReport::class, 'list'])->name('admin.vacancies.reports.list');
    Route::get('/admin/vacancies/reports/list/{application}/assess', [AdminVacancyReport::class, 'assess'])->name('admin.vacancies.reports.assess');
    Route::get('/admin/vacancies/reports/{office}', [AdminVacancyReport::class, 'show'])->name('admin.vacancies.reports.show');

    Route::get('/admin/vacancies/active', [AdminVacancy::class, 'active'])->name('admin.vacancies.active');

    Route::get('/admin/vacancies', [AdminVacancy::class, 'index'])->name('admin.vacancies.index');
    Route::get('/admin/vacancies/create', [AdminVacancy::class, 'create'])->name('admin.vacancies.create');
    Route::post('/admin/vacancies', [AdminVacancy::class, 'store'])->name('admin.vacancies.store');
    Route::get('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'edit'])->name('admin.vacancies.edit');
    Route::put('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'update'])->name('admin.vacancies.update');
    Route::get('/admin/vacancies/{vacancy}/delete', [AdminVacancy::class, 'delete'])->name('admin.vacancies.delete');

    Route::get('/admin/inquiries', [AdminInquiry::class, 'index'])->name('admin.inquiries.index');
});

Route::group(['middleware' => ['admin']], function () {
    Route::delete('/admin/applications/{application}', [AdminApplication::class, 'destroy'])->name('admin.applications.destroy');
    Route::delete('/admin/vacancies/{vacancy}', [AdminVacancy::class, 'destroy'])->name('admin.vacancies.destroy');

    Route::get('/admin/users', [AdminUser::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [AdminUser::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [AdminUser::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/delete', [AdminUser::class, 'delete'])->name('admin.users.delete');
    Route::get('/admin/users/{user}/reset', [AdminUser::class, 'reset'])->name('admin.users.reset');
    Route::put('/admin/users/{user}/reset', [AdminUser::class, 'resetOk'])->name('admin.users.resetOk');
    Route::delete('/admin/users/{user}', [AdminUser::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/users/{user}/edit', [AdminUser::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [AdminUser::class, 'update'])->name('admin.users.update');
});



