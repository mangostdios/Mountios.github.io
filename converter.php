<?php
define('PUN_ROOT', dirname(__FILE__) . '/');
include PUN_ROOT . 'include/common.php';
// Load the index.php language file
include PUN_ROOT.'lang/'.$pun_user['language'].'/index.php';

if (isset($_GET['downloadxml'])) {
	header('Content-type: application/xml');
	header('Content-disposition: attachment; filename=converter.xml');
	echo file_get_contents(FORUM_CACHE_DIR . 'converter.xml');
	unlink(FORUM_CACHE_DIR . 'converter.xml');
	die;
}

$page_title = array('Converter');
include PUN_ROOT . 'header.php';

function save_xml($xml) {
	$xml->asXML(FORUM_CACHE_DIR . 'converter.xml');
}

if (isset($_GET['completed'])) {
	echo '<p>Your forum was exported successfully! You now need to download the <a href="converter.php?downloadxml">converter.xml</a> file and upload it to your FutureBB root directory.</p>';
} else if (isset($_GET['genfile'])) {
	if (!isset($_GET['part'])) {
		message('Invalid data given');
	}
	
	if (file_exists(FORUM_CACHE_DIR . 'converter.xml')) {
		$xml_data = file_get_contents(FORUM_CACHE_DIR . 'converter.xml');
	} else {
		$xml_data = '<?xml version="1.0" encoding="utf-8" ?><converterdata></converterdata>';
	}
	$xml = new SimpleXMLElement($xml_data);
	
	switch ($_GET['part']) {
		case 'config':
			$config_xml = $xml->addChild('config');
			$config_change = array(
				'board_title'			=> 'o_board_title',
				'announcement_enable'	=> 'o_announcement',
				'announcement_text'		=> 'o_announcement_message',
				'online_timeout'		=> 'o_timeout_online',
				'sig_max_length'		=> 'p_sig_length',
				'sig_max_lines'		=> 'p_sig_lines',
				'addl_header_links'		=> 'o_additional_navlinks',
				'default_user_group'	=> 'o_default_user_group',
				'topics_per_page'		=> 'o_disp_topics_default',
				'posts_per_page'		=> 'o_disp_posts_default',
				'verify_registrations'	=> 'o_regs_verify',
				'avatars'				=> 'o_avatars',
				'admin_email'			=> 'o_admin_email',
				'maintenance_message'	=> 'o_maintenance_message',
				'rules'				=> 'o_rules_message'
			);
			$config_auto = array(
				'show_post_count'		=> '0',
				'sig_max_height'		=> '0',
				'default_language'		=> 'English',
				'censoring'			=> '',
				'maintenance'			=> '0',
				'footer_text'			=> '',
				'turn_on_maint'		=> '0',
				'turn_off_maint'		=> '0',
				'allow_privatemsg'		=> '0',
			);
			foreach ($config_change as $key => $val) {
				$cfgset = $config_xml->addChild('cfgset');
				$cfgset->addChild('c_name', $key);
				$cfgset->addChild('c_value', $pun_config[$val]);
			}
			foreach ($config_auto as $key => $val) {
				$cfgset = $config_xml->addChild('cfgset');
				$cfgset->addChild('c_name', $key);
				$cfgset->addChild('c_value', $val);
			}
			save_xml($xml);
			redirect('converter.php?genfile&part=users', 'Configuration completed');
			break;
		case 'users':
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'users WHERE username<>\'Guest\'') or error('Failed to get users', __FILE__, __LINE__, $db->error());
			$users_xml = $xml->addChild('users');
			while ($cur_user = $db->fetch_assoc($result)) {
				$user_xml = $users_xml->addChild('user');
				$user_xml->addChild('id', $cur_user['id']);
				$user_xml->addChild('username', $cur_user['username']);
				$user_xml->addChild('password', $cur_user['password']);
				$user_xml->addChild('email', $cur_user['email']);
				$user_xml->addChild('registered', $cur_user['registered']);
				$user_xml->addChild('registration_ip', $cur_user['registration_ip']);
				$user_xml->addChild('group_id', $cur_user['group_id']);
				$user_xml->addChild('signature', $cur_user['signature']);
				$user_xml->addChild('timezone', $cur_user['timezone']);
			}
			save_xml($xml);
			redirect('converter.php?genfile&part=user_groups', 'Users completed');
			break;
		case 'user_groups':
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'groups') or error('Failed to get user groups', __FILE__, __LINE__, $db->error());
			$user_groups_xml = $xml->addChild('user_groups');
			while ($cur_group = $db->fetch_assoc($result)) {
				$group_xml = $user_groups_xml->addChild('group');
				$group_xml->addChild('g_id', $cur_group['g_id']);
				$group_xml->addChild('g_permanent', ($cur_group['g_id'] <= 4));
				$group_xml->addChild('g_guest_group', ($cur_group['g_id'] == 3));
				$group_xml->addChild('g_name', $cur_group['g_title']);
				$group_xml->addChild('g_title', $cur_group['g_user_title']);
				$group_xml->addChild('g_admin_privs', ($cur_group['g_id'] == 1));
				$group_xml->addChild('g_mod_privs', $cur_group['g_moderator']);
				$group_xml->addChild('g_edit_posts', $cur_group['g_edit_posts']);
				$group_xml->addChild('g_delete_posts', $cur_group['g_delete_posts']);
				$group_xml->addChild('g_signature', $pun_config['o_signatures']);
				$group_xml->addChild('g_user_list', $cur_group['g_view_users']);
				$group_xml->addChild('g_post_flood', $cur_group['g_post_flood']);
				$group_xml->addChild('g_post_links', $cur_group['g_post_links']);
				$group_xml->addChild('g_post_images', $cur_group['g_post_links']);
			}
			save_xml($xml);
			redirect('converter.php?genfile&part=topics', 'User groups completed');
			break;
		case 'topics':
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'topics') or error('Failed to get topics', __FILE__, __LINE__, $db->error());
			$topics_xml = $xml->addChild('topics');
			while ($cur_topic = $db->fetch_assoc($result)) {
				$topic_xml = $topics_xml->addChild('topic');
				$topic_xml->addChild('id', $cur_topic['id']);
				$topic_xml->addChild('subject', $cur_topic['subject']);
				$topic_xml->addChild('forum_id', $cur_topic['forum_id']);
				$topic_xml->addChild('first_post_id', $cur_topic['first_post_id']);
				$topic_xml->addChild('closed', $cur_topic['closed']);
				$topic_xml->addChild('sticky', $cur_topic['sticky']);
				if ($cur_topic['moved_to'] != null) {
					$topic_xml->addChild('redirect_id', $cur_topic['moved_to']);
					$topic_xml->addChild('show_redirect', '1');
				}
				$topic_xml->addChild('num_replies', $cur_topic['num_replies']);
			}
			save_xml($xml);
			redirect('converter.php?genfile&part=posts', 'Topics completed');
			break;
		case 'posts':
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'posts') or error('Failed to get posts', __FILE__, __LINE__, $db->error());
			$posts_xml = $xml->addChild('posts');
			while ($cur_post = $db->fetch_assoc($result)) {
				$post_xml = $posts_xml->addChild('post');
				$post_xml->addChild('id', $cur_post['id']);
				$post_xml->addChild('poster', $cur_post['poster_id']);
				$post_xml->addChild('poster_ip', $cur_post['poster_ip']);
				$post_xml->addChild('content', $cur_post['message']);
				$post_xml->addChild('posted', $cur_post['posted']);
				$post_xml->addChild('topic_id', $cur_post['topic_id']);
				if ($cur_post['edited']) {
					$post_xml->addChild('last_edited', $cur_post['edited']);
					$post_xml->addChild('last_edited_by', $cur_post['edited_by']);
				}
				$post_xml->addChild('disable_smilies', $cur_post['hide_smilies']);
			}
			save_xml($xml);
			redirect('converter.php?genfile&part=forums', 'Posts completed');
			break;
		case 'forums':
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'forums') or error('Failed to get forums', __FILE__, __LINE__, $db->error());
			$forums_xml = $xml->addChild('forums');
			while ($cur_forum = $db->fetch_assoc($result)) {
				$forum_xml = $forums_xml->addChild('forum');
				$forum_xml->addChild('id', $cur_forum['id']);
				$forum_xml->addChild('name', $cur_forum['forum_name']);
				$forum_xml->addChild('cat_id', $cur_forum['cat_id']);
				$forum_xml->addChild('sort_position', $cur_forum['disp_position']);
				$forum_xml->addChild('description', $cur_forum['forum_desc']);
			}
			
			$result = $db->query('SELECT * FROM ' . $db->prefix . 'categories') or error('Failed to get categories', __FILE__, __LINE__, $db->error());
			$categories_xml = $xml->addChild('categories');
			while ($cur_category = $db->fetch_assoc($result)) {
				$category_xml = $categories_xml->addChild('category');
				$category_xml->addChild('id', $cur_category['id']);
				$category_xml->addChild('name', $cur_category['cat_name']);
				$category_xml->addChild('sort_position', $cur_category['disp_position']);
			}
			save_xml($xml);
			redirect('converter.php?completed', 'Posts completed');
			break;
		default:
			message('Invalid data given');
	}
} else {
	if (file_exists(FORUM_CACHE_DIR . 'converter.xml')) {
		unlink(FORUM_CACHE_DIR . 'converter.xml');
	}
	?>
	<h2>FutureBB Converter</h2>
	<p>This page will convert your forum database to a file that you can import into FutureBB. This will allow you to easily transition between the two forum systems.</p>
	<form action="converter.php" method="get">
		<p><input type="hidden" name="part" value="config" /><input type="submit" name="genfile" value="Start &rarr;" /></p>
	</form>
	<?php
}

include PUN_ROOT.'footer.php';