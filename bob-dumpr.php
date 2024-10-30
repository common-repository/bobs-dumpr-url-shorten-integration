<?php

/*
Plugin Name: Bob's Dumpr URL Shorten Integration
Plugin URI: http://dumpr.info
Description: Manage shortened URLs for your posts and provide an API for other plugins.
Version: 1.0.1
Author: Bob Majdak Jr <bob@opsat.net>
Author URI: http://www.opsat.net
*/

// release history
// 1.0.0 - 20110915 - initial build.
// 1.0.1 - 20110919 - updated to reflect you can get api keys now.

//////////////////////////////////////////
///*! api utility functions */////////////

function dumpr_is_apikey_valid($apikey=null) {
//$ string apikey
//> boolean is_valid
/// go ask dumpr.info if the specified api key is still valid. returns
/// a boolean of the result.

	if(!$apikey) {
		$opt = (object)get_option('dumpr');
		if(!$opt->apikey) return false;
		else $apikey = $opt->apikey;
	}

	$result = json_decode(file_get_contents(
		"http://dumpr.info/api/1.0/auth-key".
			"?apikey={$apikey}".
			"&agent=wordpress"
	));
	
	if($result->errno) return false;
	else return true;
}

function dumpr_get_shorturl($url,$pid=0) {
//$ string url[, int post_id]
//> string shorturl
/// go ask dumpr.info for a short link to the specified url. if a post
/// id is also supplied, then we will also assign it to a custom post
/// field in our wordpress database here.

	// check if this post already has a url assigned to it first to
	// save having to run across the internet to get it.
	if($pid) {
		$shorturl = dumpr_read_shorturl($pid);
		if($shorturl) return $shorturl;
	}
	
	$opt = (object)get_option('dumpr');
	if(!$opt->apikey) return false;
	
	$result = json_decode(file_get_contents(sprintf(
		"http://dumpr.info/api/1.0/url-shorten".
			"?apikey=%s".
			"&url=%s".
			"&agent=wordpress",
		$opt->apikey,
		urlencode($url)
	)));
	
	if(!is_object($result) or $result->errno) return false;
	
	// assign this to a specific post if one was given to that it
	// can be reused later without having to make remote api calls.
	if($pid) dumpr_assign_shorturl($pid,$result->link);
	
	return $result->link;
}

//////////////////////////////////////////
///*! post custom field functions. *//////

function dumpr_assign_shorturl($pid,$shorturl) {
//$ int post_id, string shorturl
/// assign the specified short url to a post in our database.

	delete_post_meta($pid,'dumpr-shorturl');
	add_post_meta($pid,'dumpr-shorturl',$shorturl);
	return;
}

function dumpr_delete_shorturl($pid) {
//$ int post_id
/// remove the dumpr url from a post in our database.

	delete_post_meta($pid,'dumpr-shorturl');
	return;	
}

function dumpr_read_shorturl($pid) {
//$ int post_id
/// read the short url assigned to the specified post.

	$custom = get_post_custom($pid);
	
	if(array_key_exists('dumpr-shorturl',$custom))
	return current($custom['dumpr-shorturl']);

	else return false;
}

//////////////////////////////////////////
///*! wordpress admin functions. *////////

function dumpr_get_post_list($page,$limit) {
	$offset = $limit*($page-1);
	
	$arg = array(
		'numberposts' => $limit,
		'offset'      => $offset,
		'post_status' => 'publish'
	);

	$output = array();	
	foreach(get_posts($arg) as $post) {
		$shorturl = dumpr_read_shorturl($post->ID);
		$output[] = (object)array(
			'id' => $post->ID,
			'title' => $post->post_title,
			'url' => get_permalink($post->ID),
			'shorturl' => (($shorturl)?($shorturl):(null))
		);
	}
	
	return $output;
}

register_activation_hook(__FILE__,'dumpr_on_plugin_activate');
function dumpr_on_plugin_activate() {
	$opt = (object)get_option('dumpr');
	
	register_setting('dumpr_options','dumpr');	
	update_option('dumpr',array(
		'apikey'    =>
			(($opt and property_exists($opt,'apikey'))?($opt->apikey):('')),
		'onpublish' =>
			(($opt and property_exists($opt,'onpublish'))?($opt->onpublish):(false))
	));
	
	return;
}

add_action('admin_init','dumpr_on_post_action');
function dumpr_on_post_action() {
	if(!array_key_exists('dumpr',$_POST)) return;
	
	switch($_POST['dumpr']) {
	
		case 'delete-url': {
			$pid = $_POST['dumpr_post_id'];
			dumpr_delete_shorturl($pid);
			wp_redirect($_SERVER['REQUEST_URI']);
			break;
		}
	
		case 'fetch-url': {
			$pid = $_POST['dumpr_post_id'];
			
			$url = get_permalink($pid);
			if(!$url) wp_redirect($_SERVER['REQUEST_URI']);
			
			$shorturl = dumpr_get_shorturl($url,$pid);
			wp_redirect($_SERVER['REQUEST_URI']);
			break;
		}
	
		case 'save-options': {
			$opt = get_option('dumpr');
			$opt['apikey'] = $_POST['dumpr_apikey'];
			$opt['onpublish'] = (bool)(int)$_POST['dumpr_onpublish'];
			update_option('dumpr',$opt);
			
			wp_redirect($_SERVER['REQUEST_URI']);
			break;
		}
	}
	
	return;
}

add_action('admin_menu','dumpr_on_admin_menu');
function dumpr_on_admin_menu() {
	add_menu_page(
		'Dumpr',
		'Dumpr',
		'manage_options',
		'bob-dumpr',
		'dumpr_on_admin_page'
	);
	return;
}

function dumpr_on_admin_page() {
	$opt = (object)get_option('dumpr');
	
	/////////////////////////////////////////
	// api key check	
	
	if(!$opt->apikey or !dumpr_is_apikey_valid()) {
		$apikeyvalid = false;
		$apikeymsg = '<span style="color:#800;">invalid</span>';
	} else {
		$apikeyvalid = true;
		$apikeymsg = '<span style="color:#0a0;">valid</span>';
	}
	
	/////////////////////////////////////////
	// options
	
	if($opt->onpublish)	$optpublishyes = ' selected="selected"';
	else $optpublishyes = '';

	/////////////////////////////////////////
	// paginate

	$page = ((array_key_exists('bob-dumpr-page',$_GET))?((int)$_GET['bob-dumpr-page']):(1));
	if(!$page or $page < 1) $page = 1;
	$perpage = 16;
	$maxpage = ceil(wp_count_posts()->publish / $perpage);
	$pageblock = '';
	if($page > 1)        $pageblock .= "<a class=\"button-secondary\" href=\"?page=bob-dumpr&bob-dumpr-page=".($page-1)."\">&lt;</a> &nbsp;";
	                     $pageblock .= "Page {$page} of {$maxpage}";
	if($page < $maxpage) $pageblock .= "&nbsp; <a class=\"button-secondary\" href=\"?page=bob-dumpr&bob-dumpr-page=".($page+1)."\">&gt;</a>";

	/////////////////////////////////////////
	// post table	
	
	$tableposts = '';
	foreach(dumpr_get_post_list($page,$perpage) as $post) {
		$tableposts .= "<tr>";
		$tableposts .= "<td>{$post->title}<div style=\"font-size:0.8em;\"><a href=\"{$post->url}\" target=\"_blank\">{$post->url}</a></div></td>";
		if($post->shorturl) {
			$tableposts .= "<td>";
			$tableposts .= "<a href=\"{$post->shorturl}\" target=\"_blank\"><tt>{$post->shorturl}</tt></a><br />";
			$tableposts .= "<a href=\"".(str_replace('-','~',$post->shorturl))."\" target=\"_blank\"><tt>".(str_replace('-','~',$post->shorturl))."</tt></a><br />";
			$tableposts .= "</td>";
			$tableposts .= "<td>";
			$tableposts .= "<div style=\"font-size:0.8em;\">".(strlen($post->url)-strlen($post->shorturl))." char. shorter</div>";			
			$tableposts .= "<form method=\"post\">";
			$tableposts .= "<input type=\"hidden\" name=\"dumpr\" value=\"delete-url\" />";
			$tableposts .= "<input type=\"hidden\" name=\"dumpr_post_id\" value=\"{$post->id}\" />";
			$tableposts .= "<input type=\"submit\" value=\"Delete URL\" class=\"button-secondary\" />";			
			$tableposts .= "</form>";
			$tableposts .= "</td>";
		} else {
			$tableposts .= "<td></td>";
			$tableposts .= "<td><form method=\"post\">";
			$tableposts .= "<input type=\"hidden\" name=\"dumpr\" value=\"fetch-url\" />";
			$tableposts .= "<input type=\"hidden\" name=\"dumpr_post_id\" value=\"{$post->id}\" />";
			$tableposts .= "<input type=\"submit\" value=\"Fetch URL\" class=\"button-primary\" />";
			$tableposts .= "</form></td>";
		}
		$tableposts .= "</tr>";
	}

echo <<< LOLDOC
<style type="text/css">
.bob-dumpr-options td { padding:4px; }
</style>

<div class="wrap">
<div id="icon-link-manager" class="icon32"></div>
<h2>Dumpr URL Shorten</h2>

<div>&nbsp;</div>

<form method="post">
<input type="hidden" name="dumpr" value="save-options" />
<table class="bob-dumpr-options widefat">
	<thead>
	<tr>
		<th>API Key ({$apikeymsg})</th>
		<th style="width:1px;"></th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td colspan="2">
			<input type="text" name="dumpr_apikey" value="{$opt->apikey}" style="width:100%;font-size:1.2em;letter-spacing:2px;" spellcheck="false" />
			<p>To get an API Key you need to create an account on <a href="http://dumpr.info/url" target="_blank">dumpr.info</a>. You can then generate
			API keys from your profile to use in this plugin. All URLs you generate with your API key can be viewed here in this plugin as well as by
			logging into the site.</p>
		</td>
	</tr>
	</tbody>
	
	<thead>
	<tr>
		<th>Other Options</th>
		<th></th>
	</tr>
	</thead>
	<tbody>
	<tr>
		<td><b>Generate Short URL on Publish?</b><br />If enabled a short URL will be generated every time a post is published and attached to the post in the custom field <tt>dumpr-shorturl</tt>. Else you will have to generate them manually with the &quot;Fetch URL&quot; buttons below.</td>
		<td valign="top">
			<select name="dumpr_onpublish">
				<option value="0">No</option>
				<option value="1"{$optpublishyes}>Yes</option>
			</select>
		</td>
	</tr>
	</tbody>
</table>
<div style="text-align:right;margin:4px 0px;">
<input type="submit" class="button-primary" value="Save Options" />
</div>
</form>

<div>&nbsp;</div>

<div style="overflow:auto;padding:4px 0px;margin:4px 0px;text-align:right;">
{$pageblock}
</div>

<table class="widefat">
	<thead>
	<tr>
		<th>Blog Post</th>
		<th style="width:150px;">Short URLs</th>
		<th style="width:1px;"></th>
	</tr>
	</thead>
	<tbody>
{$tableposts}
	</tbody>
</table>
</form>

<div>&nbsp;</div>

<h2>About Short URLs</h2>
<p>Dumpr provides you with two different short URLs for each link you shorten. The only
difference between the two is one will have a straight bar (-, hypen) and the other will
have a curvy bar (~, tilde). Think of it this way, the link with the straight bar will
take you straight to the original URL. The one that is not straight will not take you
straight there; instead, it will detour to a page showing the original URL and a screenshot
of it.</p>

</div>
LOLDOC;

	return;
}

//////////////////////////////////////////
///*! publishing functions *//////////////

add_action('publish_post','dumpr_on_publish_now');
add_action('publish_future_post','dumpr_on_publish_later');

function dumpr_on_publish_now($pid) {
	return dumpr_on_publish($pid,'now');
}

function dumpr_on_publish_later($pid) {
	return dumpr_on_publish($pid,'later');
}

function dumpr_on_publish($pid,$when) {
	$opt = (object)get_option('dumpr');
	if(!$opt->onpublish) return;

	$post = get_post($pid);
	if(!$post) return;
	
	//. if we are publishing a post now, check that this is the first
	//. time it has been published (updates are also publish_post)
	if($when == 'now' and $post->post_modified != $post->post_date)
	return;
	
	//. if we are publishing a post in the future, when the time comes
	//. just accept it as is.
	if($when == 'later') { }
	
	$url = get_permalink($pid);
	dumpr_get_shorturl($url,$pid);
	return;
}

?>