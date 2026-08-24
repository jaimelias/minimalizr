<?php

function get_value_option($args) {
    static $cache = [];
    $key = $args['key'];

    $cache_key = 'dy_get_value_option' . $key;

    if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
    }

    return $cache[$cache_key] = get_option($key, '');
}

class dy_input_option extends dy_input_abstract {

      protected static function get_value($args) {

            return get_value_option($args);
      }
}

class dy_select_option extends dy_select_abstract {

      protected static function get_value($args) {

            return get_value_option($args);
      }
}

class dy_textarea_option extends dy_textarea_abstract {

      protected static function get_value($args) {

            return get_value_option($args);
      }
}