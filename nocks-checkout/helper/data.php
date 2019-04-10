<?php

class Nocks_Helper_Data {
	/**
	 * Transient prefix. We can not use plugin slug because this
	 * will generate to long keys for the wp_options table.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'nocks-edd-';

	/**
	 * @param string $transient
	 * @return string
	 */
	public static function getTransientId($transient) {
		global $wp_version;

		/*
		 * WordPress will save two options to wp_options table:
		 * 1. _transient_<transient_id>
		 * 2. _transient_timeout_<transient_id>
		 */
		$transient_id = self::TRANSIENT_PREFIX . $transient;
		$option_name = '_transient_timeout_' . $transient_id;
		$option_name_length = strlen($option_name);

		$max_option_name_length = 191;

		/**
		 * Prior to WooPress version 4.4.0, the maximum length for wp_options.option_name is 64 characters.
		 * @see https://core.trac.wordpress.org/changeset/34030
		 */
		if ($wp_version < '4.4.0') {
			$max_option_name_length = 64;
		}

		if ($option_name_length > $max_option_name_length) {
			trigger_error("Transient id $transient_id is to long. Option name $option_name ($option_name_length) will be to long for database column wp_options.option_name which is varchar($max_option_name_length).", E_USER_WARNING);
		}

		return $transient_id;
	}

	/**
	 * Get current locale
	 *
	 * @return string
	 */
	public static function getCurrentLocale() {
		return apply_filters('wpml_current_language', get_locale());
	}

}