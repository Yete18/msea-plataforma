# Pruebas de aceptacion del sistema MSEA

## Objetivo

Validar que el sistema MSEA cumple los requerimientos funcionales solicitados por el cliente y definir pruebas pendientes para las funcionalidades que deben implementarse en siguientes iteraciones.

## Alcance

Las pruebas cubren los modulos de autenticacion, registro, recuperacion de contrasena, panel del estudiante, panel del profesor, panel del director, reportes, logs de seguridad, registro cabecera-detalle, ayuda, alarmas, notificaciones, API movil y requisitos no funcionales.

## Criterios generales de aceptacion

- El usuario solo accede a las opciones permitidas por su rol.
- Las acciones exitosas muestran confirmacion o redireccion esperada.
- Los datos ingresados se validan antes de guardarse.
- La informacion guardada queda persistida en la base de datos.
- Las pantallas principales cargan sin errores.
- Las respuestas JSON y descargas devuelven el formato esperado.
- Las funcionalidades pendientes quedan identificadas como no aprobadas hasta su implementacion.

## Casos de prueba funcionales

| ID | Requerimiento | Precondicion | Pasos de prueba | Resultado esperado | Estado |
| --- | --- | --- | --- | --- | --- |
| PA-01 | Inicio de sesion | Existe un usuario registrado con rol valido | Ingresar correo, contrasena y rol correcto en `/login` | El sistema redirige al dashboard correspondiente al rol | Aprobado |
| PA-02 | Rechazo de credenciales invalidas | No existe el correo o la contrasena es incorrecta | Intentar iniciar sesion con datos invalidos | El sistema rechaza el acceso y muestra mensaje de error | Aprobado |
| PA-03 | Control de acceso por rol | Existe sesion de estudiante | Intentar ingresar a `/dashboard-admin` | El sistema redirige a login o bloquea el acceso | Aprobado |
| PA-04 | Registro publico de estudiante | Usuario no autenticado | Completar formulario de registro con rol estudiante | Se crea usuario, estudiante e instrumento, y se redirige al dashboard estudiante | Aprobado |
| PA-05 | Bloqueo de registro publico de profesor | Usuario no autenticado | Intentar registrarse con rol profesor desde formulario publico | El sistema rechaza el registro del profesor | Aprobado |
| PA-06 | Recuperacion de contrasena | Existe un usuario con correo registrado | Solicitar codigo, verificarlo y registrar nueva contrasena | La contrasena cambia y el codigo no puede reutilizarse | Aprobado |
| PA-07 | Dashboard estudiante | Existe estudiante con datos academicos | Iniciar sesion como estudiante y abrir `/dashboard-estudiante` | Se muestran datos personales, instrumento y progreso disponible | Aprobado |
| PA-08 | Actualizacion de perfil estudiante | Existe sesion de estudiante | Enviar nuevos datos del perfil por el formulario | Se actualizan los datos y se conserva la contrasena si no se solicita cambio | Aprobado |
| PA-09 | Foto de perfil | Existe estudiante autenticado | Subir foto en formato base64 desde el perfil | La foto se guarda como archivo y se muestra en el dashboard | Aprobado |
| PA-10 | Registro de practica | Existe estudiante autenticado | Registrar una practica desde el dashboard | El tiempo de practica queda guardado y se refleja en el panel | Parcial |
| PA-11 | Entrega de tareas | Existe tarea asignada al estudiante | Enviar una entrega de tarea | La entrega queda registrada para revision del profesor | Parcial |
| PA-12 | Dashboard profesor | Existe profesor autenticado | Abrir `/dashboard-profesor` | Se muestran alumnos, tareas y datos disponibles del profesor | Aprobado |
| PA-13 | Creacion de tareas | Existe profesor autenticado | Crear tarea con titulo, descripcion y datos requeridos | La tarea queda registrada y visible para seguimiento | Aprobado |
| PA-14 | Calificacion de tareas | Existe entrega de estudiante | Profesor registra nota y comentario | La entrega queda calificada | Parcial |
| PA-15 | Dashboard director | Existe director autenticado | Abrir `/dashboard-admin` | Se muestran centralizador, profesores, estudiantes, elencos y actividad | Aprobado |
| PA-16 | Registro de profesor | Existe director autenticado | Crear profesor desde el panel director | Se crea usuario profesor, contrasena protegida y especialidad | Aprobado |
| PA-17 | Gestion de elencos | Existe director autenticado | Crear, editar y eliminar elenco | El sistema guarda cambios y actualiza estudiantes relacionados | Aprobado |
| PA-18 | Asignacion de estudiante a elenco | Existe estudiante y elenco | Asignar estudiante a un elenco desde el panel director | El estudiante queda vinculado al elenco seleccionado | Aprobado |
| PA-19 | Asignacion de estudiante a profesor | Existe estudiante y profesor | Asignar profesor desde el panel director | El estudiante queda vinculado al profesor seleccionado | Aprobado |
| PA-20 | Perfil del director | Existe director autenticado | Actualizar datos personales del director | Los datos se guardan y la sesion se actualiza | Aprobado |
| PA-21 | Generacion de reportes | Existe director autenticado | Abrir modulo de reportes | Se muestran reportes disponibles de estudiantes, profesores y actividad | Aprobado |
| PA-22 | Exportacion CSV | Existe director autenticado | Descargar reporte de estudiantes | El sistema descarga CSV con cabecera y datos esperados | Aprobado |
| PA-23 | Logs de seguridad | Existen usuarios con ultimo ingreso | Abrir modulo de seguridad | Se muestran eventos de ingreso y usuarios por rol | Parcial |
| PA-24 | Registro cabecera-detalle | Existen elencos y estudiantes | Abrir modulo cabecera-detalle | Se muestra la cabecera del elenco y el detalle de estudiantes | Aprobado |
| PA-25 | Alarmas administrativas | Existen tareas vencidas o estudiantes sin asignacion | Abrir modulo de alarmas | Se listan alertas por prioridad | Parcial |
| PA-26 | Modulo de ayuda | Usuario abre panel director | Revisar seccion de ayuda | Se muestran instrucciones basicas de uso de los modulos | Aprobado |
| PA-27 | Dashboard padre | Existe usuario padre asociado a estudiante | Iniciar sesion como padre | El padre puede ver progreso, tareas y alertas de su hijo | Pendiente |
| PA-28 | Notificaciones | Existen notificaciones pendientes | Abrir panel del usuario | El sistema muestra notificaciones y permite marcarlas como leidas | Pendiente |
| PA-29 | Mensajeria interna | Existen usuarios de distintos roles | Enviar mensaje entre profesor, estudiante, padre o director | El destinatario recibe y lee el mensaje | Pendiente |
| PA-30 | Gamificacion | Estudiante completa ejercicios o practicas | Registrar avance academico | El sistema actualiza XP, nivel, logros y racha | Parcial |
| PA-31 | Cursos, modulos y lecciones | Existe profesor o director autorizado | Crear curso con modulos y lecciones | El contenido queda disponible para estudiantes inscritos | Pendiente |
| PA-32 | Ejercicios musicales | Existe estudiante autenticado | Completar ejercicio de teoria, ritmo o afinacion | El sistema registra puntaje, precision y progreso | Parcial |
| PA-33 | API movil | Existe app movil Flutter | Iniciar sesion desde movil y consultar dashboard | La API responde datos del usuario autenticado | Parcial |
| PA-34 | Tokens moviles | Existe usuario movil autenticado | Iniciar y cerrar sesion desde la app movil | El token se crea, valida y revoca correctamente | Parcial |

## Casos de prueba no funcionales

| ID | Requerimiento no funcional | Prueba de aceptacion | Resultado esperado | Estado |
| --- | --- | --- | --- | --- |
| PANF-01 | Seguridad de contrasenas | Revisar que las contrasenas se almacenen con hash | No existen contrasenas nuevas en texto plano | Aprobado |
| PANF-02 | Compatibilidad con contrasenas antiguas | Iniciar sesion con contrasena legacy valida | El sistema permite acceso y actualiza la contrasena a hash | Aprobado |
| PANF-03 | Validacion de datos | Enviar formularios con campos requeridos vacios o invalidos | El sistema rechaza la solicitud y muestra errores | Aprobado |
| PANF-04 | Autorizacion | Intentar acceder a rutas de otro rol | El sistema bloquea el acceso | Aprobado |
| PANF-05 | Integridad referencial | Crear estudiantes, profesores, tareas y elencos relacionados | Las relaciones quedan consistentes en base de datos | Parcial |
| PANF-06 | Rendimiento | Cargar dashboards con volumen alto de usuarios y tareas | La respuesta se mantiene en tiempos aceptables | Pendiente |
| PANF-07 | Usabilidad | Revisar flujo principal por rol | Las opciones son claras y el usuario completa tareas sin asistencia tecnica | Parcial |
| PANF-08 | Responsividad | Abrir dashboards en escritorio y movil | La interfaz se adapta sin perdida de informacion | Parcial |
| PANF-09 | Auditoria | Ejecutar acciones administrativas | Se registra quien hizo la accion, fecha y detalle | Pendiente |
| PANF-10 | Respaldo | Simular restauracion desde copia de seguridad | La informacion puede recuperarse correctamente | Pendiente |
| PANF-11 | Disponibilidad | Verificar despliegue en ambiente productivo | El sistema esta accesible durante el horario definido por la institucion | Pendiente |
| PANF-12 | Portabilidad | Instalar el sistema desde cero usando migraciones | La app funciona en un nuevo entorno configurado | Aprobado |
| PANF-13 | Mantenibilidad | Revisar separacion entre Controllers, Requests, Services y Models | La logica esta separada y es modificable sin afectar todo el sistema | Aprobado |
| PANF-14 | Accesibilidad | Verificar contraste, etiquetas y navegacion basica | La interfaz puede usarse de forma clara por distintos usuarios | Pendiente |

## Evidencia automatizada disponible

Las siguientes pruebas automatizadas respaldan parte de la aceptacion:

- `tests/Feature/AuthFlowTest.php`
- `tests/Feature/PasswordResetFlowTest.php`
- `tests/Feature/DashboardEstudianteTest.php`
- `tests/Feature/DashboardProfesorTest.php`
- `tests/Feature/DashboardAdminTest.php`
- `tests/Feature/AdminAccessFlowTest.php`

## Comando de ejecucion

```bash
php artisan test
```

Para validar solo los flujos administrativos:

```bash
php artisan test --filter=DashboardAdminTest
```

## Observaciones

- Las pruebas marcadas como Aprobado tienen funcionalidad implementada y/o cobertura automatizada.
- Las pruebas marcadas como Parcial tienen estructura o flujo inicial, pero requieren completar reglas de negocio, vistas o persistencia.
- Las pruebas marcadas como Pendiente representan funcionalidades que el cliente deberia solicitar como parte de una siguiente iteracion.
