<div id="prefooter">
  <img src="{{ asset('/img/footer-header.png') }}" class="img-fluid w-100 mw-100" alt="Header del pie de página"/>
</div>
<div id="footer">
  <div class="container">
    <div class="row text-smaller text-center text-lg-left">
      <div class="col-lg-3 mb-2 mb-lg-0">
        <img src="{{asset(app_setting('app_logo_footer','img/default-logo-color.svg'))}}" class="footer-logo" alt="">
        <p class="">
        {!! nl2br(e(app_setting('app_footer_description'))) !!}
        </p>
      </div>
      <div class="col-lg-3 mb-2 mb-lg-0">
        <p class="mb-1 mb-lg-2"><b>Más información</b></p>
        <p class="mb-1"><a href="{{route('about.general')}}">Acerca de</a></p>
        <p class="mb-1"><a href="{{route('about.faq')}}">Preguntas frecuentes</a></p>
        <p class="mb-1"><a href="{{route('about.legal')}}#términos">Legales</a></p>
      </div>
      <div class="col-lg-3 mb-2 mb-lg-0">
        <p class="mb-1 mb-lg-2"><b>Provincia de Buenos Aires</b></p>
        <p class="mb-1"><a href="https://www.gba.gob.ar/" target="_blank" rel="noopener noreferrer">Portal de la Provincia</a></p>
        <p class="mb-1"><a href="https://www.gba.gob.ar/registrodelaspersonas" target="_blank" rel="noopener noreferrer">Registro de las Personas</a></p>
        <p class="mb-1"><a href="https://boletinoficial.gba.gob.ar/" target="_blank" rel="noopener noreferrer">Boletín Oficial</a></p>
        <p class="mb-1"><a href="https://web.arba.gov.ar/" target="_blank" rel="noopener noreferrer">ARBA</a></p>
        <p class="mb-1"><a href="https://sistemas.gba.gov.ar/consulta/expedientes/index.php" target="_blank" rel="noopener noreferrer">Consulta de expedientes</a></p>
      </div>
      <div class="col-lg-3 mb-2 mb-lg-0">
        <p class="mb-lg-2"><b>Contactenos</b></p>
        <p>{!! nl2br(e(app_setting('app_footer_contact_info'))) !!}</p>
        <p class="mb-1"><b>Redes sociales</b></p>
        <div class="footer-social-links" aria-label="Redes sociales">
          <a href="https://www.facebook.com/BAProvincia/" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
          </a>
          <a href="https://x.com/baprovincia" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="X" title="X">
            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
          </a>
          <a href="https://www.instagram.com/provinciaba/" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
            <i class="fa-brands fa-instagram" aria-hidden="true"></i>
          </a>
          <a href="https://www.youtube.com/channel/UCRuY8kHZHaiqAAdjcgobsNw" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="YouTube" title="YouTube">
            <i class="fa-brands fa-youtube" aria-hidden="true"></i>
          </a>
          <a href="https://t.me/GobiernoPBA" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="Telegram" title="Telegram">
            <i class="fa-brands fa-telegram" aria-hidden="true"></i>
          </a>
          <a href="https://www.tiktok.com/@provinciaba" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="TikTok" title="TikTok">
            <i class="fa-brands fa-tiktok" aria-hidden="true"></i>
          </a>
          <a href="https://www.twitch.tv/provinciaba" class="btn btn-primary rounded-circle" target="_blank" rel="noopener noreferrer" aria-label="Twitch" title="Twitch">
            <i class="fa-brands fa-twitch" aria-hidden="true"></i>
          </a>
        </div>
      </div>
      {{-- <div class="col-lg-2 mb-0">
        <a href="https://democraciaenred.org" target="_blank"><img src="{{asset('img/der-black.svg')}}" class="footer-logo" alt="Democracia en Red"></a>
        <br>Desarrollado con <i class="far fa-heart text-danger"></i> por Democracia en Red
      </div> --}}
    </div>
  </div>
</div>
