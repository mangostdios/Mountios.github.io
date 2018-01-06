<?php
$install_pages = array(
	'welcome'		=>	false,
	'dbtype'		=>	false,
	'dbsetup'		=>	false,
	'syscfg'		=>	false,
	'adminacct'		=>	false,
	'brdtitle'		=>	false,
	'confirmation'	=>	false,
);

define('FORUM_ROOT', dirname(__FILE__));
include FORUM_ROOT . '/app_config/sysinfo.php';

function add_cookie_data($key, $data) {
	if (isset($_COOKIE['install_cookie'])) {
		$cookie = base64_decode($_COOKIE['install_cookie']);
	} else {
		$cookie = '';
	}
	$parts = explode(chr(1), $cookie);
	$cookie_data = array();
	foreach ($parts as $val) {
		$subparts = explode('|', $val, 2);
		if (sizeof($subparts) > 1) {
			$cookie_data[$subparts[0]] = $subparts[1];
		}
	}
	$cookie_data[$key] = $data;
	
	$new_cookie_data = array();
	foreach ($cookie_data as $key => $val) {
		$new_cookie_data[] = $key . '|' . $val;
	}
	$new_cookie = base64_encode(implode(chr(1), $new_cookie_data));
	$_COOKIE['install_cookie'] = $new_cookie;
	setcookie('install_cookie', $new_cookie);
}

function get_cookie_data($key) {
	if (!isset($_COOKIE['install_cookie'])) {
		return false;
	}
	$cookie = base64_decode($_COOKIE['install_cookie']);
	$parts = explode(chr(1), $cookie);
	foreach ($parts as $val) {
		$subparts = explode('|', $val, 2);
		if ($subparts[0] == $key) {
			return $subparts[1];
		}
	}
	return false;
}

function test_db() {
	global $db;
	if (file_exists('app_resources/database/' . get_cookie_data('dbtype') . '.php')) {
		include_once 'app_resources/database/' . get_cookie_data('dbtype') . '.php';
		$info = array(
			'host'		=>	get_cookie_data('dbhost'),
			'username'	=>	get_cookie_data('dbuser'),
			'password'	=>	get_cookie_data('dbpass'),
			'name'		=>	get_cookie_data('dbname'),
			'prefix'		=>	get_cookie_data('dbprefix'),
			'hide_errors'	=>	true
		);
		$db = new Database($info);
		if (get_cookie_data('dbname') == '') {
			return false;
		}
		if ($db->link) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function check_input($inputstr, array $validchars) {
	for ($i = 0; $i < strlen($inputstr); $i++) {
		if (!ctype_alnum($inputstr{$i}) && !in_array($inputstr{$i}, $validchars)) {
			return false;
		}
	}
	return true;
}

function db_fail() {
	global $db_fail, $install_pages, $page;
	$db_fail = true;
	$install_pages['dbsetup'] = true;
	$page = 'dbsetup';
}

function get_db_info($name) {
	$dbtype = $name;
	if (ctype_alnum($dbtype)) {
		if (file_exists(FORUM_ROOT . '/app_resources/database/' . $dbtype . '.php')) {
			$contents = file_get_contents(FORUM_ROOT . '/app_resources/database/' . $dbtype . '.php');
			if (strstr($contents, 'FutureBB Database Spec - DO NOT REMOVE')) {
				//database file registered
				preg_match('%Name<(.*?)>%', $contents, $matches);
				if (!empty($matches[1])) {
					$db_name = $matches[1];
					preg_match('%Extension<(.*?)>%', $contents, $matches);
					if (!empty($matches[1]) && extension_loaded($matches[1])) {
						return $db_name;
					}
				}
			}
		}
	}
	return false;
}

//language stuff
include 'app_resources/includes/functions.php';
if (get_cookie_data('language') === false) {
	$futurebb_user = array('language' => 'English');
} else {
	$futurebb_user = array('language' => get_cookie_data('language'));
}
translate('<addfile>', 'install');

$page = '';
if (isset($_GET['downloadconfigxml'])) {
	//create config.xml file
	$xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8" ?><!--FutureBB Server Configuration - edit at your own risk--><config></config>');
	
	$db_xml = $xml->addChild('cfgset');
	$db_xml->addAttribute('type', 'database');
	$db_xml->addChild('type', get_cookie_data('dbtype'));
	$db_xml->addChild('host', get_cookie_data('dbhost'));
	$db_xml->addChild('username', get_cookie_data('dbuser'));
	$db_xml->addChild('password', get_cookie_data('dbpass'));
	$db_xml->addChild('name', get_cookie_data('dbname'));
	$db_xml->addChild('prefix', get_cookie_data('dbprefix'));
	
	$srv_xml = $xml->addChild('cfgset');
	$srv_xml->addAttribute('type', 'server');
	$srv_xml->addChild('baseurl', get_cookie_data('baseurl'));
	$srv_xml->addChild('basepath', get_cookie_data('basepath'));
	$srv_xml->addChild('cookie_name', 'futurebb_cookie_' . substr(md5(time()), 0, 10));
	$srv_xml->addChild('debug', 'off');
	
	header('Content-type: application/xml');
	header('Content-disposition: attachment; filename=config.xml');
	echo $xml->asXML();
	die;
} else if (isset($_GET['downloadhtaccess'])) {
	//download the default .htaccess file
	header('Content-type: text/plain');
	if (!strstr($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
		echo 'You are not running Apache, therefore the .htaccess file is useless to you.'; die;
	}
	header('Content-disposition: attachment; filename=.htaccess');
	echo 'RewriteEngine On' . "\n";
	echo 'RewriteBase ' . get_cookie_data('basepath') . "\n";
	echo 'RewriteRule ^static/(.*?)$ static/$1 [L]' . "\n";
	echo 'RewriteRule ^(.*)$ dispatcher.php';
	die;
} else if (isset($_POST['language_done'])) {
	$page = 'complete';
} else if (isset($_GET['language_insert'])) {
	include 'app_resources/database/db_resources.php';
	if (test_db()) {
		$page = 'language_insert';
	} else {
		$page = '';
	}
} else if (isset($_POST['install'])) {
	include 'app_resources/database/db_resources.php';
	if (test_db()) {
		//create database structure, automatically generated
		$tables['bans'] = new DBTable('bans');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['bans']->add_field($new_fld);
		$new_fld = new DBField('username','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['bans']->add_field($new_fld);
		$new_fld = new DBField('ip','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['bans']->add_field($new_fld);
		$new_fld = new DBField('message','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['bans']->add_field($new_fld);
		$new_fld = new DBField('expires','INT');
		$new_fld->set_default('NULL');
		$tables['bans']->add_field($new_fld);
		$tables['bans']->commit();
		
		$tables['categories'] = new DBTable('categories');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['categories']->add_field($new_fld);
		$new_fld = new DBField('name','VARCHAR(100)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['categories']->add_field($new_fld);
		$new_fld = new DBField('sort_position','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['categories']->add_field($new_fld);
		$tables['categories']->commit();
		
		$tables['config'] = new DBTable('config');
		$new_fld = new DBField('c_name','VARCHAR(50)');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['config']->add_field($new_fld);
		$new_fld = new DBField('c_value','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['config']->add_field($new_fld);
		$new_fld = new DBField('load_extra','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['config']->add_field($new_fld);
		$tables['config']->commit();
		
		$tables['extensions'] = new DBTable('extensions');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['extensions']->add_field($new_fld);
		$new_fld = new DBField('name','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['extensions']->add_field($new_fld);
		$new_fld = new DBField('website','TEXT');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['extensions']->add_field($new_fld);
		$new_fld = new DBField('support_url','TEXT');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['extensions']->add_field($new_fld);
		$new_fld = new DBField('uninstallable','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['extensions']->add_field($new_fld);
		$tables['extensions']->commit();
		
		$tables['forums'] = new DBTable('forums');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('url','VARCHAR(250)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('name','VARCHAR(200)');
		$new_fld->set_default('NULL');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('cat_id','INT');
		$new_fld->set_default('NULL');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('sort_position','INT');
		$new_fld->set_default('NULL');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('description','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('redirect_id','INT');
		$new_fld->set_default('NULL');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('last_post','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('last_post_id','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('view_groups','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('topic_groups','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('reply_groups','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('num_topics','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('num_posts','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['forums']->add_field($new_fld);
		$new_fld = new DBField('archived','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['forums']->add_field($new_fld);
		$tables['forums']->commit();
		
		$tables['interface_history'] = new DBTable('interface_history');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('action','enum(\'edit\',\'create\',\'delete\')');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'edit\'');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('area','enum(\'language\',\'interface\',\'pages\')');
		$new_fld->add_extra('NOT NULL');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('field','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('user','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('time','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['interface_history']->add_field($new_fld);
		$new_fld = new DBField('old_value','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['interface_history']->add_field($new_fld);
		$tables['interface_history']->commit();
		
		$tables['language'] = new DBTable('language');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['language']->add_field($new_fld);
		$new_fld = new DBField('language','VARCHAR(20)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'English\'');
		$tables['language']->add_field($new_fld);
		$new_fld = new DBField('langkey','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['language']->add_field($new_fld);
		$new_fld = new DBField('value','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['language']->add_field($new_fld);
		$new_fld = new DBField('category','VARCHAR(15)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'main\'');
		$tables['language']->add_field($new_fld);
		$tables['language']->commit();
		
		$tables['notifications'] = new DBTable('notifications');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('type','VARCHAR(20)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('user','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('send_time','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('contents','MEDIUMTEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('arguments','MEDIUMTEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('read_time','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['notifications']->add_field($new_fld);
		$new_fld = new DBField('read_ip','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['notifications']->add_field($new_fld);
		$tables['notifications']->commit();
		
		$tables['pages'] = new DBTable('pages');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('url','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('file','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('template','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('nocontentbox','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('admin','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('moderator','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['pages']->add_field($new_fld);
		$new_fld = new DBField('subdirs','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['pages']->add_field($new_fld);
		$tables['pages']->commit();
		
		$tables['posts'] = new DBTable('posts');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('poster','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('poster_ip','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('content','MEDIUMTEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('parsed_content','MEDIUMTEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('posted','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('topic_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('deleted','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('deleted_by','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('last_edited','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('last_edited_by','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['posts']->add_field($new_fld);
		$new_fld = new DBField('disable_smilies','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['posts']->add_field($new_fld);
		$tables['posts']->commit();
		
		$tables['read_tracker'] = new DBTable('read_tracker');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['read_tracker']->add_field($new_fld);
		$new_fld = new DBField('user_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['read_tracker']->add_field($new_fld);
		$new_fld = new DBField('topic_id','INT');
		$new_fld->set_default('NULL');
		$tables['read_tracker']->add_field($new_fld);
		$new_fld = new DBField('forum_id','INT');
		$new_fld->set_default('NULL');
		$tables['read_tracker']->add_field($new_fld);
		$tables['read_tracker']->commit();
		
		$tables['reports'] = new DBTable('reports');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('post_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('post_type','enum(\'post\',\'msg\',\'special\')');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'post\'');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('reason','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('reported_by','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('time_reported','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('zapped','INT');
		$new_fld->set_default('NULL');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('zapped_by','INT');
		$new_fld->set_default('NULL');
		$tables['reports']->add_field($new_fld);
		$new_fld = new DBField('status','enum(\'unread\',\'review\',\'reject\',\'accept\',\'noresp\',\'withdrawn\')');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'unread\'');
		$tables['reports']->add_field($new_fld);
		$tables['reports']->commit();
		
		$tables['search_cache'] = new DBTable('search_cache');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['search_cache']->add_field($new_fld);
		$new_fld = new DBField('hash', 'VARCHAR(50)');
		$new_fld->set_default('');
		$new_fld->add_extra('NOT NULL');
		$tables['search_cache']->add_field($new_fld);
		$new_fld = new DBField('results', 'TEXT');
		$new_fld->set_default('');
		$new_fld->add_extra('NOT NULL');
		$tables['search_cache']->add_field($new_fld);
		$new_fld = new DBField('time','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['search_cache']->add_field($new_fld);
		$tables['search_cache']->commit();
		
		$tables['search_index'] = new DBTable('search_index');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['search_index']->add_field($new_fld);
		$new_fld = new DBField('post_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['search_index']->add_field($new_fld);
		$new_fld = new DBField('word','VARCHAR(255)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['search_index']->add_field($new_fld);
		$new_fld = new DBField('locations', 'TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['search_index']->add_field($new_fld);
		$tables['search_index']->commit();
		
		$tables['topics'] = new DBTable('topics');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('subject','VARCHAR(200)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('url','VARCHAR(210)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('forum_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('deleted','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('deleted_by','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('last_post','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('last_post_id','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('first_post_id','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('closed','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('sticky','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('redirect_id','INT');
		$new_fld->add_extra('NULL');
		$new_fld->set_default('NULL');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('show_redirect','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['topics']->add_field($new_fld);
		$new_fld = new DBField('num_replies','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['topics']->add_field($new_fld);
		$tables['topics']->commit();
		
		$tables['users'] = new DBTable('users');
		$new_fld = new DBField('id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('deleted','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('username','VARCHAR(50)');
		$new_fld->add_key('UNIQUE');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('password','VARCHAR(100)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('email','VARCHAR(500)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('activate_key','VARCHAR(50)');
		$new_fld->set_default('NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('recover_key','VARCHAR(50)');
		$new_fld->set_default('NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('registered','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('registration_ip','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('num_posts','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('last_post','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('group_id','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('signature','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('parsed_signature','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('last_visit','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('timezone','INT(3)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('style','VARCHAR(100)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'default\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('language','VARCHAR(100)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'English\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('restricted_privs','set(\'\',\'edit\',\'delete\')');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('block_pm','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('block_notif','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('last_page_load','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('avatar_extension','VARCHAR(4)');
		$new_fld->set_default('NULL');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('rss_token','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$new_fld = new DBField('login_hash','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['users']->add_field($new_fld);
		$tables['users']->commit();
		
		$tables['user_groups'] = new DBTable('user_groups');
		$new_fld = new DBField('g_id','INT');
		$new_fld->add_key('PRIMARY');
		$new_fld->add_extra('NOT NULL');
		$new_fld->add_extra('AUTO_INCREMENT');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_permanent','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_guest_group','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_name','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$new_fld->set_default('\'\'');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_title','VARCHAR(50)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_admin_privs','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_mod_privs','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_mod_view_ip','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_mod_ban_users','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_mod_delete_posts','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_mod_edit_posts','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_edit_posts','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_delete_posts','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_signature','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_user_list','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_user_list_groups','TEXT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('\'\'');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_promote_group','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_promote_posts','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_promote_operator','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_promote_days','INT');
		$new_fld->add_extra('NOT NULL');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_post_flood','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_posts_per_hour','INT');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_post_links','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_post_images','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('0');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_access_board','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_view_forums','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_post_topics','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$new_fld = new DBField('g_post_replies','TINYINT(1)');
		$new_fld->add_extra('NOT NULL');
		$new_fld->set_default('1');
		$tables['user_groups']->add_field($new_fld);
		$tables['user_groups']->commit();
		unset($tables);
		unset($new_fld);
		
		//add database data
		set_config('board_title', get_cookie_data('board_title'));
		set_config('announcement_text', '');
		set_config('announcement_enable', 0);
		set_config('online_timeout', 300);
		set_config('show_post_count', 1);
		set_config('sig_max_length', 0);
		set_config('sig_max_lines', 0);
		set_config('sig_max_height', 0);
		set_config('default_language', 'English');
		set_config('default_style', 'default');
		set_config('default_user_group', 3);
		set_config('topics_per_page', 25);
		set_config('posts_per_page', 25);
		set_config('verify_registrations', 0);
		set_config('avatars', 0);
		set_config('censoring', base64_encode(''));
		set_config('maintenance', 0);
		set_config('admin_email', get_cookie_data('adminemail'));
		set_config('maintenance_message', 'These forums are down for maintenance. Please come back later.');
		set_config('footer_text', '');
		set_config('turn_on_maint', 0);
		set_config('turn_off_maint', 0);
		set_config('rules', '');
		set_config('addl_header_links', '');
		set_config('allow_privatemsg', 0);
		set_config('allow_notifications', 1);
		set_config('imghostrestriction', 'none|');
		set_config('last_update_check', 0);
		set_config('new_version', 0);
		set_config('max_quote_depth', 4);
		set_config('disable_registrations', 0);
		set_config('db_version', DB_VERSION);
		set_config('enable_bbcode', 1);
		set_config('enable_smilies', 1);
		set_config('avatar_max_filesize', 1024);
		set_config('avatar_max_width', 64);
		set_config('avatar_max_height', 64);
		set_config('bbcode_privatemsg', 1);
		set_config('header_links', '<?xml version="1.0" ?>
<linkset>
    <link path="">index</link>
    <link path="users/$username$" perm="valid">profile</link>
    <link path="users" perm="g_user_list">userlist</link>
    <link path="search">search</link>
    <link path="admin" perm="g_admin_privs">administration</link>
    <link path="admin/bans" perm="g_mod_privs ~g_admin_privs">administration</link>
    <link path="register/$reghash$" perm="~valid">register</link>
    <link path="logout" perm="valid">logout</link>
</linkset>');
		set_config('admin_pages', 'PT5pbmRleApiYW5zPT5iYW5zCnJlcG9ydHM9PnJlcG9ydHMKY2Vuc29yaW5nPT5jZW5zb3JpbmcKZm9ydW1zPT5mb3J1bXMKaXBfdHJhY2tlcj0+aXB0cmFja2VyCnVzZXJfZ3JvdXBzPT51c2VyZ3JvdXBzCnRyYXNoX2Jpbj0+dHJhc2hiaW4KbWFpbnRlbmFuY2U9Pm1haW50ZW5hbmNlCnN0eWxlPT5zdHlsZQpleHRlbnNpb25zPT5leHRlbnNpb25zCmludGVyZmFjZT0+aW50ZXJmYWNl');
		set_config('mod_pages', 'YmFucz0+YmFucwpyZXBvcnRzPT5yZXBvcnRzCnRyYXNoX2Jpbj0+dHJhc2hiaW4KaXBfdHJhY2tlcj0+aXB0cmFja2Vy');
		set_config('date_format', 'd M Y');
		set_config('time_format', 'H:i');
		
		//create guest user
		$insert = new DBInsert('users', array(
			'username'			=> 'Guest',
			'password'			=> 'Guest',
			'email'				=> '',
			'registered'		=> 0,
			'registration_ip'	=> '',
			'group_id'			=> 0,
			'last_visit'		=> 0,
			'last_page_load'	=> 0,
			'signature'			=> '',
		), 'Failed to create admin user');
		$insert->commit();
		
		//create admin user
		$insert = new DBInsert('users', array(
			'username'			=> get_cookie_data('adminusername'),
			'password'			=> futurebb_hash(get_cookie_data('adminpass')),
			'email'				=> get_cookie_data('adminemail'),
			'registered'		=> time(),
			'registration_ip'	=> $_SERVER['REMOTE_ADDR'],
			'group_id'			=> 1,
			'last_visit'		=> time(),
			'last_page_load'	=> time(),
			'rss_token'			=> md5(time())
		), 'Failed to create admin user');
		$insert->commit();
		
		//create user groups
		$insert = new DBInsert('user_groups', array(
			'g_permanent'		=> 1,
			'g_guest_group'		=> 0,
			'g_name'			=> 'Administrators',
			'g_title'			=> 'Administrator',
			'g_admin_privs'		=> 1,
			'g_mod_privs'		=> 1,
			'g_edit_posts'		=> 1,
			'g_delete_posts'	=> 1,
			'g_signature'		=> 1,
			'g_user_list'		=> 1,
			'g_user_list_groups'=> '',
			'g_promote_group'	=> 0,
			'g_promote_posts'	=> 0,
			'g_promote_operator'=> 0,
			'g_promote_days'	=> 0,
			'g_post_flood'		=> 0,
			'g_posts_per_hour'	=> 0,
			'g_post_links'		=> 1,
			'g_post_images'	=> 1
		), 'Failed to create admin user group');
		$insert->commit();
		$insert = new DBInsert('user_groups', array(
			'g_permanent'		=> 1,
			'g_guest_group'	=> 1,
			'g_name'			=> 'Guests',
			'g_title'			=> 'Guest',
			'g_admin_privs'	=> 0,
			'g_mod_privs'		=> 0,
			'g_edit_posts'		=> 0,
			'g_delete_posts'	=> 0,
			'g_signature'		=> 0,
			'g_user_list'		=> 0,
			'g_user_list_groups'=> '',
			'g_promote_group'	=> 0,
			'g_promote_posts'	=> 0,
			'g_promote_operator'=> 0,
			'g_promote_days'	=> 0,
			'g_post_flood'		=> 0,
			'g_posts_per_hour'	=> 0,
			'g_post_links'		=> 0,
			'g_post_images'	=> 0
		), 'Failed to create guest user group');
		$insert->commit();
		$insert = new DBInsert('user_groups', array(
			'g_permanent'		=> 1,
			'g_guest_group'	=> 0,
			'g_name'			=> 'Members',
			'g_title'			=> 'Member',
			'g_admin_privs'	=> 0,
			'g_mod_privs'		=> 0,
			'g_edit_posts'		=> 1,
			'g_delete_posts'	=> 1,
			'g_signature'		=> 1,
			'g_user_list'		=> 1,
			'g_user_list_groups'=> '',
			'g_promote_group'	=> 0,
			'g_promote_posts'	=> 0,
			'g_promote_operator'=> 0,
			'g_promote_days'	=> 0,
			'g_post_flood'		=> 60,
			'g_posts_per_hour'	=> 0,
			'g_post_links'		=> 1,
			'g_post_images'	=> 1
		), 'Failed to create member user group');
		$insert->commit();
		
		//run through stock cache to insert pages and language keys
		include FORUM_ROOT . '/app_config/cache/pages.php';
		$q = 'INSERT INTO `#^pages`(url,file,template,nocontentbox,admin,moderator,subdirs) VALUES';
		$page_insert_data = array();
		foreach ($pages as $url => $info) {
			$page_insert_data[] = '(\'' . $db->escape($url) . '\',\'' . $db->escape($info['file']) . '\',' . ($info['template'] ? '1' : '0') . ',' . (isset($info['nocontentbox']) ? '1' : '0') . ',' . ($info['admin'] ? '1' : '0') . ',' . ($info['mod'] ? '1' : '0') . ',0)';
		}
		foreach ($pagessubdirs as $url => $info) {
			$page_insert_data[] = '(\'' . $db->escape($url) . '\',\'' . $db->escape($info['file']) . '\',' . ($info['template'] ? '1' : '0') . ',' . (isset($info['nocontentbox']) ? '1' : '0') . ',' . ($info['admin'] ? '1' : '0') . ',' . ($info['mod'] ? '1' : '0') . ',1)';
		}
		$q = new DBMassInsert('pages', array('url', 'file', 'template', 'nocontentbox', 'admin', 'moderator', 'subdirs'), $page_insert_data, 'Failed to insert pages');
		$q->commit();
		unset($page_insert_data);
		unset($pages);
		unset($pagessubdirs);
		
		redirect('install.php?language_insert=1');
	} else {
		db_fail();
	}
} else if (isset($_POST['brdsettings'])) {
	foreach ($_POST['config'] as $key => $val) {
		$install_pages['confirmation'] = true;
		$page = 'confirm';
		foreach ($_POST['config'] as $key => $val) {
			add_cookie_data($key, $val);
		}
		if (strlen($_POST['config']['board_title']) < 4) {
			$install_pages['brdtitle'] = true;
			$page = 'brdsettings';
			$error = 'titletooshort';
			$install_pages['confirmation'] = false;
		}
	}
	if (isset($_POST['back'])) {
		$install_pages['adminacct'] = true;
		$pwd_mismatch = false;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		if (isset($error)) {
			unset($error);
		}
	}
} else if (isset($_POST['adminacc'])) {
	add_cookie_data('adminusername', $_POST['adminusername']);
	add_cookie_data('adminemail', $_POST['adminemail']);
	add_cookie_data('adminpass', $_POST['adminpass']);
	$common = explode("\n", base64_decode(file_get_contents(FORUM_ROOT . '/app_config/commonpasswords.txt')));
	if ($_POST['adminpass'] != $_POST['confirmadminpass']) {
		$install_pages['adminacct'] = true;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		$error = 'pwdmismatch';
	} else if (strlen($_POST['adminusername']) < 4 || !check_username($_POST['adminusername'])) {
		$install_pages['adminacct'] = true;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		$error = 'usernameinvalid';
	} else if (!preg_match('%.*?\@.*?\.%', $_POST['adminemail'])) {
		$install_pages['adminacct'] = true;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		$error = 'bademail';
	} else if (strlen($_POST['adminpass']) < 8) {
		$install_pages['adminacct'] = true;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		$error = 'passtooshort';
	} else if (in_array($_POST['adminpass'], $common)) {
		$install_pages['adminacct'] = true;
		$page = 'adminacc';
		$install_pages['brdtitle'] = false;
		$error = 'commonpass';
	} else {
		$install_pages['brdtitle'] = true;
		$page = 'brdsettings';
	}
	unset($common);
	if (isset($_POST['back'])) {
		$install_pages['syscfg'] = true;
		$page = 'syscfg';
		$install_pages['adminacct'] = false;
		$install_pages['brdtitle'] = false;
		if (isset($error)) {
			unset($error);
		}
	}
} else if (isset($_POST['syscfg'])) {
	add_cookie_data('baseurl', $_POST['baseurl']);
	add_cookie_data('basepath', $_POST['basepath']);
	$install_pages['adminacct'] = true;
	$page = 'adminacc';
	
	if (!preg_match('%https?://(.*?)' . preg_quote($_POST['basepath']) . '$%', $_POST['baseurl'])) {
		$error = 'invalidbaseurl';
		$install_pages['syscfg'] = true;
		$page = 'syscfg';
		$install_pages['adminacct'] = false;
		$install_pages['brdtitle'] = false;
	} else if ($_POST['basepath']{0} != '/' || !check_input($_POST['basepath'], array('/', '.', '-'))) {
		$error = 'invalidbasepath';
		$install_pages['syscfg'] = true;
		$page = 'syscfg';
		$install_pages['adminacct'] = false;
		$install_pages['brdtitle'] = false;
	}
	if (isset($_POST['back'])) {
		$install_pages['dbsetup'] = true;
		$page = 'dbsetup';
		$install_pages['adminacct'] = false;
		$db_fail = false;
		if (isset($error)) {
			unset($error);
		}
	}
} else if (isset($_POST['dbsetup'])) {
	if (get_cookie_data('dbtype') != 'sqlite3') {
		if (!check_input($_POST['dbhost'], array('.', '_', '-')) || !check_input($_POST['dbuser'], array('.', '_', '-'))) {
			db_fail();
		}
		add_cookie_data('dbhost', $_POST['dbhost']);
		add_cookie_data('dbuser', $_POST['dbuser']);
		add_cookie_data('dbpass', $_POST['dbpass']);
	}
	if (!check_input($_POST['dbname'], array('.', '_', '-', '/'))) {
		db_fail();
	}
	add_cookie_data('dbname', $_POST['dbname']);
	if (!check_input($_POST['dbprefix'], array('.', '_', '-'))) {
		db_fail();
	}
	add_cookie_data('dbprefix', $_POST['dbprefix']);
	
	if (isset($_POST['back'])) {
		$install_pages['dbtype'] = true;
		$page = 'dbtype';
		$install_pages['syscfg'] = false;
		$install_pages['dbsetup'] = false;
	} else {
		//test database
		if ((!isset($db_fail) || !$db_fail) && test_db()) {
			$install_pages['syscfg'] = true;
			$page = 'syscfg';
		} else {
			db_fail();
		}
	}
} else if (isset($_POST['dbtype'])) {
	//check a valid database was entered
	$ok = false;
	if (get_db_info($_POST['dbtype'])) {
		add_cookie_data('dbtype', $_POST['dbtype']);
		$db_fail = false;
		$install_pages['dbsetup'] = true;
		$page = 'dbsetup';
	} else {
		$install_pages['dbtype'] = true;
		$page = 'dbtype';
		$error = translate('baddbtype');
	}
	if (isset($_POST['back'])) {
		$install_pages['welcome'] = true;
		$page = 'welcome';
		$install_pages['dbsetup'] = false;
	}
} else if (isset($_POST['start'])) {
	$install_pages['dbtype'] = true;
	$page = 'dbtype';
	if (isset($_POST['language']) && (!check_input($_POST['language'], array()) || !file_exists(FORUM_ROOT . '/app_config/cache/language/' . basename($_POST['language']) . '/install.php'))) {
		$install_pages['dbtype'] = false;
		$page = 'welcome';
		$install_pages['welcome'] = true;
		$error = 'Invalid language';
		add_cookie_data('language', 'English');
	} else {
		if (isset($_POST['language'])) {
			add_cookie_data('language', $_POST['language']);
		}
	}
} else {
	setcookie('install_cookie', '');
	$install_pages['welcome'] = true;
	$page = 'welcome';
}
ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?php echo translate('headertext'); ?></title>
		<style type="text/css">
		<?php
		$data = file_get_contents('app_resources/pages/css/default.css');
		$data = preg_replace('%<\?php.*?\?>%ms', '', $data);
		echo $data;
		?>
		</style>
	</head>
	<body>
		<div id="futurebb">
			<div class="forum_header">
				<h1 style="text-align:center"><?php echo translate('headertext'); ?></h1>
				<div id="navlistwrap">
					<?php
					$pages_echo = array();
					foreach ($install_pages as $key => $current) {
						if ($current) {
							$pages_echo[] = '<b>' . translate($key) . '</b>';
						} else {
							$pages_echo[] = translate($key);
						}
					}
					echo implode(' &rarr; ', $pages_echo);
					?>
				</div>
			</div>
			<div class="forum_content">
				<?php
				switch ($page) {
					case 'welcome':
						?>
						<h2><?php echo translate('welcometofbb'); ?></h2>
						<p><?php echo translate('intro'); ?></p>
						<?php
						$ok = true;
						if (isset($error)) {
							echo '<p style="color:#F00; font-weight:bold">' . $error . '</p>';
						}
						//check if necessary directories are writable
						if (!file_exists(FORUM_ROOT . '/temp') || !is_dir(FORUM_ROOT . '/temp')) {
							$ok = false;
							echo '<p style="color:#F00; font-weight:bold">The directory &quot;temp&quot; does not exist in the forum root directory. Please create it.</p>';
						}
						if (!writable(FORUM_ROOT . '/static/avatars/')) {
							$ok = false;
							echo '<p style="color:#F00; font-weight:bold">The directory &quot;static/avatars&quot; is not writable. Please change the permissions so that it is (chmod to 0777 if in doubt)</p>';
						}
						if (!writable(FORUM_ROOT . '/temp/')) {
							$ok = false;
							echo '<p style="color:#F00; font-weight:bold">The directory &quot;temp&quot; is not writable. Please change the permissions so that it is (chmod to 0777 if in doubt)</p>';
						}
						if (!writable(FORUM_ROOT . '/app_config/cache/')) {
							$ok = false;
							echo '<p style="color:#F00; font-weight:bold">The directory &quot;app_config/cache&quot; is not writable. Please change the permissions so that it is (chmod to 0777 if in doubt)</p>';
						}
						if (strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') && function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) { //check for mod_rewrite
							$ok = false;
							echo '<p style="color:#F00; font-weight:bold">mod_rewrite is not installed in Apache. This means that the URL system will not work. Please install it.</p>';
						}
						if (!strstr($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
							echo '<p style="color:#A00; font-weight:bold">You are not running Apache. Please do not continue setting up this software unless you know what you are doing and are familiar with how to operate your server software. Specifically, you need to be knowledgeable about the available URL rewrite tools.</p>';
						}
						?>
						<form action="install.php" method="post" enctype="multipart/form-data">
                        	<p><?php echo translate('selectlang'); ?> <select name="language"><?php
							$handle = opendir(FORUM_ROOT . '/app_config/cache/language');
							while ($lang = readdir($handle)) {
								if ($lang != '.' && $lang != '..' && file_exists(FORUM_ROOT . '/app_config/cache/language/' . $lang . '/install.php')) {
									echo '<option value="' . $lang . '">' . $lang . '</option>';
								}
							}
							?></select></p>
							<p><input type="submit" name="start" value="<?php echo translate('continue'); ?> &rarr;"<?php if (!$ok) echo ' disabled="disabled"'; ?> /></p>
						</form>
						<?php
						break;
					case 'dbtype':
						?>
                        <h2><?php echo translate('dbtype'); ?></h2>
                        <form action="install.php" method="post" enctype="multipart/form-data">
                        	<?php
							if (isset($error)) {
								echo '<p style="color:#F00; font-weight:bold">' . $error . '</p>';
							}
							?>
                        	<p><?php echo translate('selectdbtype'); ?> <select name="dbtype">
							<?php
							$handle = opendir(FORUM_ROOT . '/app_resources/database');
							$existing_db_type = get_cookie_data('dbtype');
							while ($file = readdir($handle)) {
								if ($file != '.' && $file != '..') {
									$contents = file_get_contents(FORUM_ROOT . '/app_resources/database/' . $file);
									if (strstr($contents, 'FutureBB Database Spec - DO NOT REMOVE')) {
										//database file registered
										preg_match('%Name<(.*?)>%', $contents, $matches);
										if (!empty($matches[1])) {
											$name = $matches[1];
											preg_match('%Extension<(.*?)>%', $contents, $matches);
											if (!empty($matches[1]) && extension_loaded($matches[1])) {
												echo '<option value="' . basename($file, '.php') . '"';
												if (basename($file, '.php') == $existing_db_type) {
													echo ' selected="selected"';
												}
												echo '>' . $name . '</option>';
											}
										}
									}
								}
							}
                            ?>
                            </select></p>
                            <p><input type="submit" value="<?php echo translate('continue'); ?> &rarr;" /><input type="submit" name="back" value="&larr; <?php echo translate('back'); ?>" /></p>
                        </form>
                        <?php
						break;
					case 'dbsetup':
						?>
						<h2><?php echo translate('dbsetup'); ?></h2>
						<?php
						if ($db_fail) {
							if (isset($db) && $db->connect_error()) {
								$error = $db->connect_error();
							} else if (get_cookie_data('dbname') == '') {
								$error = 'No database specified';
							} else {
								$error = 'Invalid input - make sure you only use alphanumeric inputs, periods, underscores, and hyphens';
							}
							echo '<p style="color:#F00; font-weight:bold">' . translate('baddb') . $error . '</p>';
						}
						?>
						<form action="install.php" method="post" enctype="multipart/form-data">
							<table border="0">
                            	<tr>
                                	<td><?php echo translate('type'); ?></td>
                                    <td><?php echo get_db_info(get_cookie_data('dbtype')); ?></td>
                                </tr>
                                <?php if (get_cookie_data('dbtype') == 'sqlite3') { ?>
                                <tr>
									<td><?php echo translate('dbfile'); ?></td>
									<td><input type="text" name="dbname" value="<?php echo get_cookie_data('dbname') ? get_cookie_data('dbname') : ''; ?>" /></td>
								</tr>
                                <?php } else { ?>
								<tr>
									<td><?php echo translate('host'); ?></td>
									<td><input type="text" name="dbhost" value="<?php echo get_cookie_data('dbhost') ? get_cookie_data('dbhost') : 'localhost'; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('username'); ?></td>
									<td><input type="text" name="dbuser" value="<?php echo get_cookie_data('dbuser') ? get_cookie_data('dbuser') : 'root'; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('pwd'); ?></td>
									<td><input type="password" name="dbpass" value="<?php echo get_cookie_data('dbpass') ? get_cookie_data('dbpass') : ''; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('name'); ?></td>
									<td><input type="text" name="dbname" value="<?php echo get_cookie_data('dbname') ? get_cookie_data('dbname') : ''; ?>" /></td>
								</tr>
                                <?php } ?>
                                <tr>
									<td><?php echo translate('prefix'); ?></td>
									<td><input type="text" name="dbprefix" value="<?php echo get_cookie_data('dbprefix') ? get_cookie_data('dbprefix') : 'futurebb_'; ?>" /></td>
								</tr>
							</table>
							<p><input type="submit" value="<?php echo translate('continuetest'); ?> &rarr;" /><input type="hidden" name="dbsetup" value="1" /><input type="submit" name="back" value="&larr; <?php echo translate('back'); ?>" /></p>
						</form>
						<?php
						break;
					case 'syscfg':
						?>
						<h2><?php echo translate('syscfg'); ?></h2>
						<p><?php echo translate('dbgood'); ?></p>
                        <p><?php echo translate('seturlstuff'); ?></p>
						<form action="install.php" method="post" enctype="multipart/form-data">
							<?php
							if (isset($error)) {
								echo '<p style="color:#F00; font-weight:bold">' . translate($error) . '</p>';
							}
							?>
							<table border="0">
								<tr>
									<td><?php echo translate('baseurl'); ?></td>
									<td><input type="text" name="baseurl" value="<?php if (get_cookie_data('baseurl')) {
										echo get_cookie_data('baseurl');
									} else {
										if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') { 
											echo 'https://'; 
										} else {
											echo 'http://';
										}
										echo $_SERVER['HTTP_HOST']; echo str_replace('/install.php', '', $_SERVER['REQUEST_URI']);
									}
									?>" size="50" /></td>
								</tr>
								<tr>
									<td><?php echo translate('baseurlpath'); ?></td>
									<td><input type="text" name="basepath" value="<?php echo (get_cookie_data('basepath') ? get_cookie_data('basepath') : str_replace('/install.php', '', $_SERVER['REQUEST_URI'])); ?>" size="50" /></td>
								</tr>
							</table>
							<p><input type="hidden" name="syscfg" value="1" /><input type="submit" value="<?php echo translate('continue'); ?> &rarr;" /><input type="submit" name="back" value="&larr; <?php echo translate('back'); ?>" /></p>
						</form>
						<?php
						break;
					case 'adminacc':
						?>
						<h2><?php echo translate('adminacct'); ?></h2>
						<?php
						if (isset($error)) {
							echo '<p>' . translate($error) . '</p>';
						}
						?>
						<form action="install.php" method="post" enctype="multipart/form-data">
							<table border="0">
								<tr>
									<td><?php echo translate('username'); ?></td>
									<td><input type="text" name="adminusername" value="<?php echo get_cookie_data('adminusername') ? get_cookie_data('adminusername') : ''; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('pwd'); ?></td>
									<td><input type="password" name="adminpass" value="<?php echo (get_cookie_data('adminpass')) ? get_cookie_data('adminpass') : ''; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('confirmpwd'); ?></td>
									<td><input type="password" name="confirmadminpass" value="<?php echo (get_cookie_data('adminpass')) ? get_cookie_data('adminpass') : ''; ?>" /></td>
								</tr>
								<tr>
									<td><?php echo translate('email'); ?></td>
									<td><input type="email" name="adminemail" value="<?php echo get_cookie_data('adminemail') ? get_cookie_data('adminemail') : ''; ?>" /></td>
								</tr>
							</table>
							<p><input type="hidden" name="adminacc" value="1" /><input type="submit" value="<?php echo translate('continue'); ?> &rarr;" /><input type="submit" name="back" value="&larr; <?php echo translate('back'); ?>" /></p>
						</form>
						<?php
						break;
					case 'brdsettings':
						?>
						<h2><?php echo translate('brdtitle'); ?></h2>
						<?php
						if (isset($error)) {
							echo '<p style="color:#F00; font-weight:bold">' . translate($error) . '</p>';
						}
						?>
						<form action="install.php" method="post" enctype="multipart/form-data">
							<table border="0">
								<tr>
									<td><?php echo translate('brdtitle'); ?></td>
									<td><input type="text" name="config[board_title]" value="<?php echo get_cookie_data('board_title') ? get_cookie_data('board_title') : ''; ?>" /></td>
								</tr>
							</table>
							<p><input type="hidden" name="brdsettings" value="1" /><input type="submit" value="<?php echo translate('continue'); ?> &rarr;" /><input type="submit" name="back" value="&larr; <?php echo translate('back'); ?>" /></p>
						</form>
						<?php
						break;
					case 'confirm':
						?>
						<h2><?php echo translate('confirmation'); ?></h2>
						<p><?php echo translate('confirmintro'); ?></p>
						<p><?php echo translate('installdetails'); ?></p>
						<table border="0">
							<tr>
								<td><?php echo translate('dbtype'); ?></td>
								<td><?php echo get_db_info(get_cookie_data('dbtype')); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('dbhost'); ?></td>
								<td><?php echo get_cookie_data('dbhost'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('dbuser'); ?></td>
								<td><?php echo get_cookie_data('dbuser'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('dbpwd'); ?></td>
								<td><em><?php echo translate('notdisplayed'); ?></em></td>
							</tr>
							<tr>
								<td><?php echo translate('dbname'); ?></td>
								<td><?php echo get_cookie_data('dbname'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('dbprefix'); ?></td>
								<td><?php echo get_cookie_data('dbprefix'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('baseurl'); ?></td>
								<td><?php echo get_cookie_data('baseurl'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('baseurlpath'); ?></td>
								<td><?php echo get_cookie_data('basepath'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('adminusername'); ?></td>
								<td><?php echo get_cookie_data('adminusername'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('adminpwd'); ?></td>
								<td><em><?php echo translate('notdisplayed'); ?></em></td>
							</tr>
							<tr>
								<td><?php echo translate('adminemail'); ?></td>
								<td><?php echo get_cookie_data('adminemail'); ?></td>
							</tr>
							<tr>
								<td><?php echo translate('brdtitle'); ?></td>
								<td><?php echo get_cookie_data('board_title'); ?></td>
							</tr>
						</table>
						<form action="install.php" method="post" enctype="multipart/form-data">
							<p><input type="submit" name="start" value="<?php echo translate('modify'); ?>" /> <input type="submit" name="install" value="<?php echo translate('install'); ?>" /></p>
						</form>
						<?php
						break;
					case 'complete':
						?>
						<h2><?php echo translate('installcomplete'); ?></h2>
						<p><?php echo translate('testout1'); ?><a href="<?php echo get_cookie_data('baseurl'); ?>" target="_blank"><?php echo translate('clickhere'); ?></a><?php echo translate('testout2'); ?></p>
                        <ol>
                        	<li><?php echo translate('downloadxml'); ?></li>
                            <?php if (strstr($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
                            	echo translate('apachemsg');
                        	} else {
                            	echo translate('noapachemsg');
                            } ?>
                        </ol>
						<p style="font-size:30px"><a href="install.php?downloadconfigxml"><?php echo translate('xmllink'); ?></a></p>
                        <?php 
						if (strstr($_SERVER['SERVER_SOFTWARE'], 'Apache')) { 
						?>
                        	<p style="font-size:30px"><a href="install.php?downloadhtaccess"><?php echo translate('htalink'); ?></a></p>
						<?php
						} else if (strstr($_SERVER['SERVER_SOFTWARE'], 'nginx')) {
							?>
                            <p><?php echo translate('addtonginx'); ?></p>
          					<pre>
location /<?php echo substr(get_cookie_data('basepath'), 1); ?>/static {
	rewrite ^/<?php echo substr(get_cookie_data('basepath'), 1); ?>/static/(.*?)$ /<?php echo substr(get_cookie_data('basepath'), 1); ?>/static/$1 break;
}
location /<?php echo substr(get_cookie_data('basepath'), 1); ?>/ {
	rewrite ^(.*)$ /<?php echo substr(get_cookie_data('basepath'), 1); ?>/dispatcher.php;
}

                      </pre>
                            <?php
						}
						break;
					case 'language_insert':
						include FORUM_ROOT . '/app_resources/includes/language_insert.php';
						break;
					default:
						echo '<p>' . translate('weirderror') . '</p>';
				}
				?>
			</div>
		</div>
	</body>
</html>
<?php
$content = ob_get_contents();
ob_end_clean();
echo $content;