<?php

namespace Database\Seeders;

use App\User;
use App\Role;
use App\File;
use App\ImageFile;
use App\Category;
use App\StrategicObjective;
use App\Organization;
use App\Objective;
use App\Community;
use App\Setting;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DefaultDemoSeeder extends Seeder
{
    use SeedsDemoGoals;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Factory::create('es_AR');

        $category = new Category();
        $category->title = 'Educacion';
        $category->icon = 'observatorio-aprendizaje';
        $category->color = '#602282';
        $category->order = 1;
        $category->save();
        $category = new Category();
        $category->title = 'Seguridad';
        $category->icon = 'observatorio-integridad';
        $category->color = '#30689c';
        $category->order = 2;
        $category->save();
        $category = new Category();
        $category->title = 'Ecologia';
        $category->icon = 'observatorio-sostenible';
        $category->color = '#32a852';
        $category->order = 3;
        $category->save();
        $category = new Category();
        $category->title = 'Economia comunitaria';
        $category->icon = 'observatorio-procesos';
        $category->color = '#b52260';
        $category->order = 4;
        $category->save();
        $category = new Category();
        $category->title = 'Musica';
        $category->icon = 'observatorio-innovacion';
        $category->color = '#ba8e14';
        $category->order = 5;
        $category->save();

        $strategicObjectivesByCategory = array();
        foreach (Category::all() as $categoryForStrategic) {
            $strategicObjectivesByCategory[$categoryForStrategic->id] = array();
            for ($strategicIndex=0; $strategicIndex < 2; $strategicIndex++) {
                $strategicObjective = new StrategicObjective();
                $strategicObjective->codigo = 'OE-' . str_pad("{$categoryForStrategic->id}{$strategicIndex}", 4, '0', STR_PAD_LEFT);
                $strategicObjective->title = $faker->sentence;
                $strategicObjective->category()->associate($categoryForStrategic);
                $strategicObjective->save();
                $strategicObjectivesByCategory[$categoryForStrategic->id][] = $strategicObjective->id;
            }
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

        for ($i=0; $i <= 20; $i++) {
            $objective = new Objective();
            $category = Category::findorfail($faker->randomElement([1,2,3,4]));
            $objective->title = $faker->sentence;
            $objective->content = $faker->text(600);
            $objective->hidden = false;
            $objective->tags = $faker->randomElements(['tag1','tag2','tag3','tag4','tag5','tag6'],3);
            $objective->strategicObjective()->associate(StrategicObjective::findOrFail($faker->randomElement($strategicObjectivesByCategory[$category->id])));
            $objective->author()->associate($admin);
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
