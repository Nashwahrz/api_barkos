<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 6.1 - Auto demote expired promotions
Schedule::call(function () {
    DB::table('products')
        ->where('is_promoted', true)
        ->whereNotNull('promoted_until')
        ->where('promoted_until', '<', Carbon::now())
        ->update(['is_promoted' => false]);
    
    DB::table('promotions')
        ->where('status', 'active')
        ->where('end_date', '<', Carbon::now())
        ->update(['status' => 'expired']);
})->everyMinute();
