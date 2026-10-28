<?php

namespace Database\Seeders;

use App\Category;
use App\Initiative;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitiativesSeeder extends Seeder
{
    /**
     * Descripciones de cada eje, indexadas por el slug de su ícono.
     *
     * @var array<string, string>
     */
    private const AXIS_DESCRIPTIONS = [
        'observatorio-integridad' => 'Es uno de los ejes transversales a la gestión del Ministerio. Mediante políticas y herramientas de integridad, transparencia activa y espacios permanentes de participación, se busca reducir la discrecionalidad en la toma de decisiones, ampliar el acceso a la información pública e involucrar a la ciudadanía en las políticas de obras y servicios públicos.',
        'observatorio-sostenible' => 'Es uno de los ejes transversales a la gestión del Ministerio. Mediante políticas y herramientas se incorpora la perspectiva ambiental, social y climática en cada etapa del ciclo de las obras y los servicios públicos, fortaleciendo la gestión integral del riesgo para construir una infraestructura más sostenible y resiliente.',
        'observatorio-genero' => 'Es uno de los ejes transversales a la gestión del Ministerio. Mediante políticas y herramientas se promueve la transversalización de la perspectiva de género y diversidad en el diseño, la implementación y el seguimiento de las iniciativas e intervenciones del Ministerio, garantizando que formen parte de una política integral y articulada que promueva la equidad entre los géneros.',
        'observatorio-innovacion' => 'Es uno de los fundamentos institucionales que dan soporte a la gestión del Ministerio. Mediante soluciones tecnológicas, la gobernanza de datos y la innovación estructural se fortalece la trazabilidad del ciclo de la obra pública, se mejora la toma de decisiones basada en evidencia, se acerca el Estado a la ciudadanía y se promueve la exploración y experimentación con nuevas tecnologías aplicadas a la gestión pública.',
        'observatorio-planeamiento' => 'Es uno de los fundamentos institucionales que dan soporte a la gestión del Ministerio. Mediante un sistema de planificación en cascada, tableros de seguimiento y metodologías de evaluación, se busca orientar la inversión pública con evidencia, alinear las prioridades de gestión con la estrategia ministerial y aprender de los resultados.',
        'observatorio-procesos' => 'Es uno de los fundamentos institucionales que dan soporte a la gestión del Ministerio. Mediante estándares de calidad, actualización normativa y circuitos digitales, se busca reducir tiempos, simplificar trámites y hacer más transparentes las contrataciones, orientándolos en función de las necesidades de la ciudadanía.',
        'observatorio-aprendizaje' => 'Es uno de los fundamentos institucionales que dan soporte a la gestión del Ministerio. Mediante la formación continua, el desarrollo de la carrera y la mejora de los espacios de trabajo, se busca una mejora sostenible en las capacidades operativas y de gestión del MISP.',
    ];

    /**
     * Carga las descripciones de los ejes y las iniciativas del Plan.
     * Es idempotente: actualiza las iniciativas existentes por título.
     */
    public function run(): void
    {
        $axesByIcon = Category::query()->get()->keyBy('icon');

        DB::transaction(function () use ($axesByIcon): void {
            foreach (self::AXIS_DESCRIPTIONS as $icon => $description) {
                $axesByIcon->get($icon)?->forceFill(['description' => $description])->save();
            }

            foreach ($this->initiatives() as $data) {
                $initiative = Initiative::query()->updateOrCreate(
                    ['title' => $data['title']],
                    collect($data)->only(['subtitle', 'url', 'featured_on_home', 'order'])->all()
                );

                $axisIds = collect($data['axes'])
                    ->map(fn (string $icon): ?int => $axesByIcon->get($icon)?->id)
                    ->filter()
                    ->all();

                $initiative->categories()->sync($axisIds);
            }
        });

        Initiative::flushPublicCache();
    }

    /**
     * @return list<array{title: string, subtitle: string, url: ?string, featured_on_home: bool, order: int, axes: list<string>}>
     */
    private function initiatives(): array
    {
        return [
            [
                'title' => 'Sistema de Integridad',
                'subtitle' => 'Conjunto articulado de políticas, herramientas y procedimientos destinado a transversalizar la integridad en la gestión del Ministerio, sus empresas, entes y organismos.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 10,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Código de Ética del MISP',
                'subtitle' => 'Herramienta que orienta la conducta de quienes ejercen la función pública ante los dilemas éticos de las obras y los servicios públicos, con principios, valores y criterios objetivos que evitan la discrecionalidad.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 11,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Red de Integridad, Transparencia y Gobierno Abierto',
                'subtitle' => 'Espacio donde el MISP y sus empresas, entes y organismos comparten buenas prácticas y construyen herramientas de gestión conjuntas.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 12,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Integridad en el sector privado',
                'subtitle' => 'Herramientas para promover integridad, transparencia y perspectiva de género y sostenibilidad en las empresas proveedoras y contratistas del MISP, como el Pliego con Triple Impacto y planes de integridad.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 13,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Fortalecimiento de las capacidades de gestión local',
                'subtitle' => 'Instrumentos y asistencia técnica para que los municipios incorporen estándares de integridad, transparencia y participación, como MapaInversiones Municipios y el Sistema Integral de Obras Municipal.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 14,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Transparencia Activa',
                'subtitle' => 'Criterios y herramientas para publicar y actualizar de manera proactiva la información pública del Ministerio en formatos accesibles, incluyendo una biblioteca digital.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 15,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'MapaInversiones PBA',
                'subtitle' => 'Espacio web que permite consultar de manera pública y georreferenciada las obras que realiza el Ministerio, conocer su ubicación, estado de ejecución e información sobre inversión y contratación, y realizar consultas, observaciones y sugerencias.',
                'url' => 'https://mapainversionespba.minfra.gba.gob.ar/',
                'featured_on_home' => true,
                'order' => 2,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Observatorio de Obras y Servicios Públicos de la PBA',
                'subtitle' => 'Espacio de participación institucionalizada que reúne a sindicatos, cámaras, universidades y organizaciones de la sociedad civil para contribuir a la construcción colectiva, el desarrollo y la mejora de las políticas del MISP.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 16,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Ministerio Abierto',
                'subtitle' => 'Estrategia y sitio web que centraliza las instancias participativas del Ministerio, como audiencias públicas, Encuentros Regionales del PEI y el Observatorio.',
                'url' => 'https://ministerioabierto.minfra.gba.gob.ar/',
                'featured_on_home' => true,
                'order' => 1,
                'axes' => ['observatorio-integridad'],
            ],
            [
                'title' => 'Mesa de Articulación para una Infraestructura Sostenible',
                'subtitle' => 'Espacio que reúne a las áreas, empresas y organismos del MISP para construir una agenda común de desarrollo sostenible.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 17,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Comité de Gestión de Riesgo y Emergencia del MISP',
                'subtitle' => 'Espacio que planifica una respuesta coordinada ante desastres o emergencias y construye protocolos de actuación.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 18,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Incorporación de criterios de sostenibilidad y resiliencia en el diseño de proyectos',
                'subtitle' => 'Capacitación y asistencia técnica para que los equipos del MISP integren estos criterios en la formulación de proyectos de infraestructura.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 19,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Repositorio sobre Infraestructura Sostenible y Resiliente',
                'subtitle' => 'Banco digital colaborativo de documentos externos que facilita el acceso a información sobre la perspectiva ambiental en la infraestructura.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 20,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Agenda de trabajo conjunta de Triple Impacto',
                'subtitle' => 'Instrumentos de gestión que integran las perspectivas de integridad, desarrollo sostenible, género y diversidad para potenciar el impacto de las iniciativas.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 21,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Etiquetado de Obras para una Infraestructura Sostenible y Resiliente',
                'subtitle' => 'Herramienta que clasifica las obras del MISP según su contribución al desarrollo sostenible y la resiliencia, visibilizándolas en los tableros de gestión.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 22,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Fortalecimiento de la Evaluación de Impacto Ambiental',
                'subtitle' => 'Mejora y sistematiza los procesos de Evaluación de Impacto Ambiental de las obras, mediante herramientas informáticas y capacitación, para optimizar su calidad y tiempos de gestión.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 23,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Visualizador de información hidrometeorológica en PBA',
                'subtitle' => 'Herramienta de la Autoridad del Agua (ADA) que consolida la información hidrometeorológica provincial en un único visor, para apoyar la gestión del riesgo climático.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 24,
                'axes' => ['observatorio-sostenible'],
            ],
            [
                'title' => 'Programa Constructiva',
                'subtitle' => 'Comunidad que fortalece la inserción y el desarrollo laboral de mujeres y diversidades en espacios altamente masculinizados.',
                'url' => null,
                'featured_on_home' => true,
                'order' => 8,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Mecanismos de inclusión y permanencia laboral',
                'subtitle' => 'Medidas para condiciones laborales más inclusivas en las obras y los servicios públicos, como el cupo laboral travesti-trans y espacios de lactancia.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 25,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Dispositivos de prevención y abordaje de las violencias por razones de género',
                'subtitle' => 'Capacitaciones, un Equipo Interdisciplinario de escucha y acompañamiento, y un protocolo común para prevenir y abordar situaciones de violencia de género en el MISP.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 26,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Mesa Intersectorial de Infraestructura con perspectiva de Género',
                'subtitle' => 'Espacio de trabajo colaborativo entre actores clave para desarrollar iniciativas que reduzcan la brecha de género en las obras y servicios públicos.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 27,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Manual de Infraestructura y Servicios Públicos con Perspectiva de Género',
                'subtitle' => 'Lineamientos para integrar la perspectiva de género y diversidad en las obras, garantizando accesibilidad, seguridad e inclusión.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 28,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Etiquetado de Obras con Perspectiva de Género y Diversidad',
                'subtitle' => 'Sistema que clasifica las obras según su contribución al cierre de brechas de género, visibilizándolas en los tableros de gestión y en MapaInversiones.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 29,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Alianzas estratégicas con organismos especializados',
                'subtitle' => 'Acuerdos con organismos públicos, universidades e instituciones especializadas para fortalecer las políticas de género y diversidad del Ministerio.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 30,
                'axes' => ['observatorio-genero'],
            ],
            [
                'title' => 'Sistema Integral de Obras (SIO PBA)',
                'subtitle' => 'Plataforma que integra y da trazabilidad a las etapas de proyecto, contratación, ejecución y mantenimiento de las obras del MISP.',
                'url' => 'https://siopba.minfra.gba.gob.ar/users/sign_in',
                'featured_on_home' => true,
                'order' => 3,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Sistema Integral de Obras Municipios',
                'subtitle' => 'Versión del SIO desarrollada junto a los gobiernos municipales para dar trazabilidad al ciclo de sus obras.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 31,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Sistema de Gestión Vial',
                'subtitle' => 'Plataforma con información geográfica para gestionar las rutas provinciales y apoyar la conservación del pavimento.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 32,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Pesaje dinámico vehicular',
                'subtitle' => 'Sistema con inteligencia artificial que registra el peso de vehículos en movimiento en las rutas de la PBA, para fiscalizar cargas y proteger la infraestructura vial.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 33,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Metodología BIM (Building Information Modeling)',
                'subtitle' => 'Modelado digital que integra los datos del proyecto en todas sus etapas, para mejorar la trazabilidad y reducir errores y costos.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 34,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Captu.Data PBA',
                'subtitle' => 'Aplicación móvil para inspeccionar y supervisar obras en territorio, con datos en tiempo real integrados automáticamente a los sistemas del MISP.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 35,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'SIMPARH',
                'subtitle' => 'Centro de Monitoreo y plataforma con Internet de las Cosas (IoT) que recolecta y publica en tiempo real información para la previsión del riesgo hidrometeorológico.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 36,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Plan de Ciberseguridad',
                'subtitle' => 'Medidas preventivas, correctivas y de monitoreo continuo para proteger los sistemas, datos y comunicaciones del MISP ante ciberamenazas.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 37,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Laboratorio InfraInnova',
                'subtitle' => 'Entorno colaborativo para desarrollar proyectos de Inteligencia Artificial aplicados a las obras y los servicios públicos, con formación especializada e investigación.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 38,
                'axes' => ['observatorio-innovacion'],
            ],
            [
                'title' => 'Estimación de brechas de inversión en infraestructura en PBA',
                'subtitle' => 'Metodologías que cuantifican las brechas para priorizar la inversión pública, con resultados publicados en el Visualizador de Brechas.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 39,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Planes Estratégicos y Operativos',
                'subtitle' => 'Plan de trabajo de cada área del MISP que define objetivos, metas e indicadores anuales en el marco del PFI, con seguimiento periódico de avances.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 40,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'GeoInfra PBA',
                'subtitle' => 'Espacio web de datos georreferenciados que integra información territorial de distintas fuentes, base técnica para la planificación estratégica del Ministerio.',
                'url' => 'https://www.minfra.gba.gob.ar/sigplan/',
                'featured_on_home' => true,
                'order' => 6,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Etiquetado de obras y proyectos y clasificación presupuestaria con perspectiva ambiental, de género y diversidad',
                'subtitle' => 'Sistema que clasifica las obras según su aporte a la sostenibilidad y la equidad de género, visibilizándolas en MapaInversiones.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 41,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Presupuesto con enfoque estratégico',
                'subtitle' => 'Alineación de la planificación presupuestaria del MISP con los ejes del PEI y las perspectivas de integridad, sostenibilidad y género.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 42,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'DataObras - Tablero de Monitoreo de Gestión',
                'subtitle' => 'Tableros interactivos con información actualizada sobre proyectos, contrataciones y obras del MISP, interoperables con el SIO PBA.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 43,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Tablero Territorial de Obras y Proyectos',
                'subtitle' => 'Herramienta que usa información georreferenciada de GeoInfra e IDEBA para visualizar y monitorear las obras del Ministerio en todo el territorio.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 44,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Mapa de Fortalecimiento Institucional',
                'subtitle' => 'Espacio digital abierto para seguir el avance de los objetivos y metas del PFI, con sus resultados y aprendizajes.',
                'url' => 'https://seguimiento-pfi.minfra.gba.gob.ar/',
                'featured_on_home' => true,
                'order' => 7,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Evaluación participativa del Plan Estratégico de Infraestructura',
                'subtitle' => 'Metodología que incorpora las necesidades del territorio a la planificación del PEI mediante Encuentros Regionales y el Observatorio.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 45,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Metodologías de Evaluación Ex Ante y Ex Post',
                'subtitle' => 'La ex ante analiza la viabilidad de una obra antes de ejecutarla; la ex post analiza sus resultados una vez finalizada, para identificar aprendizajes.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 46,
                'axes' => ['observatorio-planeamiento'],
            ],
            [
                'title' => 'Sistema de Gestión de la Calidad',
                'subtitle' => 'Implementación de estándares internacionales ISO para garantizar la mejora continua y la trazabilidad de los procesos de la Obra Pública.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 47,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Nuevo Anteproyecto de Ley de Obra Pública',
                'subtitle' => 'Revisión integral del marco normativo de la Obra Pública en la Provincia, que incorpora integridad, sostenibilidad e innovación para procesos más eficientes y transparentes.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 48,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Actualización normativa y eficiencia de la Obra Pública',
                'subtitle' => 'Reformas al régimen de contratación para simplificar procedimientos y reducir tiempos, incluyendo cambios a la Ley N° 6.021 y al Reglamento del Registro de Licitadores.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 49,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Precios de referencia insumos de Obra Pública',
                'subtitle' => 'Listado consolidado de precios de los insumos más relevantes, que facilita la elaboración de presupuestos, licitaciones y índices propios.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 50,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Contratación electrónica – PBA Contrataciones',
                'subtitle' => 'Digitalización y centralización del proceso de contratación de obras y servicios públicos, a través de la plataforma PBA Contrataciones.',
                'url' => 'https://pbacontrataciones.minfra.gba.gob.ar/Default.aspx',
                'featured_on_home' => true,
                'order' => 5,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Sistema del Registro de Licitadores PBA',
                'subtitle' => 'Plataforma de inscripción, actualización y calificación de contratistas que participan en licitaciones de la Provincia, con trazabilidad y transparencia.',
                'url' => 'https://www.minfra.gba.gob.ar/registrolicitadoresba/',
                'featured_on_home' => false,
                'order' => 51,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Oficina Virtual',
                'subtitle' => 'Canal digital único para trámites, notificaciones y comunicaciones formales del MISP, que reduce el papel y simplifica la relación con municipios, empresas, entidades y ciudadanía.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 52,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Pliego con Triple Impacto',
                'subtitle' => 'Adecuación de los pliegos de contratación para incorporar, de forma transversal, los enfoques de integridad, sostenibilidad y género y diversidad.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 53,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Manuales y Guías de Trabajo',
                'subtitle' => 'Instrumentos que documentan, estandarizan y formalizan los procesos del MISP, sus empresas, entes y organismos.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 54,
                'axes' => ['observatorio-procesos'],
            ],
            [
                'title' => 'Plan Integral de Capacitación',
                'subtitle' => 'Instrumento que planifica la formación continua del personal del MISP a partir de las necesidades y brechas de capacidades, mediante Planes Anuales de Capacitación.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 55,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'InfraAprende - Campus Virtual',
                'subtitle' => 'Plataforma educativa del Ministerio que ofrece formación virtual, tutorada y autogestionada, al personal del MISP y a actores de la Obra Pública.',
                'url' => 'https://cursos.minfra.gba.gob.ar/login/index.php',
                'featured_on_home' => true,
                'order' => 4,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Plan de acompañamiento a la trayectoria laboral',
                'subtitle' => 'Políticas que acompañan el ciclo laboral de las personas en el MISP, desde el ingreso hasta el retiro.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 56,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Nueva estructura orgánica del MISP',
                'subtitle' => 'Adecuación de la estructura organizativa para fortalecer capacidades, ordenar responsabilidades y alinearse con el nuevo modelo de gestión.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 57,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Plan de Desarrollo de Recursos Humanos',
                'subtitle' => 'Herramienta para fortalecer la gestión de personas en el MISP mediante profesionalización, desarrollo de carrera, selección por mérito y abordaje de la violencia laboral y de género.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 58,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Mi Infra - Intranet',
                'subtitle' => 'Plataforma digital interna que facilita la comunicación y el intercambio de información entre los equipos del Ministerio, con acceso a documentos y herramientas de gestión.',
                'url' => 'https://intranet.minfra.gba.gob.ar/miinfra',
                'featured_on_home' => false,
                'order' => 59,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Red de Áreas',
                'subtitle' => 'Espacios permanentes de articulación para cada uno de los siete ejes del PFI, integrados por representantes de las áreas, empresas y organismos del Ministerio.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 60,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Plan de puesta en valor y equipamiento de edificios y espacios de trabajo',
                'subtitle' => 'Renovación y adecuación de edificios y oficinas del MISP, con mejoras en equipamiento, accesibilidad y conectividad.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 61,
                'axes' => ['observatorio-aprendizaje'],
            ],
            [
                'title' => 'Plan de puesta en valor y modernización de la Infraestructura Tecnológica del MISP',
                'subtitle' => 'Renovación de equipos, sistemas, cableado y conectividad del Ministerio para optimizar la eficiencia operativa y la seguridad.',
                'url' => null,
                'featured_on_home' => false,
                'order' => 62,
                'axes' => ['observatorio-aprendizaje'],
            ],
        ];
    }
}
