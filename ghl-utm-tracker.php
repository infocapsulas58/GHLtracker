<?php
/**
 * Plugin Name: Go High Level UTM Tracker
 * Description: Conserva primera y ultima atribucion y restaura sus parametros en paginas seleccionadas antes de que External Tracking registre la visita.
 * Version: 1.2.1
 * Author: Connection Pro
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ghl-utm-tracker
 */
if (!defined('ABSPATH')) { exit; }

final class GHL_UTM_Tracker {
    const OPTION = 'ghlut_settings';
    const VER = '1.2.1';
    const MARKER = 'ghlut-early-bridge';
    const KNOWN_KEYS = array('utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','gclid','gbraid','wbraid','gad_source','gad_campaignid','msclkid','fbclid','campaign_id','ad_group_id','ad_id','utm_matchtype','utm_keyword','ctwa_clid','ttclid','li_fat_id');
    private static $instance = null;
    public static function instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }
    private function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'settings'));
        add_action('wp_head', array($this, 'output_script'), 0);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links'));
        // Documented WP Rocket exclusion for inline JavaScript defer.
        add_filter('rocket_defer_inline_exclusions', array($this, 'rocket_inline_exclusions'));
        // SiteGround Speed Optimizer: the script is printed inline (no handle), so exclude it by content.
        add_filter('sgo_javascript_combine_excluded_inline_content', array($this, 'add_marker'));
        // Autoptimize: exclude by inline content as well.
        add_filter('autoptimize_filter_js_exclude', array($this, 'autoptimize_exclusions'));
    }
    // Both filters match against the script body, which carries MARKER as a comment.
    public function rocket_inline_exclusions($patterns) { return $this->add_marker($patterns); }
    public function add_marker($patterns) {
        if (!is_array($patterns)) return $patterns;
        $patterns[] = self::MARKER;
        return array_values(array_unique($patterns));
    }
    public function autoptimize_exclusions($exclude) {
        if (!is_string($exclude)) return $exclude;
        return $exclude === '' ? self::MARKER : $exclude . ', ' . self::MARKER;
    }
    public static function defaults() {
        return array('enabled' => 0, 'scope' => 'selected', 'pages' => array(), 'ttl' => 120, 'mode' => 'session', 'debug' => 0,
          'extra_keys' => '');
    }
    public function options() { return wp_parse_args((array)get_option(self::OPTION, array()), self::defaults()); }
    public function action_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('tools.php?page=ghl-utm-tracker')) . '">Configurar</a>');
        return $links;
    }
    public function menu() {
        add_management_page('Go High Level UTM Tracker', 'Go High Level UTM Tracker', 'manage_options', 'ghl-utm-tracker', array($this, 'render'));
    }
    public function settings() {
        register_setting('ghlut_group', self::OPTION, array('type'=>'array', 'sanitize_callback'=>array($this,'sanitize'), 'default'=>self::defaults()));
    }
    public function sanitize($v) {
        $v = is_array($v) ? $v : array();
        $scope = isset($v['scope']) && $v['scope'] === 'all' ? 'all' : 'selected';
        $pages = isset($v['pages']) && is_array($v['pages']) ? array_map('absint', $v['pages']) : array();
        $pages = array_values(array_unique(array_filter($pages, function($id){ return get_post_type($id) === 'page'; })));
        $ttl = isset($v['ttl']) ? absint($v['ttl']) : 120;
        $ttl = min(129600, max(5, $ttl));
        $mode = isset($v['mode']) && $v['mode'] === 'persistent' ? 'persistent' : 'session';
        $known = self::KNOWN_KEYS;
        $extra = isset($v['extra_keys']) ? (string)$v['extra_keys'] : '';
        $extra = preg_split('/[\s,]+/', strtolower($extra));
        $extra = array_filter(array_unique(array_slice($extra,0,30)), function($key) use ($known) {
            return preg_match('/^[a-z][a-z0-9_]{0,49}$/', $key) && !in_array($key, $known, true);
        });
        return array('enabled'=>empty($v['enabled'])?0:1, 'scope'=>$scope, 'pages'=>$pages, 'ttl'=>$ttl, 'mode'=>$mode,
          'debug'=>empty($v['debug'])?0:1, 'extra_keys'=>implode(', ', $extra));
    }
    public function render() {
        if (!current_user_can('manage_options')) return;
        $o = $this->options();
        $o['pages'] = array_map('intval', (array)$o['pages']);
        $pages = get_pages(array('sort_column'=>'post_title', 'sort_order'=>'ASC', 'post_status'=>'publish'));
        $name = self::OPTION;
        ?>
        <div class="wrap" style="max-width:1050px">
          <h1>Go High Level UTM Tracker <small style="color:#646970">v<?php echo esc_html(self::VER); ?></small></h1>
          <p>Guarda First Touch y Last Touch por separado; restaura unicamente los parametros del ultimo origen en las paginas elegidas <strong>antes</strong> de que GoHighLevel External Tracking capture el evento. No instala ni reemplaza el script de GHL.</p>
          <?php settings_errors(); ?>
          <form method="post" action="options.php">
            <?php settings_fields('ghlut_group'); ?>
            <table class="form-table" role="presentation">
              <tr><th scope="row">Activar seguimiento</th><td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[enabled]" value="1" <?php checked($o['enabled'],1); ?>> Habilitar capturas y restauracion</label><p class="description">Empieza desactivado hasta que se configure.</p></td></tr>
              <tr><th scope="row">Donde restaurar parametros</th><td>
                <fieldset>
                  <label><input type="radio" name="<?php echo esc_attr($name); ?>[scope]" value="selected" <?php checked($o['scope'],'selected'); ?>> Solo paginas seleccionadas</label><br>
                  <label><input type="radio" name="<?php echo esc_attr($name); ?>[scope]" value="all" <?php checked($o['scope'],'all'); ?>> Todo el sitio</label>
                </fieldset><p class="description">La captura se hace en todas las paginas publicas. Esta opcion determina donde reaparecen los parametros en la URL.</p>
              </td></tr>
              <tr><th scope="row">Paginas de destino</th><td>
                <input type="search" id="ghlut-page-search" placeholder="Buscar pagina..." class="regular-text" aria-label="Buscar paginas">
                <div id="ghlut-pages" style="max-height:300px;overflow:auto;border:1px solid #c3c4c7;background:#fff;padding:12px;margin-top:8px;max-width:650px">
                <?php foreach ($pages as $p): ?>
                  <label style="display:block;padding:5px 0" data-ghlut-title="<?php echo esc_attr(strtolower($p->post_title)); ?>">
                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[pages][]" value="<?php echo esc_attr($p->ID); ?>" <?php checked(in_array($p->ID,$o['pages'],true)); ?>>
                    <?php echo esc_html($p->post_title); ?> <code><?php echo esc_html(wp_parse_url(get_permalink($p->ID), PHP_URL_PATH)); ?></code>
                  </label>
                <?php endforeach; ?>
                </div><p class="description">Se ignora si seleccionas "Todo el sitio". No afecta al administrador de WordPress.</p>
              </td></tr>
              <tr><th scope="row">Modo de almacenamiento</th><td><fieldset>
                <label><input type="radio" name="<?php echo esc_attr($name); ?>[mode]" value="session" <?php checked($o['mode'],'session'); ?>> <strong>Sesion actual</strong> (sessionStorage: normalmente la misma pestana)</label><br>
                <label><input type="radio" name="<?php echo esc_attr($name); ?>[mode]" value="persistent" <?php checked($o['mode'],'persistent'); ?>> <strong>Persistencia entre visitas</strong> (localStorage: sobrevive cierre y reapertura del navegador)</label>
                </fieldset><p class="description">El modo persistente respeta la duracion configurada, pero el navegador puede borrar o bloquear el almacenamiento. El cambio de modo no transfiere datos antiguos de un modo a otro.</p></td></tr>
              <tr><th scope="row">Tiempo de conservacion</th><td><input type="number" min="5" max="129600" step="1" name="<?php echo esc_attr($name); ?>[ttl]" value="<?php echo esc_attr($o['ttl']); ?>"> minutos <p class="description">Por defecto 120 minutos. Maximo 90 dias (129600 minutos). Cada entrada tiene vencimiento fijo: navegar no renueva automaticamente el plazo.</p></td></tr>
              <tr><th scope="row">Parametros adicionales</th><td><input type="text" class="large-text" name="<?php echo esc_attr($name); ?>[extra_keys]" value="<?php echo esc_attr($o['extra_keys']); ?>" placeholder="ejemplo: parametro_propio, tracking_key"><p class="description">Separados por comas. Los parametros UTM y clics publicitarios habituales ya vienen incluidos. No agregues contrasenas, tokens ni datos personales.</p></td></tr>
              <tr><th scope="row">Diagnostico</th><td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[debug]" value="1" <?php checked($o['debug'],1); ?>> Mostrar mensajes de diagnostico en consola (solo para administradores conectados)</label><p class="description">No imprime valores completos de identificadores de clic.</p></td></tr>
            </table>
            <?php submit_button('Guardar configuracion'); ?>
          </form>
          <hr><h2>Compatibilidad y diagnostico</h2>
          <?php
            $active = (array)get_option('active_plugins', array());
            if (is_multisite()) $active = array_merge($active, array_keys((array)get_site_option('active_sitewide_plugins', array())));
            $has_rocket = defined('WP_ROCKET_VERSION') || (bool)array_filter($active, function($s){ return strpos($s, 'wp-rocket/') === 0; });
            $has_sg = defined('SG_OPTIMIZER_VERSION') || (bool)array_filter($active, function($s){ return strpos($s, 'sg-cachepress/') === 0; });
          ?>
          <table class="widefat striped" style="max-width:850px"><tbody>
          <tr><th>Cloudflare Rocket Loader</th><td>No verificable desde PHP</td><td>Atributo <code>data-cfasync="false"</code> para que no se retrase el script.</td></tr>
          <tr><th>WP Rocket</th><td><?php echo $has_rocket ? 'Detectado' : 'No detectado'; ?></td><td>Atributo <code>data-nowprocket</code> y filtro de exclusion del defer inline.</td></tr>
          <tr><th>SiteGround Speed Optimizer</th><td><?php echo $has_sg ? 'Detectado' : 'No detectado'; ?></td><td>Exclusion por contenido del script inline (<code>sgo_javascript_combine_excluded_inline_content</code>).</td></tr>
          <tr><th>Captura temprana</th><td><?php echo $o['enabled'] ? 'Configurada' : 'Desactivada'; ?></td><td>Se imprime en <code>wp_head</code>, prioridad 0, como script sin <code>async</code> ni <code>defer</code>.</td></tr>
          <tr><th>External Tracking de GHL</th><td>No verificable desde PHP</td><td>Este plugin no lo instala ni lo modifica. Revisar la carga en el navegador.</td></tr>
          </tbody></table>
          <p class="description">Estas comprobaciones muestran la configuracion, no certifican que una cache o CDN externa no haya reescrito el HTML. Valida siempre el HTML entregado a visitantes anonimos.</p>
          <h3>Verificacion en la pagina publica</h3>
          <p>Abre la pagina en una ventana de incognito, DevTools &gt; Console y ejecuta este diagnostico. No imprime valores completos de UTM ni identificadores.</p>
          <textarea readonly rows="12" class="large-text code" id="ghlut-check-snippet">(function(){
 const s=document.getElementById('ghlut-early-bridge');
 const g=[...document.scripts].filter(x=>(x.src||'').includes('external-tracking.js'));
 const state=window.__ghlutRuntime||{};
 console.table({
  'Bridge presente':!!s,
  'Bridge ejecutado':state.executed===true,
  'Bridge antes de GHL en DOM':!!(s&&g[0]&&(s.compareDocumentPosition(g[0])&Node.DOCUMENT_POSITION_FOLLOWING)),
  'WP Rocket no retrasado':!!(s&&!/rocketlazyloadscript/i.test(s.type||'')&&!s.hasAttribute('data-rocket-src')),
  'Scripts External Tracking':g.length,
  'Parametros de marketing en URL':new URL(location.href).searchParams.toString().length>0,
  'Restauracion':state.restored===true,
  'Modo':state.mode||'no disponible'
 });
})();</textarea>
          <p><button type="button" class="button" id="ghlut-copy-diagnostic">Copiar diagnostico</button> <span id="ghlut-copy-status" role="status"></span></p>
          <script>(function(){var b=document.getElementById('ghlut-copy-diagnostic');if(!b)return;b.addEventListener('click',function(){var t=document.getElementById('ghlut-check-snippet');t.select();try{document.execCommand('copy');document.getElementById('ghlut-copy-status').textContent='Copiado.';}catch(e){document.getElementById('ghlut-copy-status').textContent='Selecciona y copia manualmente.';}});})();</script>
          <p><strong>WP Rocket:</strong> la exclusion <code>data-nowprocket</code> aplica a Delay JS. Si tienes un optimizador adicional o una capa CDN que transforma HTML, puede ser necesario excluir manualmente este script y <code>external-tracking.js</code>. No desactives la cache HTML globalmente.</p>
          <hr><h2>Comportamiento de atribucion</h2>
          <p><strong>Primera atribucion:</strong> se guarda la primera entrada con parametros de marketing dentro del periodo vigente. <strong>Ultima atribucion:</strong> se actualiza cuando llega una nueva combinacion de parametros. Solo se restaura la ultima para no mezclar GCLID de distintas campanas. Son datos del navegador; no se escriben directamente en los campos First/Latest Attribution de GoHighLevel.</p>
          <p>Ambas quedan disponibles para otros scripts con <code>window.ghlutGetAttribution()</code>, que devuelve <code>{first, last}</code> con <code>ts</code>, <code>landing</code> (solo dominio y ruta) y <code>params</code>, por ejemplo para rellenar campos ocultos de un formulario.</p>
          <p><strong>Efectos secundarios de la restauracion:</strong> los parametros restaurados en la URL tambien los leen GA4, Meta Pixel y Google Ads. Si un visitante copia y comparte la URL, la visita de la otra persona puede atribuirse a la campana. En modo persistente, una visita organica posterior (sin parametros) recibe la ultima campana guardada mientras no venza.</p>
          <p><button type="button" id="ghlut-clear-data" class="button">Limpiar datos de prueba en este navegador</button> <span id="ghlut-clear-result" role="status"></span></p>
          <script>(function(){var b=document.getElementById('ghlut-clear-data');if(!b)return;b.addEventListener('click',function(){try{window.sessionStorage.removeItem('ghlut_attribution_v2');window.localStorage.removeItem('ghlut_attribution_v2');document.getElementById('ghlut-clear-result').textContent='Datos de prueba borrados en este navegador.';}catch(e){document.getElementById('ghlut-clear-result').textContent='El navegador bloqueo esta operacion.';}});})();</script>
          <hr><h2>Como verificar</h2>
          <ol><li>Abre una ventana de incognito e ingresa con UTM y un GCLID de prueba.</li><li>Navega a una pagina seleccionada, por ejemplo Contacto.</li><li>Confirma que la URL restaurada incluya los parametros y en DevTools &gt; Network revisa el payload <code>external-tracking/events</code>.</li><li>Realiza una prueba separada con un clic real antes de validar conversiones de Google Ads.</li></ol>
          <p><strong>Importante:</strong> desactiva cualquier plugin puente anterior que restaure los mismos parametros. Puedes mantener HandL solo si otras funcionalidades lo requieren, pero su opcion "Append UTM" podria competir con esta restauracion.</p>
          <p><strong>Privacidad:</strong> los identificadores y las UTM pueden estar sujetos a consentimiento y regulaciones de privacidad. Este plugin no elude mecanismos de consentimiento.</p>
        </div>
        <script>
        (function(){var q=document.getElementById('ghlut-page-search');if(!q)return;q.addEventListener('input',function(){var s=q.value.toLowerCase();document.querySelectorAll('[data-ghlut-title]').forEach(function(x){x.style.display=x.getAttribute('data-ghlut-title').indexOf(s)!==-1?'block':'none';});});})();
        </script>
        <?php
    }
    public function output_script() {
        if (is_admin() || is_feed() || is_robots() || wp_doing_ajax()) return;
        $o = $this->options();
        if (empty($o['enabled'])) return;
        // Page IDs saved by older versions may be strings; the strict lookup needs ints.
        $pages = array_map('intval', (array)$o['pages']);
        $restore = ($o['scope'] === 'all') || (is_page() && in_array((int)get_queried_object_id(), $pages, true));
        $extra = preg_split('/[\s,]+/', trim((string)$o['extra_keys']));
        $keys = array_values(array_unique(array_filter(array_merge(self::KNOWN_KEYS,$extra))));
        $config = array('keys'=>$keys,'restore'=>$restore,'ttl'=>(int)$o['ttl']*60000,
          'mode'=>$o['mode'], 'debug'=>(bool)$o['debug'] && is_user_logged_in() && current_user_can('manage_options'));
        echo "\n<script id=\"" . self::MARKER . "\" data-cfasync=\"false\" data-nowprocket=\"1\" nowprocket data-no-optimize=\"1\" data-no-defer=\"1\">\n";
        // Marker comment lets optimizers that match inline content (WP Rocket, SiteGround, Autoptimize) exclude this script.
        echo '/* ' . self::MARKER . ' */(function(c){' . "\n";
        // Script intentionally in the earliest wp_head hook, without async/defer; do not log sensitive URL data.
        ?>
        'use strict';
        var KEY='ghlut_attribution_v2';
        var now=Date.now();
        window.__ghlutRuntime={executed:true,restored:false,mode:c.mode};
        function debug(msg){if(c.debug&&window.console)console.info('[GHL UTM Tracker] '+msg);}
        function valid(t){return t&&typeof t==='object'&&typeof t.ts==='number'&&t.ts<=now&&now-t.ts<=c.ttl&&t.params&&typeof t.params==='object';}
        function matches(a,b){var ka=Object.keys(a||{}),kb=Object.keys(b||{});return ka.length===kb.length&&ka.every(function(k){return b[k]===a[k];});}
        // Keep only origin and path: the full query may carry personal data (e.g. ?email=).
        function landing(u){return u.origin+u.pathname;}
        function touch(u,p){return {ts:now,landing:landing(u),params:p};}
        function copy(t){return t?{ts:t.ts,landing:t.landing,params:JSON.parse(JSON.stringify(t.params))}:null;}
        var current=null;
        window.ghlutGetAttribution=function(){return current?{first:copy(current.first),last:copy(current.last)}:{first:null,last:null};};
        try {
          if(!window.URLSearchParams||!window.history.replaceState)return;
          var store=c.mode==='persistent'?window.localStorage:window.sessionStorage;
          if(!store)return;
          var url=new URL(window.location.href);
          if(url.protocol!=='https:'&&url.protocol!=='http:')return;
          var incoming={},count=0;
          c.keys.forEach(function(k){var v=url.searchParams.get(k);if(v!==null&&v!==''&&v.length<=2048){incoming[k]=v;count++;}});
          var entry=null;
          try{entry=JSON.parse(store.getItem(KEY)||'null');}catch(e){}
          if(!entry||entry.origin!==url.origin){entry=null;}
          if(entry){
            if(!valid(entry.first))entry.first=null;
            if(!valid(entry.last))entry.last=null;
            if(!entry.first&&!entry.last){entry=null;store.removeItem(KEY);}
          }
          if(entry){
            // Entries written by 1.2.0 stored the full landing URL; trim and save them once.
            var trimmed=false;
            ['first','last'].forEach(function(k){if(entry[k]&&typeof entry[k].landing==='string'&&entry[k].landing.search(/[?#]/)!==-1){try{entry[k].landing=landing(new URL(entry[k].landing));}catch(e){entry[k].landing='';}trimmed=true;}});
            if(trimmed&&!count)store.setItem(KEY,JSON.stringify(entry));
          }
          if(count){
            // Reloading a URL previously restored from storage must not reset the age of the click ID.
            // An equivalent set of parameters counts as the same attribution, not a fresh campaign.
            if(!entry){entry={origin:url.origin,first:touch(url,incoming),last:touch(url,incoming)};
              debug('Primera entrada de campana guardada.');}
            else if(!entry.last||!matches(incoming,entry.last.params)){
              entry.last=touch(url,incoming);
              if(!entry.first)entry.first=touch(url,incoming);
              debug('Ultima atribucion actualizada.');
            }else{if(!entry.first)entry.first=touch(url,incoming);debug('Entrada repetida; se conserva la fecha original de atribucion.');}
            store.setItem(KEY,JSON.stringify(entry));
          }
          current=entry;
          if(!count&&c.restore&&entry&&entry.last){
            // Do not blend incomplete new campaign URLs with stored click IDs.
            Object.keys(entry.last.params).forEach(function(k){if(!url.searchParams.has(k))url.searchParams.set(k,entry.last.params[k]);});
            window.history.replaceState(window.history.state,'',url.href);
            window.__ghlutRuntime.restored=true;
            debug('Ultima atribucion restaurada en la pagina.');
          }
        }catch(e){debug('No se pudo almacenar o restaurar atribucion.');}
        })(<?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        <?php
        echo "\n</script>\n";
    }
}
GHL_UTM_Tracker::instance();
