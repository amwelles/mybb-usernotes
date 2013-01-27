<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("showthread_start", "usernotes_input");

function usernotes_info()
{
	/**
	 * Array of information about the plugin.
	 * name: The name of the plugin
	 * description: Description of what the plugin does
	 * website: The website the plugin is maintained at (Optional)
	 * author: The name of the author of the plugin
	 * authorsite: The URL to the website of the author (Optional)
	 * version: The version number of the plugin
	 * guid: Unique ID issued by the MyBB Mods site for version checking
	 * compatibility: A CSV list of MyBB versions supported. Ex, "121,123", "12*". Wildcards supported.
	 */
	return array(
		"name"			=> "Skills/Attributes",
		"description"	=> "Allows users to specify skills and attributes for a given thread.",
		"website"		=> "https://github.com/amwelles/mybb-usernotes",
		"author"		=> "Autumn Welles",
		"authorsite"	=> "http://novembird.com/mybb/",
		"version"		=> "0.1",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

/**
 * ADDITIONAL PLUGIN INSTALL/UNINSTALL ROUTINES
 *
 * _install():
 *   Called whenever a plugin is installed by clicking the "Install" button in the plugin manager.
 *   If no install routine exists, the install button is not shown and it assumed any work will be
 *   performed in the _activate() routine.
 *
 * function hello_install()
 * {
 * }
 *
 * _is_installed():
 *   Called on the plugin management page to establish if a plugin is already installed or not.
 *   This should return TRUE if the plugin is installed (by checking tables, fields etc) or FALSE
 *   if the plugin is not installed.
 *
 * function hello_is_installed()
 * {
 *		global $db;
 *		if($db->table_exists("hello_world"))
 *  	{
 *  		return true;
 *		}
 *		return false;
 * }
 *
 * _uninstall():
 *    Called whenever a plugin is to be uninstalled. This should remove ALL traces of the plugin
 *    from the installation (tables etc). If it does not exist, uninstall button is not shown.
 *
 * function hello_uninstall()
 * {
 * }
 *
 * _activate():
 *    Called whenever a plugin is activated via the Admin CP. This should essentially make a plugin
 *    "visible" by adding templates/template changes, language changes etc.
 *
 * function hello_activate()
 * {
 * }
 *
 * _deactivate():
 *    Called whenever a plugin is deactivated. This should essentially "hide" the plugin from view
 *    by removing templates/template changes etc. It should not, however, remove any information
 *    such as tables, fields etc - that should be handled by an _uninstall routine. When a plugin is
 *    uninstalled, this routine will also be called before _uninstall() if the plugin is active.
 *
 * function hello_deactivate()
 * {
 * }
 */

function usernotes_install() {
	global $db, $lang;

	// create table
	$create_usernotes = "CREATE TABLE IF NOT EXISTS `". $db->table_prefix ."usernotes` (
		`tid` int(11) NOT NULL,
		`uid` int(11) NOT NULL,
		`primary_skill` text NOT NULL,
		`secondary_skill` text NOT NULL,
		`attribute` text NOT NULL
	)";
	$db->write_query($create_usernotes);

}

function usernotes_is_installed() {
	global $db;

	if($db->table_exists("usernotes")) {
		return true;
	} else {
		return false;
	}
}

function usernotes_activate() {
	global $db, $mybb;

	// set up templates
	$template0 = array(
		"tid" => NULL,
		"title" => "usernotes",
		"template" => $db->escape_string('<html>
<head>
<title>{$title}</title>
{$headerinclude}
</head>
<body>
{$header}

<form action="showthread.php?tid={$tid}&action=usernotes&update=notes" method="post">
	<input placeholder="Primary Skill" type="text" name="primary_skill" value="{$primary_skill}"><br>
	<input placeholder="Secondary Skill" type="text" name="secondary_skill" value="{$secondary_skill}"><br>
	<input placeholder="Attribute" type="text" name="attribute" value="{$attribute}"><br>
	<input type="submit" value="Save">
</form>

{$footer}
</body>
</html>'),
		"sid" => "-1"
	);
	$db->insert_query("templates", $template0);

	// edit templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';

	// creates a link under online status
	find_replace_templatesets('showthread', '#'.preg_quote('{$thread[\'subject\']}</strong>').'#', '{$thread[\'subject\']}</strong> <small><a href="showthread.php?tid={$tid}&action=usernotes">[skills/attribute]</a></small>');
}

function usernotes_deactivate() {
	global $db, $mybb;

	// delete templates
	$db->delete_query('templates', 'title IN ( \'usernotes\' )');

	// edit templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote(' <small><a href="showthread.php?tid={$tid}&action=usernotes">[skills/attribute]</a></small>').'#', " ", 0);
}

function usernotes_uninstall() {
	global $db, $mybb;

	$remove_usernotes = "DROP TABLE IF EXISTS `". $db->table_prefix ."usernotes`";
	$db->write_query($remove_usernotes);

}

// THIS IS THE MAIN FUNCTION LOLOL
function usernotes_input() {
	// global $mybb, $lang, $db, $threadlog, $templates, $header, $footer, $headerinclude, $title, $theme;
	global $mybb, $db, $lang, $header, $footer, $templates, $headerinclude, $title, $theme, $tid, $uid, $thread;

	if($mybb->input['action'] == 'usernotes') {

		// add some crumbs
		add_breadcrumb("Skills/Attributes");

		// add a title, yo
		$title = $lang->sprintf($mybb->settings['bbname'].' - Skills/Attributes for '. $thread['subject'] .'');
		
		$uid = $mybb->user['uid'];

		if($mybb->input['update'] == 'notes') {

			$query1 = $db->simple_select("usernotes", "*", "tid = '". $tid ."' AND uid = '". $uid ."'");

			// make a new row if this usernote does not exist
			if($db->num_rows($query1) == 0) {

				$usernotes_array = array(
					"tid" => $tid,
					"uid" => $uid,
					"primary_skill" => $db->escape_string($_POST['primary_skill']),
					"secondary_skill" => $db->escape_string($_POST['secondary_skill']),
					"attribute" => $db->escape_string($_POST['attribute'])
				);

				$db->insert_query("usernotes", $usernotes_array);
			}

			// update the row if this usernote does exist
			if($db->num_rows($query1) >= 1) {

				$usernotes_update = array(
					"primary_skill" => $db->escape_string($_POST['primary_skill']),
					"secondary_skill" => $db->escape_string($_POST['secondary_skill']),
					"attribute" => $db->escape_string($_POST['attribute'])
				);

				$db->update_query("usernotes", $usernotes_update, "tid = '". $tid ."' AND uid = '". $uid ."'");

			}

			// redirect 'em so they don't try to refresh and resubmit the form
			header('Location: '. $mybb->settings['bburl'] .'/showthread.php?tid='. $tid .'&action=usernotes');

		}

		// get the current usernotes
		$query2 = $db->simple_select("usernotes", "*", "tid = '". $tid ."' AND uid = '". $uid ."'");
		$usernotes = $db->fetch_array($query2);

		// output user notes (so people don't have to reenter them every time, duh)
		if ($db->num_rows($query2) > 0) {
			$primary_skill = stripslashes($usernotes['primary_skill']);
			$secondary_skill = stripslashes($usernotes['secondary_skill']);
			$attribute = stripslashes($usernotes['attribute']);
		} else {
			$primary_skill = "";
			$secondary_skill = "";
			$attribute = "";
		}

		// get dat template ready
		eval("\$usernotes_page = \"".$templates->get("usernotes")."\";");

		// output the gorram page already!
		output_page($usernotes_page);

		exit;
	}
}
?>