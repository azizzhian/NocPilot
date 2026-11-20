<?php

namespace App\Http\Controllers\Api;

/**
 * Events polling untuk halaman Gamas (channel terpisah dari Input Harian).
 */
class GamasEventsController extends DailyEntryEventsController
{
    protected const CHANNEL = 'gamas';
}
