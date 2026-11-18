<?php

use Illuminate\Support\Facades\Route;

// KUMA BANA est une API : aucune page web n'est servie par le backend.
Route::get('/', fn () => response()->json(['service' => 'KUMA BANA', 'api' => url('/api')]));
