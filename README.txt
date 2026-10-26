Go High Level UTM Tracker 1.3.1

CAMBIOS 1.3.1
- Nueva opcion "Referrer interno" (activada por defecto): en las paginas de destino con parametros de campana oculta el referrer cuando es del propio sitio, para que GoHighLevel no clasifique la ultima atribucion como Referral. Los referrers externos no se modifican.
- El snippet de diagnostico incluye "Referrer interno oculto".

CAMBIOS 1.3.0
- En modo persistente el tiempo de conservacion se indica en dias (1 a 90). En modo sesion sigue en minutos. Internamente se guarda en minutos, compatible con versiones anteriores.
- Ayuda contextual (icono ?) en cada ajuste del panel, con explicacion y ejemplos.
- Snippet de diagnostico: muestra "no aplica" cuando el bridge o External Tracking no estan en la pagina, en vez de un false enganoso.

CAMBIOS 1.2.1
- SiteGround: exclusion por contenido del script inline (sgo_javascript_combine_excluded_inline_content). Los filtros por handle de 1.2.0 no aplicaban a un script inline.
- WP Rocket / Autoptimize: comentario marcador "ghlut-early-bridge" dentro del script para que las exclusiones por contenido funcionen.
- Cloudflare Rocket Loader: atributo data-cfasync="false".
- Primera y ultima atribucion disponibles con window.ghlutGetAttribution().
- La pagina de entrada se guarda solo como dominio + ruta (sin query). Las entradas antiguas se recortan automaticamente.
- IDs de paginas guardados como texto por versiones anteriores se comparan correctamente.
- uninstall.php elimina los ajustes al borrar el plugin.

COMPATIBILIDAD
- Conserva las opciones de 1.1: captura en todo el sitio, restauracion en paginas o todo el sitio, First/Last Touch, sessionStorage/localStorage, TTL y claves personalizadas.
- El puente no instala External Tracking de GHL; usa su script existente.
- wp_head prioridad 0, inline sin async/defer.
- WP Rocket: data-nowprocket y exclusion del defer inline segun documentacion del fabricante.
- SiteGround: exclusion de combinacion por contenido inline. En algunas versiones se requiere exclusion manual de JavaScript.
- Panel en Herramientas con deteccion de optimizadores y diagnostico del navegador para comprobar la pagina cacheada.

PRECAUCIONES
- El orden efectivo puede ser alterado por CDN/optimizadores. La deteccion no demuestra compatibilidad: ejecutar la prueba en incognito con caches activas y comprobar Network.
- No almacena automaticamente datos en GHL. GHL determina First/Latest Attribution al registrar visita/formulario.
- Los parametros restaurados en la URL tambien los leen GA4, Meta Pixel y Google Ads. Una URL compartida por un visitante puede atribuir la visita de otra persona a la campana.
- En modo persistente, una visita organica posterior sin parametros recibe la ultima campana guardada mientras no venza.
- Solo se pueden seleccionar paginas (tipo "page"); para otros tipos de contenido usar "Todo el sitio".
- Parametros click ID de prueba no representan conversiones reales.
- No combinar con otros plugins que restauren parametros en URL (HandL Append UTM, puentes anteriores).
- El consentimiento de seguimiento debe configurarse segun los requisitos del sitio.

ACTUALIZACION
Reemplazar la version anterior del mismo plugin (directorio ghl-utm-tracker). No instalar ambos. Revisar ajustes en Herramientas > Go High Level UTM Tracker y purgar caches/CDN.

DOCS
https://docs.wp-rocket.me/article/1349-delay-javascript-execution-compatibility-exclusions
https://docs.wp-rocket.me/article/976-exclude-files-from-defer-js
https://www.siteground.com/tutorials/wordpress/speed-optimizer/custom-filters
