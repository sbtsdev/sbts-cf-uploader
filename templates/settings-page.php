<div class="wrap">
    <h2>Cloud Files Uploader</h2>
    <form method="post" action="options.php">
        <?php @settings_fields( 'sbts_cf_plugin_settings-group' ); ?>
        <?php @do_settings_fields( 'sbts_cf_plugin_settings-group' ); ?>

        <?php do_settings_sections( 'sbts_cf_plugin_settings' ); ?>

        <?php @submit_button(); ?>
    </form>
</div>
