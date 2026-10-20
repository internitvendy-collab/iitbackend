<?php

namespace App\Http\Requests;


class LegacyEndpointRequest extends ApiFormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('OPTIONS')) {
            return [];
        }

        $path = $this->endpointPath();
        if ($path === 'v1/librarian/shelves') {
            return self::shelfRules($this->method());
        }

        return self::rulesFor($path);
    }

    public function endpointPath(): string
    {
        $path = (string) ($this->route('path') ?: $this->path());
        return ltrim(preg_replace('#^api/#', '', $path), '/');
    }

    private static function rulesFor(string $path): array
    {
        return match ($path) {
            // Auth
            'v1/auth/login' => ['email' => ['required', 'email'], 'password' => ['required', 'string']],
            'v1/auth/register', 'v1/auth/register/otp', 'v1/auth/forgot-password', 'v1/auth/password/otp' => ['email' => ['required', 'email']],
            'v1/auth/register/verify', 'v1/auth/password/verify' => ['email' => ['required', 'email'], 'otp' => ['required', 'string']],
            'v1/auth/register/password' => ['email' => ['required', 'email'], 'new_password' => ['required', 'string', 'min:8']],
            'v1/auth/password/reset', 'v1/auth/reset-password' => ['email' => ['required', 'email'], 'otp' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8']],
            'v1/auth/change-password' => ['email' => ['required', 'email'], 'current_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8'], 'confirm_password' => ['required', 'same:new_password']],
            'v1/auth/profile' => ['email' => ['required', 'email']],
            'v1/auth/notifications' => ['email' => ['required', 'email'], 'limit' => ['nullable', 'integer', 'min:1'], 'unread_only' => ['nullable', 'boolean']],
            'v1/auth/notifications/read' => ['user_email' => ['required', 'email'], 'notification_id' => ['required_without:mark_all', 'integer', 'min:1'], 'mark_all' => ['nullable', 'boolean']],
            'v1/auth/notifications/delete' => ['user_email' => ['required', 'email'], 'notification_id' => ['required', 'integer', 'min:1']],
            'v1/auth/image', 'v1/images' => ['path' => ['required', 'string']],
            'v1/auth/profile/image' => ['email' => ['required', 'email'], 'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],

            // Books
            'v1/books/create' => ['title' => ['required', 'string'], 'author' => ['required', 'string'], 'isbn' => ['required', 'string'], 'copies_total' => ['nullable', 'integer', 'min:0'], 'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],
            'v1/books/update' => ['isbn' => ['required', 'string']],
            'v1/books/delete' => ['isbn' => ['required', 'string']],
            'v1/books/requests' => ['title' => ['required', 'string'], 'user_email' => ['required', 'email']],
            'v1/books/requests/approve', 'v1/books/requests/decline', 'v1/books/requests/details' => ['request_id' => ['required', 'integer', 'min:1']],
            'v1/reservations/cancel' => ['reservation_id' => ['required', 'integer', 'min:1']],
            'v1/reservations' => ['isbn' => ['required', 'string'], 'user_email' => ['required', 'email']],
            'v1/books/copies', 'v1/books/courses', 'v1/books/status' => ['isbn' => ['required', 'string']],
            'v1/reservations/list' => ['email' => ['required', 'email']],
            'v1/books/download' => ['isbn' => ['required', 'string']],
            'v1/books/pdf', 'v1/books/request-pdf' => ['pdf' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:51200']],
            'v1/books/cover' => ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],
            'v1/books/cover/update' => ['isbn' => ['required', 'string'], 'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],

            // Borrow
            'v1/borrow/requests' => ['isbn' => ['required', 'string', 'max:30']],
            'v1/borrow/requests/cancel' => ['user_email' => ['required_without:email', 'email'], 'email' => ['required_without:user_email', 'email'], 'request_id' => ['required_without:transaction_id', 'integer', 'min:1'], 'transaction_id' => ['required_without:request_id', 'integer', 'min:1']],
            'v1/borrow/transactions' => ['email' => ['required', 'email'], 'status' => ['nullable', 'in:all,borrowed,returned,reserved,pending']],
            'v1/borrow/returns' => ['transaction_id' => ['required', 'integer', 'min:1'], 'user_email' => ['required', 'email']],
            'v1/borrow/returns/complete' => ['transaction_id' => ['required_without:copy_id', 'integer', 'min:1'], 'copy_id' => ['required_without:transaction_id', 'string'], 'damage_fine' => ['nullable', 'numeric', 'min:0'], 'book_condition' => ['nullable', 'string']],

            // Courses
            'v1/courses/create', 'v1/courses/update' => ['course_id' => ['required', 'string'], 'course_name' => ['required', 'string'], 'semester' => ['nullable', 'string']],
            'v1/courses/delete' => ['course_id' => ['required', 'string']],

            // Librarian
            'v1/librarian/books/available-copies' => ['isbn' => ['required', 'string']],
            'v1/librarian/borrow-requests/approve' => ['request_id' => ['required', 'integer', 'min:1'], 'copy_id' => ['required', 'string']],
            'v1/librarian/return-requests/approve' => ['transaction_id' => ['required', 'integer', 'min:1']],
            'v1/librarian/borrow-requests/reject' => ['request_id' => ['required', 'integer', 'min:1']],
            'v1/librarian/requests' => ['type' => ['nullable', 'in:borrow,return,reserve,addition'], 'search' => ['nullable', 'string']],

            // Payments
            'v1/payments/fines', 'v1/payments/history' => ['user_email' => ['nullable', 'email'], 'email' => ['nullable', 'email'], 'startDate' => ['nullable', 'date'], 'endDate' => ['nullable', 'date']],
            'v1/payments/process' => ['user_email' => ['required', 'email'], 'fine_ids' => ['required_without_all:transaction_ids', 'array'], 'transaction_ids' => ['required_without_all:fine_ids', 'array'], 'payment_method' => ['nullable', 'string']],

            // Reports
            'v1/reports/generate' => [
                'report_type' => ['required', 'in:most_borrowed,most_requested,semester_wise,session_wise'],
                'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'],
                'semester' => ['nullable', 'string'], 'session' => ['nullable', 'string'],
                'format' => ['nullable', 'in:json,csv,pdf'],
            ],

            // Settings
            'v1/settings/update' => ['settings' => ['required', 'array']],

            default => [],
        };
    }

    private static function shelfRules(string $method): array
    {
        if (!in_array($method, ['POST', 'PUT'], true)) {
            return [];
        }

        return [
            'shelf_id' => ['required', 'integer', 'min:1'],
            'compartment' => ['required', 'integer', 'min:1'],
            'subcompartment' => ['required', 'integer', 'min:1'],
        ];
    }
}
