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
        <!-- <p>{!! nl2br(e(app_setting('app_footer_contact_info'))) !!}</p> -->
         <a href="https://x.com/test"><i class="fab fa-X-twitter"></i></a>
      </div>
      {{-- <div class="col-lg-2 mb-0">
        <a href="https://democraciaenred.org" target="_blank"><img src="{{asset('img/der-black.svg')}}" class="footer-logo" alt="Democracia en Red"></a>
        <br>Desarrollado con <i class="far fa-heart text-danger"></i> por Democracia en Red
      </div> --}}
    </div>
  </div>
</div>
