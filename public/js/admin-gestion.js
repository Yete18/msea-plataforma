/* MSEA - Paginas de gestion del director: confirmaciones y filtro de tablas (sin scripts en linea). */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirmar]').forEach((boton) => {
    boton.addEventListener('click', (e) => {
      if (!window.confirm(boton.dataset.confirmar)) e.preventDefault();
    });
  });

  document.querySelectorAll('[data-filtrar]').forEach((input) => {
    const tabla = document.querySelector(input.dataset.filtrar);
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase().trim();
      tabla?.querySelectorAll('tbody tr').forEach((fila) => {
        fila.hidden = q !== '' && !fila.textContent.toLowerCase().includes(q);
      });
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      const boton = form.querySelector('button[type="submit"]');
      if (boton && !boton.dataset.confirmar) {
        boton.classList.add('is-loading');
        setTimeout(() => { boton.disabled = true; }, 0);
      }
    });
  });
});
