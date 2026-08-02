<?php

use App\Http\Controllers\Api\ActivationController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GenerateReportController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyEntryController;
use App\Http\Controllers\Api\DailyEntryEventsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DismantleController;
use App\Http\Controllers\Api\InternetPackageController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\OdcController;
use App\Http\Controllers\Api\OdpController;
use App\Http\Controllers\Api\OltController;
use App\Http\Controllers\Api\OnuController;
use App\Http\Controllers\Api\PopController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportTicketController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RouterController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/auth/telegram-config', [AuthController::class, 'telegramConfig']);
    Route::post('/auth/telegram', [AuthController::class, 'loginTelegram']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/nav-badges', [DashboardController::class, 'navBadges']);
        Route::get('/alerts', [AlertController::class, 'index']);

        // Monitoring MikroTik
        Route::prefix('monitoring')->group(function () {
            Route::get('/summary', [MonitoringController::class, 'summary']);
            Route::get('/routers', [MonitoringController::class, 'routers']);
            Route::get('/traffic-snapshot', [MonitoringController::class, 'trafficSnapshot']);
            Route::get('/routers/{router}', [MonitoringController::class, 'show']);
            Route::post('/routers/sync-all', [MonitoringController::class, 'syncAll']);
            Route::post('/routers/{router}/sync', [MonitoringController::class, 'sync']);
            Route::post('/routers/{router}/sync-interfaces', [MonitoringController::class, 'syncInterfaces']);
            Route::patch('/routers/{router}/interfaces/{interface}', [MonitoringController::class, 'updateInterface']);
            Route::get('/routers/{router}/live', [MonitoringController::class, 'live']);
            Route::get('/pops', [MonitoringController::class, 'pops']);
        });

        // Tickets removed — fokus Daily Report

        Route::get('/activations/stats', [ActivationController::class, 'stats']);
        Route::apiResource('activations', ActivationController::class);

        Route::get('/dismantles/stats', [DismantleController::class, 'stats']);
        Route::apiResource('dismantles', DismantleController::class);

        Route::get('/report-tickets/stats', [ReportTicketController::class, 'stats']);
        Route::get('/report-tickets/export', [ReportTicketController::class, 'export']);
        Route::apiResource('report-tickets', ReportTicketController::class);

        Route::get('/technicians', [UserController::class, 'technicians']);

        // Daily Entry (Input Harian)
        Route::prefix('daily-entry')->group(function () {
            Route::get('/events', [DailyEntryEventsController::class, 'index']);
            Route::get('/', [DailyEntryController::class, 'index']);
            Route::get('/customers/search', [DailyEntryController::class, 'searchCustomers']);
            Route::get('/complaint-history', [DailyEntryController::class, 'complaintHistory']);
            Route::get('/export/complaints', [DailyEntryController::class, 'exportComplaints']);
            Route::get('/export/noc-updates', [DailyEntryController::class, 'exportNocUpdates']);
            Route::get('/list/complaints', [DailyEntryController::class, 'listComplaints']);
            Route::get('/list/noc-updates', [DailyEntryController::class, 'listNocUpdates']);
            Route::get('/list/activations', [DailyEntryController::class, 'listActivations']);
            Route::get('/list/cctv', [DailyEntryController::class, 'listCctvSetups']);
            Route::post('/activation', [DailyEntryController::class, 'storeActivation']);
            Route::put('/activation/{dailyActivation}', [DailyEntryController::class, 'updateActivation']);
            Route::post('/cctv', [DailyEntryController::class, 'storeCctv']);
            Route::put('/cctv/{dailyCctv}', [DailyEntryController::class, 'updateCctv']);
            Route::post('/dismantle', [DailyEntryController::class, 'storeDismantle']);
            Route::put('/dismantle/{dailyDismantle}', [DailyEntryController::class, 'updateDismantle']);
            Route::post('/complaint', [DailyEntryController::class, 'storeComplaint']);
            Route::put('/complaint/{dailyComplaint}', [DailyEntryController::class, 'updateComplaint']);
            Route::post('/noc-update', [DailyEntryController::class, 'storeNocUpdate']);
            Route::put('/noc-update/{dailyNocUpdate}', [DailyEntryController::class, 'updateNocUpdate']);
            Route::delete('/{type}/{id}', [DailyEntryController::class, 'destroy']);
            Route::patch('/{type}/{id}/status', [DailyEntryController::class, 'updateStatus']);
        });

        // Activity & Realtime
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/realtime/feed', [RealtimeController::class, 'feed']);
        Route::post('/realtime/mark-read', [RealtimeController::class, 'markRead']);

        // Reports
        Route::get('/reports/analytics', [ReportController::class, 'analytics']);
        Route::get('/reports/export', [ReportController::class, 'export']);
        Route::prefix('reports/generate')->group(function () {
            Route::get('/', [GenerateReportController::class, 'index']);
            Route::post('/', [GenerateReportController::class, 'generate']);
            Route::get('/history', [GenerateReportController::class, 'history']);
            Route::get('/history/{snapshot}', [GenerateReportController::class, 'show']);
            Route::put('/templates', [GenerateReportController::class, 'updateTemplate']);
            Route::post('/templates/reset', [GenerateReportController::class, 'resetTemplate']);
        });

        Route::get('/customers/stats', [CustomerController::class, 'stats']);
        Route::get('/customers/export', [CustomerController::class, 'export']);
        Route::post('/customers/import', [CustomerController::class, 'import']);
        Route::get('/customers/history', [CustomerController::class, 'history']);
        Route::apiResource('customers', CustomerController::class);

        Route::get('/inventory/tree', [InventoryController::class, 'tree']);
        Route::get('/inventory/flat', [InventoryController::class, 'flat']);
        Route::get('/master-data', [MasterDataController::class, 'index']);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.manage');

        Route::post('/routers/{router}/test-connection', [RouterController::class, 'testConnection']);
        Route::apiResource('routers', RouterController::class);
        Route::apiResource('pops', PopController::class);
        Route::apiResource('odcs', OdcController::class);
        Route::apiResource('odps', OdpController::class);
        Route::apiResource('olts', OltController::class);
        Route::apiResource('onus', OnuController::class);
        Route::apiResource('packages', InternetPackageController::class);

        Route::middleware('permission:user.manage')->group(function () {
            Route::apiResource('users', UserController::class);
        });
    });
});
