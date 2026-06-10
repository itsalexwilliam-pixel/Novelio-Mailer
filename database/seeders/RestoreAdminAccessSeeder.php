<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestoreAdminAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = Account::query()->first();

        if (! $account) {
            $account = Account::query()->create([
                'name' => 'Default Account',
                'slug' => 'default-account-' . Str::lower(Str::random(6)),
                'status' => 'active',
            ]);
        }

        $user = User::query()->where('email', 'admin@proadvisor.local')->first();

        if (! $user) {
            User::query()->create([
                'name' => 'Admin',
                'email' => 'admin@proadvisor.local',
                'password' => bcrypt('Opetron11#$'),
                'role' => 'admin',
                'account_id' => $account->id,
            ]);

            return;
        }

        $user->name = 'Admin';
        $user->password = bcrypt('Opetron11#$');
        $user->role = 'admin';
        $user->account_id = $account->id;
        $user->save();
    }
}
