<?php

if ( !defined( 'WPINC' ) ) exit;

class Dy_Core_Fields
{
    private static array $instances = [];
    private static array $cache = [];
    private static array $migrations_in_progress = [];

    private string $post_type;
    private array $migration_arr;
    private bool $is_valid_migration_arr = false;

    public function __construct(string $post_type, array $migration_arr)
    {
        $this->post_type = $post_type;
        $this->migration_arr = $migration_arr;
        self::$instances[$post_type] = $this;
    }

    private static function instance_for(int $the_id): self
    {
        $post_type = get_post_type($the_id);
        $post_type = is_string($post_type) ? $post_type : '';

        if (!isset(self::$instances[$post_type])) {
            new self($post_type, []);
        }

        return self::$instances[$post_type];
    }

    private function get_migration_arr(): array
    {
        if($this->is_valid_migration_arr) {
            return $this->migration_arr;
        }

        $is_sequential_array = static function (array $arr): bool {
            return array_keys($arr) === range(0, count($arr) - 1);
        };

        foreach ($this->migration_arr as $row) {
            if (
                !is_array($row)
                || !array_key_exists('old', $row)
                || !array_key_exists('new', $row)
                || !is_array($row['old'])
                || !is_array($row['new'])
                || empty($row['old'])
                || empty($row['new'])
                || !$is_sequential_array($row['old'])
                || !$is_sequential_array($row['new'])
            ) {
                $this->migration_schema_error($row);
            }

            foreach ([...$row['old'], ...$row['new']] as $part) {
                if (!is_string($part) || $part === '') {
                    $this->migration_schema_error($row);
                }
            }
        }

        $this->is_valid_migration_arr = true;
        return $this->migration_arr;
    }

    private function migration_schema_error(mixed $row): void
    {
        write_log(
            [
                'message'  => 'Invalid migration array schema.',
                'post_type'=> $this->post_type,
                'row_type' => get_debug_type($row),
                'row'      => $row
            ],
            true,
            false,
            'ERROR'
        );

        wp_die(
            'Internal Server Error',
            'Internal Server Error',
            ['response' => 500]
        );
    }

    private function migrate_field(string $name, int $the_id): array
    {
        $migration_arr = $this->get_migration_arr();

        foreach ($migration_arr as $row) {
            $old = implode('_', $row['old']);
            $new = implode('_', $row['new']);

            if (!in_array($name, [$new, $old], true)) {
                continue;
            }

            $migration_key = $this->post_type . ':' . $the_id . ':' . $new;

            if (isset(self::$migrations_in_progress[$migration_key])) {
                $value = get_post_meta($the_id, $new, true);

                if ($value === '') {
                    $value = get_post_meta($the_id, $old, true);
                }

                return [$value, $value !== ''];
            }

            $this_field = get_post_meta($the_id, $new, true);

            if ($this_field !== '') {
                return [$this_field, true];
            }

            $this_field = get_post_meta($the_id, $old, true);

            if ($this_field === '') {
                return ['', false];
            }

            self::$migrations_in_progress[$migration_key] = true;

            try {
                $is_updated = update_post_meta($the_id, $new, $this_field);

                if ($is_updated !== false) {
                    delete_post_meta($the_id, $old);
                }
            } finally {
                unset(self::$migrations_in_progress[$migration_key]);
            }

            return [$this_field, true];
        }

        return ['', false];
    }

    private function get_value(string $name, int $the_id): string
    {
        $cache_key = $this->post_type . '_'. $name . '_' . $the_id;

        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

        [$this_field, $is_migrated] = $this->migrate_field($name, $the_id);

        if(!$is_migrated) {
            $this_field = get_post_meta($the_id, $name, true);
        }

        if(!is_string($this_field)) {
            write_log(
                [
                    'message'    => 'Field value is not a string.',
                    'field'      => $name,
                    'post_id'    => $the_id,
                    'post_type'  => $this->post_type,
                    'value_type' => get_debug_type($this_field)
                ],
                true,
                false,
                'WARNING'
            );

            $this_field = '';
        }

        return self::$cache[$cache_key] = $this_field;
    }

    public static function get(string $name, null|int $the_id = null): string
    {
        if ($the_id === null) {
            $the_id = get_dy_id();

            if($the_id === null) {
                $request_uri = secure_server('REQUEST_URI');
                throw new Exception("'the_id' can not be null: $name, URL: $request_uri");
            }
        }

        return self::instance_for($the_id)->get_value($name, $the_id);
    }
}

?>