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
| The previous /chat route referenced App\Http\Controllers\OpenAIController,
| which does not exist in this project. The active OpenAI controller lives
| under App\Http\Controllers\Guest and exposes inquire2(), not chat().
| Remove the stale API route so route discovery works cleanly.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
