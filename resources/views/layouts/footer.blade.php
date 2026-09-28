<div id="prefooter">
  <img src="{{ asset('/img/footer-header.png') }}" class="img-fluid w-100 mw-100" alt="Header del pie de página"/>
</div>
<div id="footer">
  <div class="container">
    <div class="row text-smaller text-center text-md-left">
      <div class="col-12 col-lg-5 mb-4 mb-lg-0">
        <img src="{{asset(app_setting('app_logo_footer','img/logo-large-color.svg'))}}" class="footer-logo" alt="">
          <div class="footer-social-links justify-content-center justify-content-md-start my-1" aria-label="Redes sociales">
            <a href="https://www.facebook.com/BAProvincia/" class="h4" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
              <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
            </a>
            <a href="https://x.com/baprovincia" class="h4" target="_blank" rel="noopener noreferrer" aria-label="X" title="X">
              <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
            </a>
            <a href="https://www.instagram.com/provinciaba/" class="h4" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
              <i class="fa-brands fa-instagram" aria-hidden="true"></i>
            </a>
            <a href="https://www.youtube.com/channel/UCRuY8kHZHaiqAAdjcgobsNw" class="h4" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube">
              <i class="fa-brands fa-youtube" aria-hidden="true"></i>
            </a>
            <a href="https://t.me/GobiernoPBA" class="h4" target="_blank" rel="noopener noreferrer" aria-label="Telegram" title="Telegram">
              <i class="fa-brands fa-telegram" aria-hidden="true"></i>
            </a>
            <a href="https://www.tiktok.com/@provinciaba" class="h4" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
              <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
            </a>
            <a href="https://www.twitch.tv/provinciaba" class="h4" target="_blank" rel="noopener noreferrer" aria-label="Twitch" title="Twitch">
              <i class="fa-brands fa-twitch" aria-hidden="true"></i>
            </a>
            <a href="https://wa.me/2214354223" class="h4" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" title="WhatsApp">
              <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
            </a>
        </div>
        <p class="mb-lg-1"><b>Contactenos</b></p>
        <!-- <p>{!! nl2br(e(app_setting('app_footer_contact_info'))) !!}</p> -->
        <p>mesadeayuda@minfra.gba.gob.ar</p>
      </div>
      <div class="col-6 col-md-4 offset-lg-1 col-lg-2 mb-4 mb-lg-0">
        <p class="mb-1 mb-lg-2"><b>Secciones</b></p>
        <p class="mb-1"><a href="{{route('home')}}">Inicio</a></p>
        <p class="mb-1"><a href="{{route('catalog')}}">El Plan</a></p>
        <p class="mb-1"><a href="{{route('objectives')}}">Objetivos</a></p>
        <p class="mb-1"><a href="{{route('reports')}}">Seguimiento</a></p>
        <p class="mb-1"><a href="{{route('events.upcoming')}}">Eventos</a></p>
      </div>
      <div class="col-6 col-md-4 order-md-last col-lg-2 mb-4 mb-lg-0">
        <p class="mb-1 mb-lg-2"><b>Más información</b></p>
        <p class="mb-1"><a href="{{route('about.general')}}">Acerca de</a></p>
        <p class="mb-1"><a href="{{route('about.faq')}}">Preguntas frecuentes</a></p>
        <p class="mb-1"><a href="{{route('about.legal')}}#términos">Legales</a></p>
      </div>
      <div class="col-12 col-md-4 col-lg-2 mb-2 mb-lg-0">
        <p class="mb-1 mb-lg-2"><b>Provincia de Buenos Aires</b></p>
        @foreach ([
          'Portal de la Provincia' => 'https://www.gba.gob.ar/',
          'Registro de las Personas' => 'https://www.gba.gob.ar/registrodelaspersonas',
          'Boletín Oficial' => 'https://boletinoficial.gba.gob.ar/',
          'ARBA' => 'https://web.arba.gov.ar/',
          'Consulta de expedientes' => 'https://sistemas.gba.gov.ar/consulta/expedientes/index.php',
        ] as $externalLabel => $externalUrl)
          <p class="mb-1">
            <a href="{{ $externalUrl }}" class="footer-external-link" target="_blank" rel="noopener noreferrer">
              {{ $externalLabel }}<i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="sr-only"> (abre en una nueva pestaña)</span>
            </a>
          </p>
        @endforeach
      </div>
    </div>
  </div>
</div>
