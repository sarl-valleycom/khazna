<?php

use App\Http\Controllers\Accounts\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OfficeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Attendance\AttendanceCorrectionController;
use App\Http\Controllers\Attendance\AttendancePresenceController;
use App\Http\Controllers\Attendance\AttendanceSessionController;
use App\Http\Controllers\Attendance\AttendanceSettingsController;
use App\Http\Controllers\Attendance\AttendanceSummaryController;
use App\Http\Controllers\Attendance\HolidayController;
use App\Http\Controllers\Attendance\LeaveController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientImportController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\MilestoneController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dev\DevUiController;
use App\Http\Controllers\Dues\DueController;
use App\Http\Controllers\Dues\DueReminderController;
use App\Http\Controllers\Employees\AttendanceCodeController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\Employees\SalaryController;
use App\Http\Controllers\Exports\ClientsExportController;
use App\Http\Controllers\Exports\ClientStatementController;
use App\Http\Controllers\Exports\ContractsExportController;
use App\Http\Controllers\Exports\DuesExportController;
use App\Http\Controllers\Exports\MonthlySummaryPdfController;
use App\Http\Controllers\Exports\PayslipController;
use App\Http\Controllers\Exports\SessionsExportController;
use App\Http\Controllers\Forecast\ForecastController;
use App\Http\Controllers\Ledger\ManualEntryController;
use App\Http\Controllers\Ledger\TransferController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payroll\AdjustmentController;
use App\Http\Controllers\Payroll\CloseController;
use App\Http\Controllers\Reconciliation\ReconciliationController;
use App\Http\Controllers\Reports\CashReportController;
use App\Http\Controllers\Suppliers\SupplierController;
use App\Http\Controllers\Triage\TriageController;
use App\Http\Controllers\Work\WorkController;
use App\Http\Middleware\EnsureDevToolsEnabled;
use Illuminate\Support\Facades\Route;

// The employee page: no login, a personal code instead (see WorkController).
Route::prefix('work')->name('work.')->group(function () {
    Route::get('/', [WorkController::class, 'show'])->name('show');
    Route::post('enter', [WorkController::class, 'enter'])->name('enter');
    Route::post('leave', [WorkController::class, 'leave'])->name('leave');
    Route::post('check-in', [WorkController::class, 'checkIn'])->name('check-in');
    Route::post('check-out', [WorkController::class, 'checkOut'])->name('check-out');
    Route::post('break', [WorkController::class, 'startBreak'])->name('break');
    Route::post('back', [WorkController::class, 'endBreak'])->name('back');
    Route::post('sessions/{attendanceSession}/report', [WorkController::class, 'report'])->whereNumber('attendanceSession')->name('report');
    Route::post('sessions/{attendanceSession}/correct', [WorkController::class, 'correct'])->whereNumber('attendanceSession')->name('correct');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', TriageController::class)->name('home');
    Route::get('dues/export', DuesExportController::class)->name('dues.export');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/export', ClientsExportController::class)->name('clients.export');
    Route::get('clients/import', [ClientImportController::class, 'create'])->name('clients.import');
    Route::post('clients/import/preview', [ClientImportController::class, 'preview'])->name('clients.import.preview');
    Route::get('clients/import/template', [ClientImportController::class, 'template'])->name('clients.import.template');
    Route::get('clients/import/{token}', [ClientImportController::class, 'showPreview'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('clients.import.show');
    Route::post('clients/import/confirm', [ClientImportController::class, 'confirm'])->name('clients.import.confirm');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::post('clients/{client}/dues', [DueController::class, 'store'])->whereNumber('client')->name('dues.store');
    Route::get('clients/{client}/statement', ClientStatementController::class)->whereNumber('client')->name('clients.statement');
    Route::get('clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::patch('clients/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');

    Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('contracts/export', ContractsExportController::class)->name('contracts.export');
    Route::post('contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('contracts/{contract}/attachment', [ContractController::class, 'attachment'])->whereNumber('contract')->name('contracts.attachment');
    Route::patch('contracts/{contract}/status', [ContractController::class, 'status'])->whereNumber('contract')->name('contracts.status');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
    Route::patch('contracts/{contract}', [ContractController::class, 'update'])->whereNumber('contract')->name('contracts.update');

    Route::post('contracts/{contract}/milestones/preview', [MilestoneController::class, 'preview'])->whereNumber('contract')->name('contracts.milestones.preview');
    Route::put('contracts/{contract}/milestones', [MilestoneController::class, 'update'])->whereNumber('contract')->name('contracts.milestones.update');
    Route::post('contracts/{contract}/milestones/{milestone}/deliver', [MilestoneController::class, 'deliver'])->whereNumber(['contract', 'milestone'])->name('contracts.milestones.deliver');
    Route::post('contracts/{contract}/milestones/{milestone}/request', [MilestoneController::class, 'request'])->whereNumber(['contract', 'milestone'])->name('contracts.milestones.request');

    Route::patch('dues/{due}/postpone', [DueController::class, 'postpone'])->whereNumber('due')->name('dues.postpone');
    Route::post('dues/{due}/reminders', [DueReminderController::class, 'store'])->whereNumber('due')->name('dues.reminders.store');
    Route::patch('dues/{due}/cancel', [DueController::class, 'cancel'])->whereNumber('due')->name('dues.cancel');

    Route::post('dues/{due}/payments', [PaymentController::class, 'store'])->whereNumber('due')->name('payments.store');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->whereNumber('payment')->name('payments.reverse');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');

    // Literal paths first, so "transfers" and "entries" are never read as an account id.
    Route::get('accounts/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('accounts/transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('accounts/transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->whereNumber('transfer')->name('transfers.reverse');
    Route::get('accounts/reconcile', [ReconciliationController::class, 'index'])->name('reconciliations.index');
    Route::post('accounts/reconcile', [ReconciliationController::class, 'store'])->name('reconciliations.store');
    Route::post('accounts/reconcile/{reconciliation}/adjust', [ReconciliationController::class, 'adjust'])->whereNumber('reconciliation')->name('reconciliations.adjust');
    Route::get('accounts/entries', [ManualEntryController::class, 'index'])->name('entries.index');
    Route::post('accounts/entries', [ManualEntryController::class, 'store'])->name('entries.store');
    Route::post('accounts/entries/{entry}/reverse', [ManualEntryController::class, 'reverse'])->whereNumber('entry')->name('entries.reverse');
    Route::get('accounts/entries/{entry}/receipt', [ManualEntryController::class, 'receipt'])->whereNumber('entry')->name('entries.receipt');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->whereNumber('account')->name('accounts.show');
    Route::patch('accounts/{account}', [AccountController::class, 'update'])->whereNumber('account')->name('accounts.update');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
    Route::patch('employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
    Route::get('employees/{employee}/payslip', PayslipController::class)->whereNumber('employee')->name('employees.payslip');
    Route::post('employees/{employee}/attendance-code', [AttendanceCodeController::class, 'store'])->whereNumber('employee')->name('employees.attendance-code.store');
    Route::delete('employees/{employee}/attendance-code', [AttendanceCodeController::class, 'destroy'])->whereNumber('employee')->name('employees.attendance-code.destroy');

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('sessions', [AttendanceSessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/export', SessionsExportController::class)->name('sessions.export');
        Route::post('sessions', [AttendanceSessionController::class, 'store'])->name('sessions.store');
        Route::patch('sessions/{attendanceSession}', [AttendanceSessionController::class, 'update'])->whereNumber('attendanceSession')->name('sessions.update');
        Route::delete('sessions/{attendanceSession}', [AttendanceSessionController::class, 'destroy'])->whereNumber('attendanceSession')->name('sessions.destroy');
        Route::post('sessions/{attendanceSession}/approve', [AttendanceSessionController::class, 'approve'])->whereNumber('attendanceSession')->name('sessions.approve');

        Route::get('presence', AttendancePresenceController::class)->name('presence.index');

        Route::get('summary', AttendanceSummaryController::class)->name('summary.index');

        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->whereNumber('holiday')->name('holidays.destroy');

        Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
        Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::delete('leave/{leave}', [LeaveController::class, 'destroy'])->whereNumber('leave')->name('leave.destroy');

        Route::get('corrections', [AttendanceCorrectionController::class, 'index'])->name('corrections.index');
        Route::patch('corrections/{correctionRequest}/approve', [AttendanceCorrectionController::class, 'approve'])->whereNumber('correctionRequest')->name('corrections.approve');
        Route::patch('corrections/{correctionRequest}/reject', [AttendanceCorrectionController::class, 'reject'])->whereNumber('correctionRequest')->name('corrections.reject');

        Route::get('settings', [AttendanceSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [AttendanceSettingsController::class, 'update'])->name('settings.update');
    });

    // Everything about what people earn sits behind one gate.
    Route::middleware('can:view-salaries')->group(function () {
        Route::post('employees/{employee}/salaries', [SalaryController::class, 'store'])->whereNumber('employee')->name('employees.salaries.store');

        Route::get('payroll/adjustments', [AdjustmentController::class, 'index'])->name('payroll.adjustments');
        Route::post('payroll/adjustments', [AdjustmentController::class, 'store'])->name('payroll.adjustments.store');
        Route::get('payroll/close', [CloseController::class, 'show'])->name('payroll.close');
        Route::post('payroll/close', [CloseController::class, 'store'])->name('payroll.close.store');
    });

    Route::get('forecast', ForecastController::class)->middleware('can:view-forecast')->name('forecast');
    Route::get('reports/cash', CashReportController::class)->middleware('can:view-reports')->name('reports.cash');
    Route::get('reports/monthly-summary', MonthlySummaryPdfController::class)->middleware('can:view-reports')->name('reports.monthly-summary');

    Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->whereNumber('supplier')->name('suppliers.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');

    Route::get('offices', [OfficeController::class, 'index'])->name('offices.index');
    Route::post('offices', [OfficeController::class, 'store'])->name('offices.store');
    Route::patch('offices/{office}', [OfficeController::class, 'update'])->name('offices.update');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/password-reset', [UserController::class, 'resetPassword'])->name('users.password-reset');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('audit-log', AuditLogController::class)->name('audit-log.index');
});

Route::middleware([EnsureDevToolsEnabled::class, 'auth', 'verified', 'can:view-dev-tools'])->group(function () {
    Route::get('dev/ui', DevUiController::class)->name('dev.ui');
});

require __DIR__.'/settings.php';
