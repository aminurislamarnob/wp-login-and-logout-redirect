<form action="options.php" method="POST">
    <?php do_settings_sections('wplalr_login_logout_section');?>
    <?php settings_fields('wplalr_login_logout_settings_section');?>
    <?php submit_button();?>
</form>