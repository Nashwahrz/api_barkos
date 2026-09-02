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
    DB::table('produk')
        ->where('dipromosikan', true)
        ->whereNotNull('dipromosikan_hingga')
        ->where('dipromosikan_hingga', '<', Carbon::now())
        ->update(['dipromosikan' => false]);

    DB::table('promosi')
        ->where('status', 'active')
        ->where('berakhir_pada', '<', Carbon::now())
        ->update(['status' => 'expired']);
})->everyMinute();
