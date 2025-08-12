<?php

use Illuminate\Support\Facades\Route;

// Include versioned routes
Route::prefix('v1')->group(base_path('routes/api/v1.php'));


