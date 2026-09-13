<?php

use Illuminate\Support\Facades\Route;

/*
| Sanctum's /sanctum/csrf-cookie route is registered automatically by the
| package's service provider. This file is otherwise unused: the frontend
| is a separate Next.js application and does not render Blade views here.
*/

Route::get('/', function () {
    return response()->json(['name' => config('app.name'), 'status' => 'ok']);
});
