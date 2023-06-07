<?php

use Illuminate\Support\Facades\Route;
use Mamdouh\TenancyModules\Http\Controllers\ModuleController;

Route::resource('modules', ModuleController::class);