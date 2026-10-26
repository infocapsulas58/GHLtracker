# Go High Level UTM Tracker

Plugin de WordPress que conserva la primera y la última atribución de campaña (UTM y click IDs) en el navegador del visitante. En las páginas elegidas restaura los parámetros de la última campaña en la URL **antes** de que se ejecute el script External Tracking de GoHighLevel, para que GHL registre la visita y el formulario con la atribución correcta.

No instala ni reemplaza el script de GHL: trabaja con el que ya tenga el sitio.

## Características

- Captura en todo el sitio público y restauración en páginas seleccionadas o en todo el sitio.
- First Touch y Last Touch por separado. Solo se restaura la última, para no mezclar GCLID de distintas campañas.
- Almacenamiento por sesión (`sessionStorage`) o persistente (`localStorage`), con un plazo configurable de 5 minutos a 90 días.
- Parámetros incluidos: `utm_*`, `gclid`, `gbraid`, `wbraid`, `gad_source`, `gad_campaignid`, `msclkid`, `fbclid`, `ttclid`, `li_fat_id`, `ctwa_clid` y otros. Se pueden añadir claves propias.
- `window.ghlutGetAttribution()` devuelve `{first, last}` para otros scripts, por ejemplo para rellenar campos ocultos.
- Compatible con WP Rocket, SiteGround Speed Optimizer, Autoptimize, LiteSpeed y Cloudflare Rocket Loader mediante exclusiones y atributos.
- Panel de diagnóstico en **Herramientas > Go High Level UTM Tracker**, con un snippet para comprobar en el navegador la página cacheada.

## Requisitos

- WordPress 5.3 o superior
- PHP 7.4 o superior
- El script External Tracking de GoHighLevel instalado en el sitio

## Instalación

1. Descarga el zip de la última versión en [Releases](../../releases), o clona este repositorio en `wp-content/plugins/ghl-utm-tracker`.
2. Activa el plugin y configúralo en **Herramientas > Go High Level UTM Tracker**. El seguimiento empieza desactivado.
3. Purga las cachés y la CDN.
4. Comprueba en incógnito: entra con UTM, navega a una página seleccionada y revisa el payload `external-tracking/events` en DevTools > Network.

## Precauciones

- Los parámetros restaurados en la URL también los leen GA4, Meta Pixel y Google Ads. Si un visitante comparte la URL, la visita de quien la abra puede atribuirse a la campaña.
- En modo persistente, una visita orgánica posterior del mismo navegador (sin parámetros) recibe la última campaña guardada mientras no venza.
- No lo combines con otros plugins que restauren parámetros en la URL (por ejemplo, la opción "Append UTM" de HandL).
- El consentimiento de seguimiento debe configurarse según la normativa aplicable al sitio. El plugin no elude mecanismos de consentimiento.

## Licencia

[GPL-2.0-or-later](LICENSE)
