Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/stats', [App\Http\Controllers\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/api/charts/{period?}', [App\Http\Controllers\Admin\DashboardController::class, 'charts'])->name('dashboard.charts');
    Route::get('/api/funnel', [App\Http\Controllers\Admin\DashboardController::class, 'funnel'])->name('dashboard.funnel');

    /* ReportCopilot routes - AI natural language reporting assistant */
    Route::get('/report/analyze', [App\Http\Controllers\Admin\ReportCopilotController::class, 'analyze'])->name('report.analyze');
    Route::get('/report/executive-summary', [App\Http\Controllers\Admin\ReportCopilotController::class, 'executiveSummary'])->name('report.executive_summary');
    Route::get('/report/compare', [App\Http\Controllers\Admin\ReportCopilotController::class, 'comparePeriods'])->name('report.compare');
});

    /* Budget Alert Routes */
    Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/budget-alerts/check', [App\Http\Controllers\Admin\BudgetAlertController::class, 'checkAlerts'])->name('budget-alerts.check');
        Route::post('/budget-alerts/{id}/acknowledge', [App\Http\Controllers\Admin\BudgetAlertController::class, 'acknowledgeAlert'])->name('budget-alerts.acknowledge');
        Route::post('/budget-alerts/{id}/resolve', [App\Http\Controllers\Admin\BudgetAlertController::class, 'resolveAlert'])->name('budget-alerts.resolve');
        Route::get('/budget-alerts', [App\Http\Controllers\Admin\BudgetAlertController::class, 'listAlerts'])->name('budget-alerts.list');
    });