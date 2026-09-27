<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Icon per axis order: [svg slug, previous FontAwesome class].
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $iconsByOrder = [
        1 => ['observatorio-integridad', 'fas fa-users'],
        2 => ['observatorio-sostenible', 'fas fa-leaf'],
        3 => ['observatorio-genero', 'fas fa-transgender-alt'],
        4 => ['observatorio-innovacion', 'fas fa-lightbulb'],
        5 => ['observatorio-planeamiento', 'fas fa-chart-bar'],
        6 => ['observatorio-procesos', 'fas fa-road'],
        7 => ['observatorio-aprendizaje', 'fas fa-chalkboard-teacher'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->iconsByOrder as $order => [$svgIcon]) {
            DB::table('categories')->where('order', $order)->update(['icon' => $svgIcon]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->iconsByOrder as [$svgIcon, $fontAwesomeIcon]) {
            DB::table('categories')->where('icon', $svgIcon)->update(['icon' => $fontAwesomeIcon]);
        }
    }
};
