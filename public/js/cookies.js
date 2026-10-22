/* Aviso de cookies de MSEA.
   - Cookies necesarias (sesion, seguridad): no piden permiso.
   - Analitica (solo si la fundacion la configura): se carga unicamente si la persona la acepta.
   - La eleccion se guarda en el navegador (localStorage, clave "msea-cookies"). */
(function () {
  'use strict';

  var CLAVE = 'msea-cookies';
  var banner = document.getElementById('cookie-banner');
  if (!banner) return;

  function leer() {
    try { return JSON.parse(localStorage.getItem(CLAVE) || 'null'); } catch (e) { return null; }
  }
  function guardar(analitica) {
    try { localStorage.setItem(CLAVE, JSON.stringify({ v: 1, analitica: !!analitica, fecha: new Date().toISOString() })); } catch (e) { /* sin almacenamiento: se vuelve a preguntar */ }
  }

  var cargada = false;
  function cargarAnalitica() {
    if (cargada) return;
    var ga4 = banner.getAttribute('data-ga4') || '';
    var dominio = banner.getAttribute('data-plausible') || '';

    if (/^G-[A-Z0-9]{4,20}$/.test(ga4)) {
      cargada = true;
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      // Sin senales de publicidad ni personalizacion: solo conteo de visitas.
      window.gtag('config', ga4, { allow_google_signals: false, allow_ad_personalization_signals: false });
      var g = document.createElement('script');
      g.async = true;
      g.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4);
      document.head.appendChild(g);
    }

    if (/^[a-z0-9.-]+\.[a-z]{2,}$/i.test(dominio)) {
      cargada = true;
      var p = document.createElement('script');
      p.defer = true;
      p.setAttribute('data-domain', dominio);
      p.src = banner.getAttribute('data-plausible-script') || 'https://plausible.io/js/script.js';
      document.head.appendChild(p);
    }
  }

  function mostrar() {
    banner.hidden = false;
    document.body.classList.add('cookies-visibles');
  }
  function ocultar() {
    banner.hidden = true;
    document.body.classList.remove('cookies-visibles');
  }

  banner.addEventListener('click', function (e) {
    var boton = e.target.closest('[data-cookies]');
    if (!boton) return;
    var accion = boton.getAttribute('data-cookies');
    if (accion === 'aceptar') { guardar(true); cargarAnalitica(); }
    else { guardar(false); }
    ocultar();
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-abrir-cookies]')) {
      e.preventDefault();
      mostrar();
      var primero = banner.querySelector('button');
      if (primero) primero.focus();
    }
  });

  var eleccion = leer();
  if (!eleccion) mostrar();
  else if (eleccion.analitica) cargarAnalitica();
})();
