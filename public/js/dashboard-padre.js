/* MSEA - Panel familiar: selector de hijo con pestañas accesibles.
   El menú apunta siempre a las secciones del hijo que se está viendo. */
document.addEventListener('DOMContentLoaded', () => {
  const pestanas = [...document.querySelectorAll('.hijo-pill')];
  const enlaces = document.querySelectorAll('.app-nav a[data-ancla]');

  const elegir = (pestana, enfocar = false) => {
    const id = pestana.dataset.hijoId;
    pestanas.forEach((p) => {
      const activa = p === pestana;
      p.setAttribute('aria-selected', String(activa));
      p.tabIndex = activa ? 0 : -1;
    });
    document.querySelectorAll('.hijo').forEach((s) => { s.hidden = s.id !== `hijo-section-${id}`; });
    enlaces.forEach((a) => { a.href = `#${a.dataset.ancla}-${id}`; });
    if (enfocar) pestana.focus();
  };

  pestanas.forEach((p, i) => {
    p.addEventListener('click', () => elegir(p));
    p.addEventListener('keydown', (e) => {
      const paso = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (paso) { e.preventDefault(); elegir(pestanas[(i + paso + pestanas.length) % pestanas.length], true); }
      if (e.key === 'Home') { e.preventDefault(); elegir(pestanas[0], true); }
      if (e.key === 'End') { e.preventDefault(); elegir(pestanas[pestanas.length - 1], true); }
    });
  });

  // Enlace activo del menú según la sección visible.
  const todos = document.querySelectorAll('.app-nav a[href^="#"]');
  todos.forEach((a) => a.addEventListener('click', () => {
    todos.forEach((x) => x.removeAttribute('aria-current'));
    a.setAttribute('aria-current', 'location');
  }));
});
