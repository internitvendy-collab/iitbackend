<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LibrarySettingsController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\LibrarianController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (v1 only)
|--------------------------------------------------------------------------
| Every endpoint lives under /api/v1. No legacy versionless routes remain.
*/

Route::prefix('v1')->group(function () {
    Route::get('/', fn () => response()->json(['message' => 'API Server Running', 'version' => 'v1']));

    // ---------------------------------------------------------------- Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/register', [AuthController::class, 'sendRegistrationOtp'])->middleware('throttle:5,1');
    Route::post('/auth/register/otp', [AuthController::class, 'sendRegistrationOtp'])->middleware('throttle:5,1');
    Route::post('/auth/register/verify', [AuthController::class, 'verifyRegistrationOtp'])->middleware('throttle:10,1');
    Route::post('/auth/register/password', [AuthController::class, 'setRegistrationPassword'])->middleware('throttle:10,1');
    Route::post('/auth/password/otp', [AuthController::class, 'sendPasswordResetOtp'])->middleware('throttle:5,1');
    Route::post('/auth/password/verify', [AuthController::class, 'verifyPasswordResetOtp'])->middleware('throttle:10,1');
    Route::post('/auth/password/reset', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::get('/auth/image', [AuthController::class, 'getImage']);
    Route::get('/images', [AuthController::class, 'serveImage']);

    Route::middleware('api.token')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/session', [AuthController::class, 'session']);

        Route::middleware('request.user')->group(function () {
            Route::post('/auth/change-password', [AuthController::class, 'changePasswordLegacy']);
            Route::match(['GET', 'POST'], '/auth/profile', [AuthController::class, 'getProfile']);
            Route::match(['POST', 'PUT'], '/auth/profile/update', [AuthController::class, 'updateProfile']);
            Route::post('/auth/profile/image', [AuthController::class, 'uploadProfileImage']);
            Route::get('/auth/notifications', [AuthController::class, 'getNotifications']);
            Route::post('/auth/notifications/read', [AuthController::class, 'markNotificationRead']);
            Route::post('/auth/notifications/delete', [AuthController::class, 'deleteNotification']);
            Route::get('/auth/dashboard', [AuthController::class, 'studentDashboard']);
        });

        Route::post('/auth/notifications/send', [AuthController::class, 'sendReminders'])
            ->middleware('role:Librarian,Director');
    });

    // --------------------------------------------------------------- Books
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/categories', [BookController::class, 'categories']);
    Route::get('/books/copies', [BookController::class, 'copies'])->middleware('api.token');
    Route::match(['GET', 'POST'], '/books/courses', [BookController::class, 'courses'])->middleware('api.token');
    Route::get('/books/status', [BookController::class, 'status'])->middleware('api.token');
    Route::get('/books/download', [BookController::class, 'downloadPdf']);

    Route::middleware(['api.token', 'role:Librarian,Director'])->group(function () {
        Route::post('/books/create', [BookController::class, 'store']);
        Route::match(['POST', 'PUT'], '/books/update', [BookController::class, 'update']);
        Route::match(['POST', 'DELETE'], '/books/delete', [BookController::class, 'destroy']);
        Route::post('/books/pdf', [BookController::class, 'uploadPdf']);
        Route::post('/books/request-pdf', [BookController::class, 'uploadRequestPdf']);
        Route::post('/books/cover', [BookController::class, 'uploadCoverImage']);
        Route::post('/books/cover/update', [BookController::class, 'updateCoverImage']);
        Route::get('/librarian/books/available-copies', [BookController::class, 'availableCopies']);
        Route::post('/books/requests/update', [BookController::class, 'updateAdditionRequest']);
        Route::post('/books/requests/approve', [BookController::class, 'approveAdditionRequest']);
        Route::post('/books/requests/decline', [BookController::class, 'declineAdditionRequest']);
    });

    Route::get('/books/requests/details', [BookController::class, 'additionRequestDetails'])->middleware('api.token');

    Route::post('/books/requests', [BookController::class, 'requestAddition'])
        ->middleware(['api.token', 'request.user']);

    // ---------------------------------------------------------- Reservations
    Route::post('/reservations', [BorrowController::class, 'reserve'])
        ->middleware(['api.token', 'request.user']);
    Route::match(['POST', 'DELETE'], '/reservations/cancel', [BorrowController::class, 'cancelReservation'])
        ->middleware(['api.token', 'request.user']);
    Route::match(['GET', 'POST'], '/reservations/list', [BorrowController::class, 'userReservations'])
        ->middleware(['api.token', 'request.user']);

    // -------------------------------------------------------------- Borrow
    Route::middleware(['api.token', 'request.user'])->group(function () {
        Route::post('/borrow/requests', [BorrowController::class, 'request']);
        Route::post('/borrow/returns', [BorrowController::class, 'requestReturn']);
        Route::post('/borrow/returns/complete', [BorrowController::class, 'returnBook']);
        Route::match(['POST', 'DELETE'], '/borrow/requests/cancel', [BorrowController::class, 'cancelRequest']);
        Route::match(['GET', 'POST'], '/borrow/transactions', [BorrowController::class, 'userTransactions']);
    });

    // ----------------------------------------------------------- Librarian
    Route::middleware(['api.token', 'role:Librarian,Director'])->group(function () {
        Route::post('/librarian/borrow-requests/approve', [BorrowController::class, 'approveBorrowRequest']);
        Route::post('/librarian/borrow-requests/reject', [BorrowController::class, 'rejectBorrowRequest']);
        Route::post('/librarian/return-requests/approve', [LibrarianController::class, 'approveReturnNotification']);
        Route::get('/librarian/dashboard', [LibrarianController::class, 'dashboard']);
        Route::get('/librarian/requests', [LibrarianController::class, 'requests']);
        Route::get('/librarian/requests/sync', [LibrarianController::class, 'syncStatus']);
        Route::get('/librarian/transactions', [LibrarianController::class, 'transactionHistory']);
        Route::match(['GET', 'POST', 'PUT', 'DELETE'], '/librarian/shelves', [LibrarianController::class, 'shelves']);
        Route::post('/librarian/preregistrations', [LibrarianController::class, 'addPreRegistration']);
        Route::post('/librarian/preregistrations/import', [LibrarianController::class, 'importPreRegistrations']);
    });

    // ------------------------------------------------------------- Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/search', [CourseController::class, 'index']);
    Route::middleware(['api.token', 'role:Librarian,Director'])->group(function () {
        Route::post('/courses/create', [CourseController::class, 'store']);
        Route::match(['POST', 'PUT'], '/courses/update', [CourseController::class, 'update']);
        Route::match(['POST', 'DELETE'], '/courses/delete', [CourseController::class, 'destroy']);
    });

    // ------------------------------------------------------------ Payments
    Route::middleware(['api.token', 'request.user'])->group(function () {
        Route::get('/payments/fines', [PaymentController::class, 'fines']);
        Route::match(['GET', 'POST'], '/payments/history', [PaymentController::class, 'history']);
        Route::post('/payments/process', [PaymentController::class, 'process']);
        Route::match(['GET', 'POST'], '/payments/overdue', [PaymentController::class, 'checkOverdueBooks']);
    });

    // ------------------------------------------------------------- Reports
    Route::post('/reports/generate', [ReportController::class, 'generate'])
        ->middleware(['api.token', 'role:Librarian,Director']);

    // ------------------------------------------------------------ Settings
    Route::get('/settings', [LibrarySettingsController::class, 'index']);
    Route::match(['POST', 'PUT'], '/settings/update', [LibrarySettingsController::class, 'update'])
        ->middleware(['api.token', 'role:Librarian,Director']);

    // ------------------------------------------------------- Public notices
    // Readable without a token: this powers the bell on the login screen.
    Route::get('/notices', [NoticeController::class, 'index']);

    Route::middleware(['api.token', 'role:Librarian,Director'])->group(function () {
        Route::get('/notices/manage', [NoticeController::class, 'manage']);
        Route::post('/notices/create', [NoticeController::class, 'store']);
        Route::match(['POST', 'PUT'], '/notices/update', [NoticeController::class, 'update']);
        Route::match(['POST', 'DELETE'], '/notices/delete', [NoticeController::class, 'destroy']);
    });

    // Keep the wildcard book route last so it cannot shadow static paths.
    Route::get('/books/{book}', [BookController::class, 'show']);
});
