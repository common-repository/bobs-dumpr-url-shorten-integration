=== Plugin Name ===
Contributors: bobmajdakjr
Donate link: http://dumpr.info
Tags: urls
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 1.0.1

Generate and manage short URLs for blog posts using dumpr.info.

== Description ==

**Note: this plugin requires an API key for dumpr.info. To get an API key all you
need to do is create an account, then you can generate them from the Profile page.**

This plugin provides an interface with the [dumpr.info](http://dumpr.info/url) api
to generate short URLs for blog posts. It has a control panel built into the
WordPress administration where you can fetch and manage them. URLs can be set to
be automatically generated every time you add a post or not.

When a short URL is generated, it is attached to the post under the custom field
dumpr-shorturl. You can render that out in your theme or where ever.

This plugin also provides nice functions that other plugins could use to integrate
whatever services they need into the short URL system as well (like a plugin that
posts to Twitter or Facebook, for example)

= About dumpr.info =

dumpr.info is a text dump and url shortening site. The URL shortening is unique
because each URL has a - or a ~ in it. This character tells you what is going to
happen. If the URL has a straight bar, (-) then the short URL will go straight to
the original. If the URL has a curvy bar (~) then it will not go straight to the
original, instead taking a detour to a preview page that shows the original link
and a screenshot.

You can view all the URLs you have added by logging into the site with the account
that owns the API key used.

== Installation ==

1. Upload `bob-dumpr` to the `wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. ????
4. Profit. (metaphorically, most likely)

== Frequently Asked Questions ==

== Screenshots ==

1. The Dumpr admin panel.

== Changelog ==

= 1.0.0 =
* Initial build.

= 1.0.1 =
* Updated to reflect you can generate your own API keys now without having to contact
a site administrator.
* Fixed how to properly commit it to WordPress.Org SVN so that it installs and works
properly. Sorry about that I thought it was less dumb than it was.
