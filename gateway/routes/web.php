<?php

use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return view("welcome");
});

// Health check endpoint for Kubernetes probes
Route::get("/health", function () {
    return response("OK", 200);
});

