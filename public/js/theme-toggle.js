// Tema claro/oscuro de los paneles y páginas de acceso.
// La clase theme-dark va en <html> y la pone partials/tema-inicial antes de pintar
// (FE-06). Aquí solo se sincroniza el botón y se guarda la elección. Si la persona
// nunca eligió, se sigue la preferencia del sistema, también si cambia en vivo.
(() => {
  const storageKey = 'msea-theme';
  const raiz = document.documentElement;
  const svg = (contenido) => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${contenido}</svg>`;
  const SOL = svg('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-17.07 1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>');
  const LUNA = svg('<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>');
  const sistema = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  const leer = () => { try { return localStorage.getItem(storageKey); } catch (e) { return null; } };
  const guardar = (valor) => { try { localStorage.setItem(storageKey, valor); } catch (e) { /* modo privado */ } };
  const temaActual = () => leer() || (sistema && sistema.matches ? 'dark' : 'light');

  const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    raiz.classList.toggle('theme-dark', isDark);
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      const texto = isDark ? 'Activar modo claro' : 'Activar modo oscuro';
      button.setAttribute('aria-pressed', String(isDark));
      button.setAttribute('aria-label', texto);
      button.setAttribute('title', texto);
      button.innerHTML = isDark ? SOL : LUNA;
    });
  };

  sistema?.addEventListener?.('change', () => { if (!leer()) applyTheme(temaActual()); });

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(temaActual());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const nextTheme = raiz.classList.contains('theme-dark') ? 'light' : 'dark';
        guardar(nextTheme);
        applyTheme(nextTheme);
      });
    });
  });
})();
