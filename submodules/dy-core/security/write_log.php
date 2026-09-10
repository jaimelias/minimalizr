<?php 

if ( ! function_exists('write_log')) {

	if(!function_exists('sanitize_sensitive_data'))
	{
		function sanitize_sensitive_data(
			mixed $data,
			array $sensitive_params = [],
			string $replacement = '[REDACTED]'
		): mixed
		{
			$sensitive_params = array_merge([
				'CCNum',
				'ExpMonth',
				'ExpYear',
				'CVV2',
				'password',
				'passwd',
				'token',
				'access_token',
				'authorization',
				'api_key',
				'apikey',
				'secret',
				'hash',
				'SecretHash',
				'cf-turnstile-response',

				// Additional common sensitive keys.
				'client_secret',
				'refresh_token',
				'id_token',
				'private_key',
				'cookie',
				'set-cookie',
				'nonce',
				'_wpnonce'
			], $sensitive_params);

			$sensitive_params = array_fill_keys(
				array_map(
					static fn(mixed $param): string => strtolower((string) $param),
					$sensitive_params
				),
				true
			);

			/*
			 * Also redact recognizable key=value or key:value pairs found
			 * inside free-form strings.
			 */
			$sensitive_pattern = implode(
				'|',
				array_map(
					static fn(string $param): string => preg_quote($param, '/'),
					array_keys($sensitive_params)
				)
			);

			$redact_string = static function(string $value) use (
				$sensitive_pattern,
				$replacement
			): string
			{
				$pattern = '/(["\']?\b(?:'
					. $sensitive_pattern
					. ')\b["\']?\s*[:=]\s*)'
					. '("[^"]*"|\'[^\']*\'|(?:Bearer[ \t]+)?[^\s,;&]+)/i';

				$redacted = preg_replace_callback(
					$pattern,
					static function(array $matches) use ($replacement): string
					{
						$value = $matches[2];
						$first = $value[0] ?? '';
						$quote = ($first === '"' || $first === "'")
							? $first
							: '';

						return $matches[1]
							. $quote
							. $replacement
							. $quote;
					},
					$value
				);

				return is_string($redacted)
					? $redacted
					: $replacement;
			};

			$seen_objects = new SplObjectStorage();

			$sanitize = function(
				mixed $value,
				int $depth = 0
			) use (
				&$sanitize,
				$sensitive_params,
				$replacement,
				$redact_string,
				$seen_objects
			): mixed
			{
				if($depth >= 20)
				{
					return '[MAX DEPTH]';
				}

				if(is_string($value))
				{
					return $redact_string($value);
				}

				if(is_array($value))
				{
					foreach($value as $key => $item)
					{
						$value[$key] = isset(
							$sensitive_params[strtolower((string) $key)]
						)
							? $replacement
							: $sanitize($item, $depth + 1);
					}

					return $value;
				}

				if(is_object($value))
				{
					if($seen_objects->contains($value))
					{
						return '[RECURSION]';
					}

					$seen_objects->attach($value);

					try
					{
						if($value instanceof JsonSerializable)
						{
							return $sanitize(
								$value->jsonSerialize(),
								$depth + 1
							);
						}

						$properties = [];

						foreach(get_object_vars($value) as $key => $item)
						{
							$properties[$key] = isset(
								$sensitive_params[strtolower((string) $key)]
							)
								? $replacement
								: $sanitize($item, $depth + 1);
						}

						/*
						 * A new object prevents mutation of the original and
						 * avoids invoking unexpected serialization behavior.
						 */
						return (object) $properties;
					}
					catch(Throwable)
					{
						return '[UNSERIALIZABLE OBJECT]';
					}
					finally
					{
						$seen_objects->detach($value);
					}
				}

				return $value;
			};

			return $sanitize($data);
		}
	}

	function write_log(
		mixed $log = '',
		bool $debug = false,
		bool $log_request = false,
		string $level = 'write_log'
	): void
	{
		/*
		 * Remove every character that could physically divide an event into
		 * multiple log lines. Escaped JSON sequences such as "\n" remain valid.
		 */
		$single_line = static function(string $value): string
		{
			$value = str_replace(
				[
					"\u{0085}",
					"\u{2028}",
					"\u{2029}"
				],
				' ',
				$value
			);

			$normalized = preg_replace(
				'/[\x00-\x1F\x7F]+/',
				' ',
				$value
			);

			return trim(
				is_string($normalized)
					? $normalized
					: ''
			);
		};

		$encode = static function(mixed $value) use ($single_line): string
		{
			$encoded = wp_json_encode(
				sanitize_sensitive_data($value),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			return is_string($encoded)
				? $single_line($encoded)
				: '"[UNENCODABLE]"';
		};

		if(is_array($log) || is_object($log))
		{
			$message = $encode($log);
		}
		elseif(is_string($log))
		{
			$message = $single_line(
				(string) sanitize_sensitive_data($log)
			);
		}
		elseif(is_bool($log))
		{
			$message = $log ? 'true' : 'false';
		}
		elseif($log === null)
		{
			$message = 'null';
		}
		elseif(is_int($log) || is_float($log))
		{
			$message = (string) $log;
		}
		else
		{
			$message = sprintf(
				'[unsupported type: %s]',
				get_debug_type($log)
			);
		}

		$raw_ip = function_exists('get_ip_address')
			? get_ip_address()
			: '(unknown)';

		$ip = is_scalar($raw_ip)
			? $single_line((string) $raw_ip)
			: '(unknown)';

		/*
		 * Preserve the supplied case, including the default "write_log".
		 * Replacing punctuation and whitespace keeps the prefix parseable.
		 */
		$level = $single_line($level);
		$normalized_level = preg_replace(
			'/[^A-Za-z0-9_.-]+/',
			'_',
			$level
		);
		$level = is_string($normalized_level)
			? $normalized_level
			: 'write_log';

		$prefix = sprintf('[%s]', $ip);

		if($level !== '')
		{
			$prefix .= ' ' . $level . ':';
		}

		$parts = [$prefix];

		if($message !== '')
		{
			$parts[] = $message;
		}

		if($log_request)
		{
			$get_data = isset($_GET) && is_array($_GET)
				? $_GET
				: [];

			$post_data = isset($_POST) && is_array($_POST)
				? $_POST
				: [];

			if($get_data !== [])
			{
				$parts[] = 'get=' . $encode($get_data);
			}

			if($post_data !== [])
			{
				$parts[] = 'post=' . $encode($post_data);
			}
		}

		if($debug)
		{
			$trace = array_slice(
				debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
				1,
				7
			);

			$lines = [];

			foreach($trace as $index => $frame)
			{
				$function = ($frame['class'] ?? '')
					. ($frame['type'] ?? '')
					. ($frame['function'] ?? '');

				$file = $frame['file'] ?? '(no-file)';
				$line = (int) ($frame['line'] ?? 0);

				$lines[] = sprintf(
					'#%d %s() @ %s:%d',
					$index,
					$function,
					$file,
					$line
				);
			}

			$parts[] = 'debug=' . $encode($lines);
		}

		$request_path = wp_parse_url(
			secure_server('REQUEST_URI'),
			PHP_URL_PATH
		);

		$server_data = [
			'method'     => secure_server('REQUEST_METHOD'),
			'path'       => is_string($request_path) ? $request_path : ''
		];

		$country_code = get_request_country_code();

		if($country_code !== '') {
			$server_data['country'] = $country_code;
			$server_data['flag'] = country_code_to_flag($country_code);
		}

		$parts[] = 'server=' . $encode($server_data);

		/*
		 * Defense in depth: normalize the complete assembled event, including
		 * its prefix, before making exactly one error_log() call.
		 */
		$output = $single_line(implode(' ', $parts));

		error_log($output);
	}
}
