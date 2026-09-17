<?php

if (!defined('WPINC')) exit;

require_once __DIR__ . '/source.php';
require_once __DIR__ . '/gateway.php';
require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/form.php';
require_once __DIR__ . '/matrix/cuanto/cuanto.php';
require_once __DIR__ . '/matrix/paypal/paypal_me.php';
require_once __DIR__ . '/matrix/yappy/yappy_direct.php';
require_once __DIR__ . '/matrix/yappy/yappy_v2.php';
require_once __DIR__ . '/matrix/crypto/stable-coins.php';
require_once __DIR__ . '/matrix/paguelo_facil/paguelo_facil_on.php';

new paguelo_facil_on();
new cuanto();
new paypal_me();
new yappy_direct();
new yappy_v2();
new stable_coins('dy-core', 'usdt');
new stable_coins('dy-core', 'usdc');

add_action('template_redirect', [Dy_Checkout::class, 'submit'], 20);
add_filter('dy_confirmation_result', [Dy_Checkout::class, 'confirmation'], 10, 2);
