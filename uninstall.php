<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

delete_option('ghlut_settings');
if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
        switch_to_blog($site_id);
        delete_option('ghlut_settings');
        restore_current_blog();
    }
}
