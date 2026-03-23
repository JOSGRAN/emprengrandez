<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    $token = (string) env('HEALTHZ_TOKEN', '');
    $provided = (string) request()->header('X-Healthz-Token', '');

    if ($token === '' || ! hash_equals($token, $provided)) {
        return response()->json(['ok' => true]);
    }

    $checks = [];

    try {
        DB::connection()->select('select 1');
        $checks['db'] = true;
    } catch (Throwable $e) {
        $checks['db'] = false;
    }

    try {
        Redis::connection()->ping();
        $checks['redis'] = true;
    } catch (Throwable $e) {
        $checks['redis'] = false;
    }

    $ok = ! in_array(false, $checks, true);

    return response()->json([
        'ok' => $ok,
        'checks' => $checks,
    ], $ok ? 200 : 503);
});
