<?php

/*
|--------------------------------------------------------------------------
| Reglas de negocio MSEA
|--------------------------------------------------------------------------
| Todo valor que antes estaba "quemado" en controladores (XP, limites de
| subida, vida de tokens) vive aqui y se puede ajustar por .env.
*/

return [

    'xp' => [
        // XP base por tipo de practica autonoma. El servidor calcula el XP;
        // el navegador nunca envia una cantidad de XP.
        'practica' => [
            'afinacion' => (int) env('MSEA_XP_AFINACION', 15),
            'ritmo' => (int) env('MSEA_XP_RITMO', 20),
            'teoria' => (int) env('MSEA_XP_TEORIA_PRACTICA', 10),
            'tecnica' => (int) env('MSEA_XP_TECNICA', 15),
            'libre' => (int) env('MSEA_XP_LIBRE', 10),
        ],
        // Bono por minuto practicado (medido por el servidor), con tope.
        'bono_por_minuto' => 1,
        'bono_maximo_por_practica' => 10,
        // Una practica debe durar al menos esto (segundos, medido en servidor) para dar XP.
        'duracion_minima_segundos' => (int) env('MSEA_PRACTICA_MIN_SEGUNDOS', 30),
        // Duracion maxima que se reconoce para una sola sesion.
        'duracion_maxima_segundos' => 3600,
        // Tope de XP diario que se puede obtener por practicas autonomas.
        'tope_diario_practica' => (int) env('MSEA_XP_TOPE_DIARIO', 150),
        // Teoria: XP completo solo si acierta al primer intento; si acierta
        // despues de fallar, una cuarta parte. Cada pregunta paga una sola vez.
        'nivel_cada' => 500,
    ],

    'racha' => [
        // Dias sin actividad permitidos entre dos dias de practica sin perder
        // la racha (no suman, pero tampoco la reinician).
        'dias_descanso' => (int) env('MSEA_RACHA_DIAS_DESCANSO', 1),
    ],

    'practica' => [
        'tipos' => ['afinacion', 'ritmo', 'teoria', 'tecnica', 'libre'],
        // Una sesion "en curso" caduca si no se finaliza en este tiempo.
        'sesion_caduca_minutos' => 120,
    ],

    'archivos' => [
        // Tamanos en kilobytes (regla "max" de Laravel).
        'audio_max_kb' => (int) env('MSEA_AUDIO_MAX_KB', 20480),
        'material_max_kb' => (int) env('MSEA_MATERIAL_MAX_KB', 15360),
        'audio_mimes' => ['mp3', 'wav', 'ogg', 'oga', 'webm', 'm4a', 'aac'],
        'audio_mimetypes' => [
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/ogg',
            'audio/webm', 'video/webm', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'video/mp4',
        ],
        'material_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'mp3', 'wav', 'ogg', 'm4a'],
        // Disco privado (storage/app/private). Nunca el disco "public".
        'disco' => env('MSEA_ARCHIVOS_DISCO', 'local'),
    ],

    'api' => [
        'token_dias' => (int) env('MSEA_TOKEN_DIAS', 30),
        'max_sesiones_por_usuario' => (int) env('MSEA_MAX_SESIONES_MOVILES', 5),
    ],

    'recuperacion' => [
        'minutos_validez' => 15,
        'max_intentos_por_codigo' => 5,
        // Fallos sumados de todos los codigos de una cuenta en 24 h (luego se bloquea).
        'max_intentos_por_dia' => (int) env('MSEA_RECUPERACION_MAX_DIA', 20),
    ],

    'digitalizador' => [
        // Programa de reconocimiento óptico de música (OMR). Audiveris es libre (AGPL) y se
        // instala aparte: en Windows, "C:\\Program Files\\Audiveris\\bin\\Audiveris.exe".
        // Vacío = sin OMR: las páginas se digitalizan escribiendo las notas en la revisión.
        'audiveris' => env('MSEA_OMR_AUDIVERIS', ''),
        // Segundos máximos por página antes de cancelar el reconocimiento.
        'tiempo_por_pagina' => (int) env('MSEA_OMR_SEGUNDOS_PAGINA', 300),
        // true = se procesa en la cola (requiere el worker de "database-largo", ver
        // DEPLOYMENT.md); false = durante la petición web, solo para desarrollo.
        // En producción es true salvo que se indique lo contrario.
        'usar_cola' => filter_var(env('MSEA_OMR_EN_COLA', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),
        'pdf_max_kb' => (int) env('MSEA_OMR_PDF_MAX_KB', 51200),
        'max_paginas_por_vez' => 20,
    ],

    'correo' => [
        // true = el correo se envia por cola (requiere "php artisan queue:work").
        'usar_cola' => (bool) env('MSEA_CORREO_EN_COLA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitio publico: datos de contacto, legal, SEO, WhatsApp y analitica
    |--------------------------------------------------------------------------
    | Todo lo que depende de datos reales de la fundacion se lee del .env.
    | Si un dato esta vacio, simplemente no se muestra (nada se inventa).
    */
    'sitio' => [
        'nombre' => 'Fundación Movimiento Sinfónico de El Alto (MSEA)',
        'nombre_corto' => 'MSEA',
        'descripcion' => 'Plataforma del Movimiento Sinfónico de El Alto: practica violín, viola, violonchelo y contrabajo, afina con el micrófono y gana puntos.',
        'ciudad' => 'El Alto',
        'pais' => 'Bolivia',
        'codigo_pais' => 'BO',
        'idioma' => 'es',
        // Fecha de la ultima revision de los textos legales.
        'legal_actualizado' => env('MSEA_LEGAL_ACTUALIZADO', '2026-10-15'),
        'razon_social' => env('MSEA_LEGAL_RAZON_SOCIAL', 'Fundación Movimiento Sinfónico de El Alto'),
        'nit' => env('MSEA_LEGAL_NIT', ''),
        'direccion' => env('MSEA_CONTACTO_DIRECCION', ''),
        'correo' => env('MSEA_CONTACTO_CORREO', ''),
        'telefono' => env('MSEA_CONTACTO_TELEFONO', ''),
        // Solo digitos con codigo de pais, sin "+" (ej. 5917XXXXXXX). Vacio = sin boton.
        'whatsapp' => preg_replace('/\D+/', '', (string) env('MSEA_WHATSAPP', '')),
        'whatsapp_mensaje' => env('MSEA_WHATSAPP_MENSAJE', 'Hola, quiero información sobre el Movimiento Sinfónico de El Alto (MSEA).'),
        // Perfiles oficiales (se usan en los datos estructurados para Google).
        'redes' => array_values(array_filter([
            env('MSEA_RED_FACEBOOK'), env('MSEA_RED_INSTAGRAM'), env('MSEA_RED_YOUTUBE'),
            env('MSEA_RED_TIKTOK'), env('MSEA_FICHA_GOOGLE_URL'),
        ])),
        // Analitica: apagada por defecto. Solo carga en paginas publicas y despues de que
        // la persona acepte las cookies de analitica. Nunca en los paneles con sesion.
        'analitica' => [
            'ga4' => env('MSEA_ANALITICA_GA4', ''),
            'plausible_dominio' => env('MSEA_ANALITICA_PLAUSIBLE', ''),
            'plausible_script' => env('MSEA_ANALITICA_PLAUSIBLE_SCRIPT', 'https://plausible.io/js/script.js'),
        ],
        // Forzar HTTPS: por defecto solo en produccion. MSEA_FORZAR_HTTPS=false lo apaga.
        'forzar_https' => env('MSEA_FORZAR_HTTPS'),
        // Proxies de confianza (Nginx, Cloudflare...). "*" = todos; vacio = ninguno.
        'proxies' => env('MSEA_PROXIES', ''),
        // Proteccion de formularios publicos (campo trampa + tiempo minimo).
        'antispam' => [
            'activo' => (bool) env('MSEA_ANTISPAM', true),
            'segundos_minimos' => (int) env('MSEA_ANTISPAM_SEGUNDOS', 2),
        ],
    ],
];
