/* MSEA - Pagina de curso del estudiante: preguntas evaluadas en servidor y avance de lecciones. */
(function () {
  'use strict';
  const UI = window.MSEA_UI;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.pregunta').forEach((caja) => {
      const estado = caja.querySelector('.pregunta-estado');
      caja.querySelectorAll('button[data-respuesta]').forEach((boton) => {
        boton.addEventListener('click', async () => {
          caja.querySelectorAll('button').forEach((b) => { b.disabled = true; });
          try {
            const r = await UI.api(`/dashboard-estudiante/teoria/${Number(caja.dataset.id)}/responder`, { json: { respuesta: boton.dataset.respuesta } });
            boton.classList.add(r.correcta ? 'ok' : 'bad');
            if (r.correcta) {
              caja.dataset.resuelta = '1';
              estado.textContent = r.xp ? `Correcto: +${r.xp} XP${r.primer_intento ? '' : ' (menos XP por no ser el primer intento)'}` : 'Correcto.';
            } else {
              estado.textContent = `No era esa. La respuesta correcta es: ${r.respuesta_correcta}. Márcala para seguir con la lección.`;
              setTimeout(() => caja.querySelectorAll('button').forEach((b) => { b.disabled = false; b.classList.remove('bad'); }), 1500);
            }
          } catch (err) {
            UI.toast(err.message);
            caja.querySelectorAll('button').forEach((b) => { b.disabled = false; });
          }
        });
      });
    });

    document.querySelectorAll('.btn-completar').forEach((boton) => {
      boton.addEventListener('click', async () => {
        try {
          const r = await UI.conCarga(boton, UI.api(`/dashboard-estudiante/lecciones/${Number(boton.dataset.id)}/completar`, { json: {} }));
          UI.toast(r.xp ? `Lección completada: +${r.xp} XP` : r.message);
          setTimeout(() => window.location.reload(), 900);
        } catch (err) {
          UI.toast(err.message);
        }
      });
    });
  });
})();
