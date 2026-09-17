<?php if (!defined('WPINC')) exit; ?>
<h3><?php esc_html_e('Choose Network', 'dycore'); ?></h3>
<p><select name="dy_network"><option value="">--</option></select></p>
<p id="dy_crypto_alert" class="minimal_alert hidden">
    <?php esc_html_e('The network you selected is', 'dycore'); ?> <strong id="dy_crypto_network_code"></strong>.
    <?php esc_html_e('make sure you use', 'dycore'); ?> <strong id="dy_crypto_network_name"></strong>
    <?php esc_html_e('at the time of sending the funds. If the other network does not support it, your assets may be lost.', 'dycore'); ?>
</p>
