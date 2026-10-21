<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LibrarianController extends Controller
{
    public function requests(LegacyEndpointRequest $request): JsonResponse
    {
        $type = $request->string('type')->toString();
        $search = trim($request->string('search')->toString());
        if ($type === 'borrow') {
            DB::table('Transaction_Requests')->where('status', 'Pending')->where('request_date', '<', now()->subHours(24))->delete();
            $query = DB::table('Transaction_Requests as tr')->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Books as b', 'b.isbn', '=', 'tr.isbn')
                ->where('tr.status', 'Pending');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('u.email', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%")->orWhere('tr.isbn', 'like', "%{$search}%"));
            $rows = $query->orderByDesc('tr.request_date')->get(['tr.request_id', 'tr.isbn', 'tr.request_date', 'u.name', 'u.email', 'b.title']);
            $items = $rows->map(function ($row): array {
                $minutes = max(0, (int) Carbon::parse($row->request_date)->addHours(24)->diffInMinutes(now(), false) * -1);
                return (array) $row + [
                    'hours_old' => (int) Carbon::parse($row->request_date)->diffInHours(now()),
                    'minutes_until_expiry' => $minutes,
                    'expires_in_hours' => (int) floor($minutes / 60),
                    'expires_in_minutes' => $minutes % 60,
                    'is_expired' => $minutes === 0,
                ];
            });
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'addition') {
            $query = DB::table('Requests as req')->leftJoin('Users as u', 'u.email', '=', 'req.requester_identifier')->where('req.status', 'Pending');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('req.requester_identifier', 'like', "%{$search}%")->orWhere('req.title', 'like', "%{$search}%")->orWhere('req.isbn', 'like', "%{$search}%"));
            $items = $query->orderByDesc('req.request_id')->get([
                'req.request_id', 'req.title as requested_title', 'req.isbn', 'req.requester_identifier as email',
                DB::raw("COALESCE(u.name, 'Unknown') as name"), 'req.status', 'req.approved_by', 'req.approved_at', 'req.description',
            ]);
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'reserve') {
            $isbns = DB::table('Reservations')->where('status', 'Active')->distinct()->pluck('isbn');
            foreach ($isbns as $isbn) $this->renumberQueue($isbn);
            $query = DB::table('Reservations as r')->join('Users as u', 'u.email', '=', 'r.user_email')->join('Books as b', 'b.isbn', '=', 'r.isbn')->where('r.status', 'Active');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('u.email', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%")->orWhere('r.isbn', 'like', "%{$search}%"));
            $items = $query->orderBy('r.isbn')->orderBy('r.queue_position')->orderBy('r.created_at')->get([
                'r.reservation_id', 'r.isbn', 'r.queue_position', 'r.status', 'r.created_at', 'u.name', 'u.email', 'b.title',
            ]);
            return ApiResponse::success(['count' => $items->count(), 'items' => $items]);
        }

        if ($type === 'return') {
            $items = [];
            foreach (DB::table('Notifications')->where('type', 'ReturnRequestPending')->orderByDesc('sent_at')->get(['user_email', 'message', 'sent_at']) as $notification) {
                if (!preg_match('/Transaction\s+#(\d+)/', $notification->message, $match)) continue;
                $row = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                    ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
                    ->join('Books as b', 'b.isbn', '=', 'bc.isbn')->where('at.transaction_id', (int) $match[1])->where('at.status', 'Borrowed')
                    ->first(['at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.due_date', 'tr.isbn', 'u.name', 'u.email', 'b.title']);
                if (!$row) continue;
                $row->requested_at = $notification->sent_at;
                $row->days_overdue = max(0, (int) Carbon::parse($row->due_date)->startOfDay()->diffInDays(now()->startOfDay()));
                if ($search !== '' && stripos(implode(' ', [(string) $row->name, (string) $row->email, (string) $row->title, (string) $row->isbn]), $search) === false) continue;
                $items[] = $row;
            }
            return ApiResponse::success(['count' => count($items), 'items' => $items]);
        }

        return ApiResponse::error('Invalid type', 400);
    }

    public function dashboard(): JsonResponse
    {
        $pendingReturnIds = [];
        foreach (DB::table('Notifications')->where('type', 'ReturnRequestPending')->pluck('message') as $message) {
            if (preg_match('/Transaction\s+#(\d+)/', $message, $match)) $pendingReturnIds[$match[1]] = true;
        }
        $recent = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->join('Books as b', 'b.isbn', '=', 'bc.isbn')->orderByDesc('at.issue_date')->limit(5)
            ->get(['at.transaction_id', 'at.copy_id', 'at.issue_date', 'at.return_date', 'at.status', 'u.name', 'tr.requester_email as email', 'b.title']);

        return ApiResponse::success([
            'stats' => [
                'total_books' => DB::table('Books')->where('title', 'not like', '[DELETED]%')->distinct('isbn')->count('isbn'),
                'pending_returns' => count($pendingReturnIds),
                'pending_requests' => DB::table('Transaction_Requests')->where('status', 'Pending')->count(),
                'fines_collected_today' => (float) DB::table('Fines')->where('paid', true)->whereDate('payment_date', today())->sum('amount'),
                'return_approvals' => count($pendingReturnIds),
                'new_book_requests' => DB::table('Requests')->where('status', 'Pending')->count(),
                'payment_verifications' => DB::table('Fines')->where('paid', false)->count(),
            ],
            'recent_activity' => $recent,
        ]);
    }

    public function listPreRegistrations(LegacyEndpointRequest $request): JsonResponse
    {
        $role = ucfirst(strtolower(trim((string) $request->input('role', 'Student'))));
        $tableByRole = [
            'Student' => 'prereg_students',
            'Teacher' => 'prereg_teachers',
            'Librarian' => 'prereg_librarians',
            'Director' => 'prereg_directors',
        ];

        $all = $request->boolean('all');
        $search = trim((string) $request->input('search', ''));
        $statusFilter = strtolower(trim((string) $request->input('status', 'all')));
        $limit = min(500, max(1, (int) $request->input('limit', 200)));

        $roles = $all ? $tableByRole : array_intersect_key($tableByRole, [$role => true]);
        if ($roles === []) {
            return ApiResponse::error('Select a valid pre-registration role.', 422);
        }

        // Real accounts already living in Users, keyed by lower(email).
        $registeredByRole = [];
        foreach ($tableByRole as $tableRole => $unused) {
            $registeredByRole[$tableRole] = DB::table('Users')
                ->whereRaw('LOWER(role) = ?', [strtolower($tableRole)])
                ->get(['email', 'name', 'role', 'contact', 'phone', 'created_at'])
                ->keyBy(static fn ($row) => strtolower((string) $row->email));
        }

        $items = [];
        $counts = [];
        $pendingCounts = [];

        foreach ($tableByRole as $tableRole => $table) {
            $counts[$tableRole] = DB::table($table)->count() + $registeredByRole[$tableRole]->count();
            $pendingCounts[$tableRole] = (clone DB::table($table))
                ->whereNotIn(DB::raw('LOWER(email)'), $registeredByRole[$tableRole]->keys()->all() ?: [''])
                ->count();
        }

        foreach ($roles as $tableRole => $table) {
            $registered = $registeredByRole[$tableRole];
            $seen = [];

            $query = DB::table($table);

            if ($search !== '') {
                $searchColumns = ['email', 'full_name', 'contact'];
                if ($tableRole === 'Student') {
                    $searchColumns[] = 'roll';
                }
                if ($tableRole === 'Teacher') {
                    $searchColumns[] = 'designation';
                }
                $query->where(function ($q) use ($search, $searchColumns): void {
                    foreach ($searchColumns as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }

            foreach ($query->orderBy('full_name')->limit($limit)->get() as $row) {
                $key = strtolower((string) $row->email);
                $seen[$key] = true;
                $isRegistered = $registered->has($key);

                if ($statusFilter === 'pending' && $isRegistered) continue;
                if ($statusFilter === 'registered' && !$isRegistered) continue;

                $items[] = array_merge((array) $row, [
                    'role' => $tableRole,
                    'status' => $isRegistered ? 'registered' : 'pending',
                    'source' => 'preregistration',
                ]);
            }

            // Accounts that were created directly (seeders, manual inserts) and
            // never went through the pre-registration list.
            foreach ($registered as $key => $user) {
                if (isset($seen[$key])) continue;
                if ($statusFilter === 'pending') continue;

                if ($search !== '') {
                    $haystack = strtolower(implode(' ', [
                        (string) $user->email,
                        (string) $user->name,
                        (string) $user->contact,
                        (string) $user->phone,
                    ]));
                    if (!str_contains($haystack, strtolower($search))) continue;
                }

                $items[] = [
                    'email' => $user->email,
                    'full_name' => $user->name,
                    'contact' => $user->contact ?: $user->phone,
                    'roll' => null,
                    'session' => null,
                    'designation' => null,
                    'role' => $tableRole,
                    'status' => 'registered',
                    'source' => 'users',
                ];
            }
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? '')));

        return ApiResponse::success([
            'role' => $role,
            'count' => count($items),
            'counts_by_role' => $counts,
            'pending_by_role' => $pendingCounts,
            'items' => array_slice($items, 0, $limit),
        ]);
    }

    public function importPreRegistrations(Request $request): JsonResponse
    {
        $file = $request->file('file');
        $role = ucfirst(strtolower(trim((string) $request->input('role'))));
        $tableByRole = [
            'Student' => 'prereg_students',
            'Teacher' => 'prereg_teachers',
            'Librarian' => 'prereg_librarians',
            'Director' => 'prereg_directors',
        ];

        if (!$file || !$file->isValid()) return ApiResponse::error('Please upload a valid CSV file.', 422);
        if ($file->getSize() > 5 * 1024 * 1024) return ApiResponse::error('CSV file must be smaller than 5 MB.', 422);
        if (!isset($tableByRole[$role])) return ApiResponse::error('Select a valid pre-registration role.', 422);

        $requiredHeaders = [
            'Student' => ['email', 'roll', 'full_name', 'contact', 'session'],
            'Teacher' => ['email', 'designation', 'full_name', 'contact'],
            'Librarian' => ['email', 'full_name', 'contact'],
            'Director' => ['email', 'full_name', 'contact'],
        ][$role];
        $handle = fopen($file->getRealPath(), 'rb');
        $headers = $handle ? fgetcsv($handle) : false;
        if (!$handle || $headers === false) return ApiResponse::error('The CSV file is empty.', 422);
        $headers = array_map(static fn ($header) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header))), $headers);
        if (array_diff($requiredHeaders, $headers) !== []) {
            fclose($handle);
            return ApiResponse::error('CSV headers must be: ' . implode(', ', $requiredHeaders), 422);
        }

        $connection = DB::connection();
        $table = $tableByRole[$role];
        $created = 0;
        $updated = 0;
        $errors = [];
        $lineNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($values) === 1 && trim((string) $values[0]) === '') continue;
            $row = [];
            foreach ($headers as $index => $header) $row[$header] = trim((string) ($values[$index] ?? ''));
            $email = strtolower($row['email'] ?? '');
            $missing = array_values(array_filter($requiredHeaders, static fn ($header) => $header !== 'contact' && ($row[$header] ?? '') === ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Row {$lineNumber}: invalid email.";
            elseif ($missing !== []) $errors[] = "Row {$lineNumber}: missing " . implode(', ', $missing) . '.';
            elseif ($role === 'Student' && $connection->table($table)->where('roll', $row['roll'])->where('email', '!=', $email)->exists()) $errors[] = "Row {$lineNumber}: roll already belongs to another student.";
            else {
                $data = ['email' => $email, 'full_name' => $row['full_name'], 'contact' => $row['contact'] ?: null];
                if ($role === 'Student') $data += ['roll' => $row['roll'], 'session' => $row['session'] ?: null];
                if ($role === 'Teacher') $data['designation'] = $row['designation'];
                $exists = $connection->table($table)->where('email', $email)->exists();
                $connection->table($table)->updateOrInsert(['email' => $email], $data);
                $exists ? $updated++ : $created++;
            }
        }
        fclose($handle);

        return ApiResponse::success([
            'role' => $role,
            'created' => $created,
            'updated' => $updated,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 100),
        ], 200, 'Pre-registration import completed.');
    }

    public function addPreRegistration(Request $request): JsonResponse
    {
        $role = ucfirst(strtolower(trim((string) $request->input('role'))));
        $tableByRole = [
            'Student' => 'prereg_students',
            'Teacher' => 'prereg_teachers',
            'Librarian' => 'prereg_librarians',
            'Director' => 'prereg_directors',
        ];
        if (!isset($tableByRole[$role])) return ApiResponse::error('Select a valid pre-registration role.', 422);

        $email = strtolower(trim((string) $request->input('email')));
        $fullName = trim((string) $request->input('full_name'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ApiResponse::error('Enter a valid email address.', 422);
        if ($fullName === '') return ApiResponse::error('Full name is required.', 422);
        if ($role === 'Student' && trim((string) $request->input('roll')) === '') return ApiResponse::error('Student roll is required.', 422);
        if ($role === 'Teacher' && trim((string) $request->input('designation')) === '') return ApiResponse::error('Teacher designation is required.', 422);

        $connection = DB::connection();
        $table = $tableByRole[$role];
        foreach ($tableByRole as $otherRole => $otherTable) {
            if ($otherRole !== $role && $connection->table($otherTable)->where('email', $email)->exists()) {
                return ApiResponse::error("This email is already pre-registered as {$otherRole}.", 422);
            }
        }

        $data = [
            'email' => $email,
            'full_name' => $fullName,
            'contact' => trim((string) $request->input('contact')) ?: null,
        ];
        if ($role === 'Student') $data += ['roll' => trim((string) $request->input('roll')), 'session' => trim((string) $request->input('session')) ?: null];
        if ($role === 'Teacher') $data['designation'] = trim((string) $request->input('designation'));

        $updated = $connection->table($table)->where('email', $email)->exists();
        $connection->table($table)->updateOrInsert(['email' => $email], $data);
        return ApiResponse::success([
            'role' => $role,
            'created' => $updated ? 0 : 1,
            'updated' => $updated ? 1 : 0,
            'failed' => 0,
            'errors' => [],
        ], 200, $updated ? 'Pre-registration updated.' : 'Pre-registration added.');
    }

    public function transactionHistory(LegacyEndpointRequest $request): JsonResponse
    {
        $filter = $request->string('filter')->toString() ?: 'All';
        $search = trim($request->string('search')->toString());
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $transactions = [];
        $base = function (string $dateColumn, string $type, string $status, bool $requireReturn = false) use ($search, $start, $end) {
            $query = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
                ->join('Users as u', 'u.email', '=', 'tr.requester_email')->join('Books as b', 'b.isbn', '=', 'tr.isbn');
            if ($type === 'Borrow') $query->whereIn('at.status', ['Borrowed', 'Returned']);
            else $query->where('at.status', 'Returned')->whereNotNull('at.return_date');
            if ($requireReturn) $query->whereNotNull('at.return_date');
            if ($search !== '') $query->where(fn ($q) => $q->where('u.email', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%"));
            if ($start) $query->whereDate($dateColumn, '>=', $start);
            if ($end) $query->whereDate($dateColumn, '<=', $end);
            return $query->get([
                DB::raw("'{$type}' as type"), 'b.title as book_title', 'u.email as user_id', 'u.name as user_name',
                DB::raw("DATE({$dateColumn}) as date"), DB::raw("DATE_FORMAT({$dateColumn}, '%h:%i %p') as time"), DB::raw("'{$status}' as status"),
            ]);
        };
        if (in_array($filter, ['All', 'Borrow'], true)) $transactions = array_merge($transactions, $base('at.issue_date', 'Borrow', 'Completed')->all());
        if (in_array($filter, ['All', 'Return'], true)) $transactions = array_merge($transactions, $base('at.return_date', 'Return', 'Completed', true)->all());

        $reservations = function () use ($search, $start, $end) {
            $query = DB::table('Reservations as r')->join('Users as u', 'u.email', '=', 'r.user_email')->join('Books as b', 'b.isbn', '=', 'r.isbn');
            if ($search !== '') $query->where(fn ($q) => $q->where('r.user_email', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%")->orWhere('b.title', 'like', "%{$search}%"));
            if ($start) $query->whereDate('r.created_at', '>=', $start);
            if ($end) $query->whereDate('r.created_at', '<=', $end);
            return $query->get([
                DB::raw("'Reservation' as type"), 'b.title as book_title', 'r.user_email as user_id', 'u.name as user_name',
                DB::raw('DATE(r.created_at) as date'), DB::raw("DATE_FORMAT(r.created_at, '%h:%i %p') as time"), 'r.status',
            ]);
        };
        if (in_array($filter, ['All', 'Reservation'], true)) $transactions = array_merge($transactions, $reservations()->all());

        usort($transactions, static fn ($left, $right) => strtotime($right->date) <=> strtotime($left->date));
        return ApiResponse::success(['count' => count($transactions), 'transactions' => $transactions]);
    }

    public function shelves(LegacyEndpointRequest $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return ApiResponse::success(['shelves' => DB::table('Shelves')->where('is_deleted', false)->orderBy('shelf_id')->get()]);
        }

        $compartment = $request->integer('compartment');
        $subcompartment = $request->integer('subcompartment');
        $shelfId = $request->filled('shelf_id') ? $request->integer('shelf_id') : null;

        if ($request->isMethod('POST')) {
            if ($shelfId !== null && DB::table('Shelves')->where('shelf_id', $shelfId)->exists()) {
                return ApiResponse::error(
                    "Shelf {$shelfId} already exists. Choose a different shelf number.",
                    400,
                );
            }

            try {
                $values = [
                    'compartment' => $compartment,
                    'subcompartment' => $subcompartment,
                    'is_deleted' => false,
                ];
                if ($shelfId !== null) {
                    $values['shelf_id'] = $shelfId;
                }

                $newShelfId = (int) DB::table('Shelves')->insertGetId($values, 'shelf_id');
            } catch (\Throwable $e) {
                return ApiResponse::error('Could not add the shelf. Please try again.', 400);
            }

            return ApiResponse::success([
                'shelf_id' => $newShelfId,
                'compartment' => $compartment,
                'subcompartment' => $subcompartment,
            ], 200, 'Shelf location added successfully');
        }

        if ($shelfId === null) {
            return ApiResponse::error('Shelf not found.', 404);
        }

        if ($request->isMethod('PUT')) {
            $updated = DB::table('Shelves')->where('shelf_id', $shelfId)->where('is_deleted', false)->update(['compartment' => $compartment, 'subcompartment' => $subcompartment]);
            if (!$updated && !DB::table('Shelves')->where('shelf_id', $shelfId)->exists()) return ApiResponse::error('Shelf not found.', 404);
            return ApiResponse::success([], 200, 'Shelf location updated successfully');
        }

        $deleted = DB::table('Shelves')->where('shelf_id', $shelfId)->update(['is_deleted' => true]);
        if (!$deleted) return ApiResponse::error('Shelf not found.', 404);
        return ApiResponse::success([], 200, 'Shelf deleted successfully');
    }

    public function syncStatus(): JsonResponse
    {
        return ApiResponse::success([
            'counts' => [
                'pending_borrow_requests' => DB::table('Transaction_Requests')->where('status', 'Pending')->count(),
                'borrowed_transactions' => DB::table('Approved_Transactions')->where('status', 'Borrowed')->count(),
                'active_reservations' => DB::table('Reservations')->where('status', 'Active')->count(),
                'pending_addition_requests' => DB::table('Requests')->where('status', 'Pending')->count(),
            ],
        ], 200, 'Database sync status');
    }

    public function approveReturnNotification(LegacyEndpointRequest $request): JsonResponse
    {
        $row = DB::table('Approved_Transactions as at')->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->where('at.transaction_id', $request->integer('transaction_id'))
            ->first(['tr.requester_email', 'b.title']);
        if (!$row) return ApiResponse::error('Transaction not found', 404);
        DB::table('Notifications')->insert([
            'user_email' => $row->requester_email,
            'message' => "Your return request for '{$row->title}' has been approved.",
            'type' => 'ReturnRequestApproved',
            'sent_at' => now(),
        ]);
        return ApiResponse::success([], 200, 'Return request approved');
    }

    private function renumberQueue(string $isbn): void
    {
        DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'Cancelled']);
        $rows = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->orderBy('queue_position')->orderBy('reservation_id')->get(['reservation_id', 'queue_position']);
        foreach ($rows as $index => $row) {
            if ((int) $row->queue_position !== $index + 1) DB::table('Reservations')->where('reservation_id', $row->reservation_id)->update(['queue_position' => $index + 1]);
        }
    }
}