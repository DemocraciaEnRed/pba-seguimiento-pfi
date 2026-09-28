<?php

namespace Tests\Feature;

use App\Faq;
use Database\Seeders\FaqTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_about_section_with_institutional_titles(): void
    {
        $this->seed(FaqTableSeeder::class);

        $this->assertSame(
            [
                'Sobre el “Plan de Fortalecimiento Institucional”',
                'Ministerio de Infraestructura y Servicios Públicos',
                '¿Como participo?',
            ],
            Faq::query()->where('section', 'general')->orderBy('order')->pluck('title')->all()
        );

        $ministry = Faq::query()->where('title', 'Ministerio de Infraestructura y Servicios Públicos')->firstOrFail();
        $this->assertStringContainsString('mailto:mesadeayuda@minfra.gba.gob.ar', $ministry->content);
        $this->assertStringContainsString('https://wa.me/2214354223', $ministry->content);
    }

    public function test_seeded_content_has_no_legacy_brand_or_placeholders(): void
    {
        $this->seed(FaqTableSeeder::class);

        foreach (Faq::all() as $faq) {
            $this->assertStringNotContainsStringIgnoringCase('partícipes', $faq->title.$faq->content);
            $this->assertStringNotContainsString('[Nombre de la plataforma]', $faq->content);
            $this->assertStringNotContainsString('xxxx', $faq->content);
        }
    }
}
