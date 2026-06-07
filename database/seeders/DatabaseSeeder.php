<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            // Admin
            [
                'user_id' => 1,
                'name' => 'Admin Athletica',
                'email' => 'admin@athletica.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Trainer
            [
                'user_id' => 2,
                'name' => 'Sarah Wijaya',
                'email' => 'sarah@athletica.com',
                'password' => Hash::make('password123'),
                'role' => 'trainer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 3,
                'name' => 'Mike Pratama',
                'email' => 'mike@athletica.com',
                'password' => Hash::make('password123'),
                'role' => 'trainer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 4,
                'name' => 'Alex Budiman',
                'email' => 'alex@athletica.com',
                'password' => Hash::make('password123'),
                'role' => 'trainer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // User biasa
            [
                'user_id' => 5,
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 6,
                'name' => 'Citra Lestari',
                'email' => 'citra@example.com',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 7,
                'name' => 'Dian Permata',
                'email' => 'dian@example.com',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('classes')->insert([
            [
                'class_id' => 1,
                'class_name' => 'Yoga',
                'description' => 'Yoga untuk relaksasi dan fleksibilitas tubuh. Cocok untuk pemula hingga mahir.',
                'price' => 100000,
                'capacity' => 15,
                'image' => 'classes/yoga.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'class_id' => 2,
                'class_name' => 'Zumba',
                'description' => 'Senam aerobik dengan musik latin yang energik. Bakar kalori sambil bersenang-senang!',
                'price' => 120000,
                'capacity' => 20,
                'image' => 'classes/zumba.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'class_id' => 3,
                'class_name' => 'Weight Training',
                'description' => 'Latihan beban untuk membentuk otot dan meningkatkan kekuatan. Dibimbing trainer profesional.',
                'price' => 150000,
                'capacity' => 10,
                'image' => 'classes/weight-training.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('memberships')->insert([
            [
                'membership_id' => 1,
                'name' => 'Basic',
                'price' => 350000,
                'duration_days' => 30,
                'class_limit' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'membership_id' => 2,
                'name' => 'Premium',
                'price' => 750000,
                'duration_days' => 30,
                'class_limit' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'membership_id' => 3,
                'name' => 'Platinum',
                'price' => 1500000,
                'duration_days' => 90,
                'class_limit' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $schedules = [];
        $startDate = Carbon::today();

        // Jadwal Yoga
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->copy()->addDays($i);
            // Skip weekend (Sabtu=Minggu)
            if ($date->isWeekend()) continue;

            $schedules[] = [
                'trainer_id' => 2,
                'class_id' => 1,
                'schedule_date' => $date,
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $schedules[] = [
                'trainer_id' => 2,
                'class_id' => 1,
                'schedule_date' => $date,
                'start_time' => '16:00:00',
                'end_time' => '17:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Jadwal Zumba
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->copy()->addDays($i);
            if ($date->isWeekend()) continue;

            $schedules[] = [
                'trainer_id' => 3,
                'class_id' => 2,
                'schedule_date' => $date,
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $schedules[] = [
                'trainer_id' => 3,
                'class_id' => 2,
                'schedule_date' => $date,
                'start_time' => '18:00:00',
                'end_time' => '19:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Jadwal Weight Training
        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->copy()->addDays($i);
            if ($date->isWeekend()) continue;

            $schedules[] = [
                'trainer_id' => 4,
                'class_id' => 3,
                'schedule_date' => $date,
                'start_time' => '07:00:00',
                'end_time' => '08:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $schedules[] = [
                'trainer_id' => 4,
                'class_id' => 3,
                'schedule_date' => $date,
                'start_time' => '19:00:00',
                'end_time' => '20:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('schedules')->insert($schedules);

        DB::table('user_memberships')->insert([
            [
                'user_id' => 5,
                'membership_id' => 1,
                'start_date' => Carbon::today(),
                'end_date' => Carbon::today()->addDays(30),
                'remaining_class' => 3,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 6,
                'membership_id' => 2,
                'start_date' => Carbon::today(),
                'end_date' => Carbon::today()->addDays(30),
                'remaining_class' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $scheduleIds = DB::table('schedules')->pluck('schedule_id')->toArray();

        DB::table('bookings')->insert([
            [
                'user_id' => 5,
                'schedule_id' => $scheduleIds[0] ?? 1,
                'booking_type' => 'membership',
                'status' => 'booked',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 6,
                'schedule_id' => $scheduleIds[2] ?? 2,
                'booking_type' => 'membership',
                'status' => 'booked',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 7,
                'schedule_id' => $scheduleIds[4] ?? 3,
                'booking_type' => 'regular',
                'status' => 'booked',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('payments')->insert([
            [
                'user_id' => 7,
                'booking_id' => 3,
                'user_membership_id' => null,
                'amount' => 120000,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'payment_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 5,
                'booking_id' => null,
                'user_membership_id' => 1,
                'amount' => 350000,
                'payment_method' => 'midtrans',
                'status' => 'paid',
                'payment_date' => Carbon::yesterday(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 6,
                'booking_id' => null,
                'user_membership_id' => 2,
                'amount' => 750000,
                'payment_method' => 'midtrans',
                'status' => 'paid',
                'payment_date' => Carbon::yesterday(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_memberships')
            ->where('user_membership_id', 1)
            ->decrement('remaining_class', 1);
    }
}
