<?php
/**
 * Redirect interception signal.
 *
 * @package PluginizeLab\WpLoginLogoutRedirect
 */

namespace PluginizeLab\WpLoginLogoutRedirect\Tests\Integration;

use Exception;

/**
 * Thrown from a `wp_redirect` filter to unwind out of code that ends in exit().
 *
 * The message carries the intercepted URL.
 */
class RedirectCaught extends Exception {
}
