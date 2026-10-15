<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BusinessTime
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.business_timezone', 'Asia/Jakarta'));
    }

    public static function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, config('app.business_timezone', 'Asia/Jakarta'))->startOfDay();
    }

    public static function endOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, config('app.business_timezone', 'Asia/Jakarta'))->endOfDay();
    }

    public static function utcStartOfDay(string $date): CarbonImmutable
    {
        return self::startOfDay($date)->utc();
    }

    public static function utcEndOfDay(string $date): CarbonImmutable
    {
        return self::endOfDay($date)->utc();
    }
}
