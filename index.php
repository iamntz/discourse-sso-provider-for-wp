<?php
/*
Plugin Name: Discourse SSO provider for WP
Description: Use Discourse as a SSO provider
Author: Ionuț Staicu
Version: 1.0.0
Author URI: http://ionutstaicu.com
 */

if (!defined('ABSPATH')) {
  exit;
}

define('NTZ_WP_SSO_VERSION', '1.0.0');

define('NTZ_WP_SSO_BASEFILE', __FILE__);
define('NTZ_WP_SSO_URL', plugin_dir_url(__FILE__));
define('NTZ_WP_SSO_PATH', plugin_dir_path(__FILE__));

$ntz_discourseOptions = get_option('discourse');

if (!empty($ntz_discourseOptions['sso-secret'])) {
  define('DISCOURSE_SSO_SECRET', $ntz_discourseOptions['sso-secret']);
}

if (!empty($ntz_discourseOptions['url'])) {
  define('DISCOURSE_SSO_URL', $ntz_discourseOptions['url']);
}

define('DISCOURSE_SSO_META_KEY', 'discourse_sso_user_id');

add_action('parse_request', function () {
  if (empty($_GET['sso']) || empty($_GET['sig'])) {
    return;
  }

  $sso = $_GET['sso'];
  $sig = $_GET['sig'];
  $sso = urldecode($sso);

  $query = [];

  parse_str(base64_decode($sso), $query);
  $query = array_map('sanitize_text_field', $query);

  if (empty($query['external_id'])) {
    return;
  }

  if (hash_hmac('sha256', urldecode($sso), DISCOURSE_SSO_SECRET) !== $sig) {
    header("HTTP/1.1 404 Not Found");
    die();
  }

  $userQuery = new WP_User_Query([
    'meta_key' => DISCOURSE_SSO_META_KEY,
    'meta_value' => $query['external_id'],
  ]);

  $userQueryResults = $userQuery->get_results();
  $updatedUser = [];

  if (empty($userQueryResults)) {
    // create user if not present
    if (is_user_logged_in()) {
      $userID = get_current_user_id();
    } else {
      $userID = wp_create_user($query['username'], $query['nonce'], $query['email']);
      $updatedUser['user_pass'] = $query['nonce'];
    }

    if (is_wp_error($userID)) {
      return;
    }

    update_user_meta($userID, DISCOURSE_SSO_META_KEY, $query['external_id']);
  } else {
    $userID = $userQueryResults{0}->ID;
  }

  add_filter('send_password_change_email', '__return_false');
  add_filter('send_email_change_email', '__return_false');

  $updatedUser = array_merge([
    'ID' => $userID,
    'user_login' => $query['username'],
    'user_email' => $query['email'],
    'user_nicename' => $query['name'],
    'display_name' => $query['name'],
    'first_name' => $query['name'],
  ], $updatedUser);


  wp_update_user($updatedUser);

  wp_set_current_user($userID, $query['username']);
  wp_set_auth_cookie($userID);
  do_action('wp_login', $query['username']);

  if (!empty($query['return_sso_url'])) {
    wp_redirect($query['return_sso_url']);
  } else {
    wp_redirect(home_url('/'));
  }

  die();
}, 5);


add_shortcode( 'discourse_sso', function($atts)
{
  if (is_user_logged_in()) {
    $user = wp_get_current_user();
    if ( get_user_meta($user->ID, DISCOURSE_SSO_META_KEY, true) ) {
      return;
    }
  }

  $anchor = !empty($atts[0]) ? $atts[0] : __('Log in with Discourse');

  $nonce = hash('sha512', mt_rand());

  $redirectTo = get_permalink();

  if (empty($redirectTo)) {
    $redirectTo = home_url('/');
  }

  $payload = base64_encode(http_build_query([
    'nonce' => $nonce,
    'return_sso_url' => add_query_arg('existing_user', is_user_logged_in(), $redirectTo),
  ]
  ));

  $request = [
    'sso' => $payload,
    'sig' => hash_hmac('sha256', $payload, DISCOURSE_SSO_SECRET),
  ];

  $sso_login_url = DISCOURSE_SSO_URL . '/session/sso_provider?' . http_build_query($request);

  return sprintf('<a href="%s">%s</a>', $sso_login_url, $anchor);
} );