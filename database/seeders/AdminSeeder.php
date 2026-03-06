<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    // I have changed the email and password.
    public function run(): void
    {
        $user = User::create([
            'name' => 'Muhammad Kaif',
            'email' => 'admin@gmail.com',
            'mobile_no' => '03343739795',
            'password' => Hash::make('Password123'),
            'email_verified' => true 
        ]);

        if($user){
            $user->assignRole('Admin');
        }
    }
}
