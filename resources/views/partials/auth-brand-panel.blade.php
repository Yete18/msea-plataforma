<aside class="auth-brand-panel" aria-label="MSEA Music Academy">
  <a href="{{ url('/') }}" class="auth-brand-logo">
    <img src="{{ asset('assets/img/logo-320.webp') }}" width="64" height="64" alt="" />
    <span>
      <strong>Academia de Música MSEA</strong>
      <small>Fundación MSEA · Bolivia</small>
    </span>
  </a>

  <div class="auth-brand-content">
    <h2>Aprende música.<br>Practica. Progresa.</h2>
    <p>Una plataforma educativa musical diseñada para acompañar la formación de la comunidad MSEA en El Alto, Bolivia.</p>

    <dl class="auth-metrics">
      @foreach ($authMetrics as $metric)
        <div>
          <dt>{{ number_format($metric['value'], 0, ',', '.') }}</dt>
          <dd>{{ $metric['label'] }}</dd>
        </div>
      @endforeach
    </dl>
  </div>

  <p class="auth-brand-footer">© {{ now()->year }} Fundación Movimiento Sinfónico de El Alto · MSEA</p>
</aside>
