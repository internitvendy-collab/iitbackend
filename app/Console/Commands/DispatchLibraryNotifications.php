<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchLibraryNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--type=all : all|due|fines|reservations}';

    protected $description = 'Create due-date, fine-limit and reservation notifications';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $created = 0;

        if (in_array($type, ['all', 'due'], true)) {
            $created += $this->dueReminders();
        }

        if (in_array($type, ['all', 'fines'], true)) {
            $created += $this->fineLimitReminders();
        }

        if (in_array($type, ['all', 'reservations'], true)) {
            $created += $this->reservationExpiry();
        }

        $this->info("Created {$created} notification(s).");

        return self::SUCCESS;
    }

    private function dueReminders(): int
    {
        $created = 0;

        $rows = DB::table('Approved_Transactions as at')
            ->join('Transaction_Requests as tr', 'tr.request_id', '=', 'at.request_id')
            ->join('Book_Copies as bc', 'bc.copy_id', '=', 'at.copy_id')
            ->join('Books as b', 'b.isbn', '=', 'bc.isbn')
            ->whereIn('at.status', ['Borrowed', 'Overdue'])
            ->whereNull('at.return_date')
            ->whereDate('at.due_date', '>=', now()->toDateString())
            ->whereDate('at.due_date', '<=', now()->addDays(2)->toDateString())
            ->get(['tr.requester_email', 'b.title', 'at.due_date']);

        foreach ($rows as $row) {
            $dueDate = \Illuminate\Support\Carbon::parse($row->due_date);
            $isToday = $dueDate->isToday();
            $notifType = $isToday ? 'DueDateToday' : 'DueDateReminder';
            $email = (string) $row->requester_email;

            if ($this->alreadySent($email, $notifType)) {
                continue;
            }

            $when = $isToday
                ? 'TODAY (' . $dueDate->format('M d, Y') . ')'
                : 'in ' . max(1, (int) now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay())) . ' day(s) (' . $dueDate->format('M d, Y') . ')';

            $this->insert($email, "Reminder: your borrowed book '{$row->title}' is due {$when}. Please return it to avoid late fees.", $notifType);
            $created++;
        }

        return $created;
    }

    private function fineLimitReminders(): int
    {
        $created = 0;

        $rows = DB::table('Fines')
            ->select('user_email', DB::raw('SUM(amount) as total'))
            ->where('paid', false)
            ->groupBy('user_email')
            ->havingRaw('SUM(amount) >= ?', [200])
            ->get();

        foreach ($rows as $row) {
            $email = (string) $row->user_email;
            if ($this->alreadySent($email, 'FineLimitReached', now()->subDay())) {
                continue;
            }

            $this->insert(
                $email,
                'Your total unpaid fines (' . number_format((float) $row->total, 2) . ' TK) have exceeded the limit. Please pay your fines to continue borrowing books.',
                'FineLimitReached',
            );
            $created++;
        }

        return $created;
    }

    private function reservationExpiry(): int
    {
        $created = 0;

        $expired = DB::table('Reservations')
            ->where('status', 'Active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get(['reservation_id', 'user_email', 'isbn']);

        foreach ($expired as $row) {
            DB::table('Reservations')->where('reservation_id', $row->reservation_id)->update(['status' => 'Expired']);

            $title = DB::table('Books')->where('isbn', $row->isbn)->value('title') ?? $row->isbn;
            $this->insert(
                (string) $row->user_email,
                "You missed your reservation pickup window for '{$title}'. The book is now available for others.",
                'ReservationMissed',
            );
            $created++;
        }

        return $created;
    }

    private function alreadySent(string $email, string $type, ?\DateTimeInterface $since = null): bool
    {
        return DB::table('Notifications')
            ->where('user_email', $email)
            ->where('type', $type)
            ->where('sent_at', '>=', $since ?? now()->subHours(20))
            ->exists();
    }

    private function insert(string $email, string $message, string $type): void
    {
        DB::table('Notifications')->insert([
            'user_email' => $email,
            'message' => $message,
            'type' => $type,
            'isRead' => false,
            'is_read' => false,
            'sent_at' => now(),
        ]);
    }
}
