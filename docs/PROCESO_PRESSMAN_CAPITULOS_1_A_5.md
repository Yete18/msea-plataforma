# Mejora de MSEA con Pressman (capitulos 1 a 5)

## Proposito y alcance

MSEA es una aplicacion web de apoyo a la formacion musical con tres actores principales: estudiantes, profesores y director. Esta iteracion fortalece los flujos de acceso, registro, recuperacion de contrasena y gestion de perfil, sin cambiar las rutas existentes ni el modelo operativo de los roles.

El documento usa como referencia la 9.a edicion de *Software Engineering: A Practitioner's Approach* de Roger S. Pressman y Bruce R. Maxim. En ella, los capitulos 1 a 5 abarcan software e ingenieria de software, modelos de proceso, agilidad, el proceso recomendado y factores humanos.

## 1. Principios de ingenieria aplicados

- Se separa la responsabilidad: vistas para la experiencia, Form Requests para reglas de entrada, servicios para logica y middleware para controles transversales.
- Se preserva la compatibilidad del sistema: roles, URLs, sesiones y pantallas siguen funcionando sin requerir migracion del usuario.
- Se consideran atributos de calidad desde el inicio: seguridad, usabilidad, mantenibilidad y trazabilidad de cambios.
- Se automatizan verificaciones con la suite de pruebas Laravel antes de publicar una iteracion.

## 2. Modelo de proceso para esta iteracion

Se adopta un ciclo incremental orientado a prototipos, adecuado para mejorar un sistema que ya esta en uso.

1. Definir necesidades y riesgos con estudiantes, profesores y direccion.
2. Prototipar las pantallas de acceso con Bootstrap y validar su uso en escritorio y movil.
3. Implementar controles de servidor y cliente.
4. Ejecutar pruebas de regresion y corregir hallazgos.
5. Revisar el resultado con usuarios representativos y priorizar el siguiente incremento.

Cada incremento debe tener criterios de aceptacion, una persona responsable y una evidencia de prueba. Esto permite aprender de cada prototipo antes de extender los cambios a los tableros completos.

## 3. Historias y requisitos priorizados

| ID | Necesidad | Criterio de aceptacion |
| --- | --- | --- |
| RF-01 | Como estudiante, quiero registrarme con datos validos para acceder a mi tablero. | Solo se crea una cuenta estudiante con correo unico y contrasena valida. |
| RF-02 | Como usuario, quiero iniciar sesion de forma segura. | El sistema valida correo, contrasena y rol, regenera la sesion y limita intentos. |
| RF-03 | Como usuario, quiero recuperar mi contrasena sin revelar si mi correo existe. | El sistema siempre devuelve un mensaje generico y usa un codigo temporal de un solo uso. |
| RF-04 | Como estudiante, quiero actualizar mi perfil y foto. | Solo el propietario puede modificarlo y la foto debe ser una imagen valida de hasta 2 MB. |
| RNF-01 | Seguridad | Formularios con CSRF, contrasenas de 8+ caracteres con letras y numeros, limitacion de intentos y encabezados HTTP defensivos. |
| RNF-02 | Usabilidad | Mensajes de error claros, persistencia de correo al fallar el login y alertas Bootstrap descartables. |
| RNF-03 | Compatibilidad | Las mejoras no cambian rutas ni contratos de los dashboards existentes. |

## 4. Riesgos y decisiones

| Riesgo | Impacto | Mitigacion aplicada |
| --- | --- | --- |
| Fuerza bruta en acceso o recuperacion | Alto | Limites por correo e IP en login, registro, envio y verificacion de codigo. |
| Archivos simulados como imagen | Alto | Se decodifica Base64, se limita el tamano y se verifica el contenido real PNG, JPG o WEBP. |
| Contraseñas faciles de adivinar | Alto | Regla de minimo 8 caracteres, con letras y numeros. |
| Insercion de datos no confiables en el DOM | Alto | Deuda tecnica identificada: reemplazar los usos de `innerHTML` con nodos DOM o escape explicito en los tableros. |
| Cambio visual que afecte el aprendizaje | Medio | Bootstrap se aplica primero a los flujos de acceso, que son independientes de los tableros. |

## 5. Aspectos humanos y forma de trabajo

- Estudiante: valida claridad, lenguaje y accesibilidad de registro, perfil y recuperacion.
- Profesor: valida que el cambio no interrumpa asignacion ni revision de tareas.
- Director: prioriza riesgos, acepta incrementos y define reglas de datos institucionales.
- Equipo tecnico: mantiene pruebas, revisa seguridad y registra decisiones en este documento.

La retroalimentacion debe recogerse despues de cada incremento con preguntas cortas: que fue claro, que impidio completar una tarea y que informacion falto. El siguiente ciclo se prioriza por impacto en estudiantes y riesgo para los datos.

## Evidencia de esta entrega

- Bootstrap 5.3.3 se incorpora en acceso, registro y recuperacion para alertas y controles visuales consistentes.
- Los mensajes del servidor se centralizan en `resources/views/partials/flash-messages.blade.php`.
- Las rutas sensibles usan limitadores nombrados.
- Las fotos Base64 se validan por contenido antes de almacenarse.
- La suite de pruebas debe ejecutarse con `php artisan test` antes de desplegar.
