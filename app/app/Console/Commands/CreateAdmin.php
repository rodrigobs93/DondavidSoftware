<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature   = 'pos:create-admin {email} {password} {--name=Admin}';
    protected $description = 'Create or update an admin user non-interactively (used by the Windows installer).';

    public function handle(): int
    {
        $email    = $this->argument('email');
        $password = $this->argument('password');
        $name     = $this->option('name');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
                'role'     => 'admin',
                'active'   => true,
            ],
        );

        $this->info("Admin user ready: {$user->email}");
        return self::SUCCESS;
    }
}
