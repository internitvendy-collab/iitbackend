<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LibrarianDirectorSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $users = [
            [
                'email' => 'librarian@iit.edu',
                'name' => 'Librarian',
                'password_hash' => Hash::make('123'),
                'role' => 'Librarian',
                'phone' => '01700000001',
                'email_verified_at' => $now,
                'last_login' => $now,
            ],
            [
                'email' => 'director@iit.edu',
                'name' => 'Director',
                'password_hash' => Hash::make('123'),
                'role' => 'Director',
                'phone' => '01700000002',
                'email_verified_at' => $now,
                'last_login' => $now,
            ],
        ];

        foreach ($users as $data) {
            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                $user = new User();
                $user->forceFill($data);
                $user->save();
            }
        }
    }
}
