<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\AdminController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:api')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('/users', [AuthController::class, 'users']);


    Route::get('/todos/trash', [TodoController::class, 'trash']);
    Route::get('/todos', [TodoController::class, 'index']);
    Route::post('/todos', [TodoController::class, 'store']);
    Route::get('/todos/{id}', [TodoController::class, 'show']);
    Route::put('/todos/{id}', [TodoController::class, 'update']);
    Route::delete('/todos/{id}', [TodoController::class, 'destroy']);
    Route::delete('/todos/{id}/force', [TodoController::class, 'forceDelete']);
    Route::post('/todos/{id}/restore', [TodoController::class, 'restore']);

});

Route::middleware(['auth:api', 'admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/todos', [AdminController::class, 'todos']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
    Route::delete('/admin/todos/{id}', [AdminController::class, 'deleteTodo']);
});
