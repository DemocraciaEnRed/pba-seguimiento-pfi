<?php

namespace Database\Seeders;

use App\User;
use App\Role;
use App\ImageFile;
use App\Community;
use App\Organization;
use App\Objective;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    use SeedsDemoGoals;

    /**
     * Decorates the axes, strategic and general objectives loaded by `db:seed` with demo users, organizations and goals.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create('es_AR');

        if (! Objective::query()->exists()) {
            $this->call(DatabaseSeeder::class);
        }

        $admin = new User();
        $admin->name = 'Admin';
        $admin->surname = 'Participes';
        $admin->email = 'admin@admin.com';
        $admin->email_verified_at = now();
        $admin->password = Hash::make('participes');
        $admin->remember_token = Str::random(10);
        $admin->save();
        $admin->roles()->attach(Role::where('name', 'user')->first());
        $admin->roles()->attach(Role::where('name', 'admin')->first());
        $admin->save();

        $usrRole = Role::where('name', 'user')->first();
        $users = array();
        for ($i=0; $i < 50; $i++) {
            $user = new User();
            $user->name = $faker->firstName;
            $user->surname = "Usuario${i}";
            $user->email = "user${i}@user.com";
            $user->email_verified_at = now();
            $user->password = Hash::make('participes');
            $user->remember_token = Str::random(10);
            $user->save();
            $user->roles()->attach($usrRole);
            $user->save();
            $users[] = $user->id;
        }
        $organizations = array();
        for ($i=0; $i < 25; $i++) {
            $picture = new ImageFile();
            $picture->name = 'default-organization.png';
            $picture->size = '100';
            $picture->mime = 'image/png';
            $picture->path = '/img/default-organization.png';
            $organization = new Organization();
            $organization->name = $faker->company;
            $organization->description = $faker->text;
            $organization->save();
            $organization->logo()->save($picture);
            $organizations[] = $organization->id;
        }

        foreach (Objective::query()->orderBy('id')->get() as $i => $objective) {
            $objective->hidden = false;
            $objective->tags = $faker->randomElements(['tag1','tag2','tag3','tag4','tag5','tag6'],3);
            $objective->save();
            $objective->organizations()->attach($faker->randomElements($organizations,3));

            $community = new Community();
            $community->label = '¡Unite al Telegram!';
            $community->icon = 'fab fa-telegram';
            $community->color = '#30689c';
            $community->url = 'https://google.com';
            $objective->communities()->save($community);

            $theTeam = $faker->randomElements($users,6);
            for ($y=0; $y < 6; $y++) {
                $objective->members()->attach($theTeam[$y], ['role' => $faker->randomElement(['manager','reporter'])]);
            }

            $objective->subscribers()->attach($faker->randomElements($users,4));

            $this->seedDemoGoals($objective, $theTeam, $faker, $i);
        }
    }
}
