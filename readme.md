## What is this?

Discourse provides an official plugin that will enable you to use a WP instance as a SSO provider. That is, you can auth to your Discourse instance using your WP creditentials.

However, sometimes, you'll want to use it the other way around: Discourse users on a WP instance. This plugins allows you exactly that.

## Usage:

1. Install [wp-discourse](https://github.com/discourse/wp-discourse);
2. Go to Settings -> Discourse and fill `Discourse URL` and `SSO Secret Key`;
3. Use the `[discourse_sso]` shortcode anywhere you'd want the auth link to be shown. The default anchor is `Log in with Discourse`
4. Obviously, you can specify an alternative anchor like this: `[discourse_sso "Auth with my awesome discourse!"]``

Done!
