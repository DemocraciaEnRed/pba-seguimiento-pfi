<?php

$assetsUrl = 'https://democraciaenred.nyc3.digitaloceanspaces.com/projects/pba-ministerio-abierto/app/assets/pdf';

return [

    'library_url' => 'https://www.minfra.gba.gob.ar/test-bibliotecadigital/home',

    /*
    |--------------------------------------------------------------------------
    | Documentos del Plan
    |--------------------------------------------------------------------------
    |
    | Documentos que se muestran en la sección «El Plan» con su tapa y un
    | botón de descarga.
    |
    */

    'documents' => [
        [
            'title' => 'Plan de Fortalecimiento Institucional 2024-2027',
            'acronym' => 'PFI',
            'description' => 'Desarrolla las capacidades del Ministerio para llevar adelante las prioridades de infraestructura.',
            'url' => env('PLAN_PFI_URL', $assetsUrl.'/misp-pfi-plan-de-fortalecimiento-institucional-2024-2027.pdf'),
            'cover' => env('PLAN_PFI_COVER', $assetsUrl.'/misp-pfi-plan-de-fortalecimiento-institucional-2024-2027.png'),
        ],
        [
            'title' => 'Plan Estratégico de Infraestructura 2024-2035',
            'acronym' => 'PEI',
            'description' => 'Define las prioridades de infraestructura de la Provincia de Buenos Aires.',
            'url' => env('PLAN_PEI_URL', $assetsUrl.'/misp-pei-plan-estrategico-de-infraestructura-2026.pdf'),
            'cover' => env('PLAN_PEI_COVER', $assetsUrl.'/misp-pei-plan-estrategico-de-infraestructura-2026.png'),
        ],
    ],

];
