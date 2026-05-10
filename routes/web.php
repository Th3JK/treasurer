<?php

Route::post('payments/{gateway}/webhook/{token}')->name('payments.webhook');
Route::get('payments/{gateway}/webhook/{token}')->name('payments.webhook');
