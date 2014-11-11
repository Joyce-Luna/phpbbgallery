<?php

/**
*
* @package phpBB Gallery Core
* @copyright (c) 2014 nickvergessen
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbgallery\core\controller;

class moderate
{
	/* @var \phpbb\auth\auth */
	protected $auth;

	/* @var \phpbb\config\config */
	protected $config;

	/* @var \phpbb\db\driver\driver */
	protected $db;

	/* @var \phpbb\request\request */
	protected $request;

	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbbgallery\core\album\display */
	protected $display;

	/* @var string */
	protected $root_path;

	/* @var string */
	protected $php_ext;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth			$auth		Auth object
	* @param \phpbb\config\config		$config		Config object
	* @param \phpbb\db\driver\driver	$db			Database object
	* @param \phpbb\request\request		$request	Request object
	* @param \phpbb\template\template	$template	Template object
	* @param \phpbb\user				$user		User object
	* @param \phpbb\controller\helper	$helper		Controller helper object
	* @param \phpbbgallery\core\album\display	$display	Albums display object
	* @param string						$root_path	Root path
	* @param string						$php_ext	php file extension
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, 
	\phpbb\template\template $template, \phpbb\user $user, \phpbb\controller\helper $helper, \phpbbgallery\core\album\display $display, \phpbbgallery\core\moderate $moderate, 
	\phpbbgallery\core\auth\auth $gallery_auth, \phpbbgallery\core\misc $misc, \phpbbgallery\core\album\album $album, \phpbbgallery\core\image\image $image,
	$root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->display = $display;
		$this->moderate = $moderate;
		$this->gallery_auth = $gallery_auth;
		$this->misc = $misc;
		$this->album = $album;
		$this->image = $image;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Index Controller
	*	Route: gallery/modarate
	*
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function base()
	{
		$this->gallery_auth->load_user_premissions($this->user->data['user_id']);
		$album_backlink = append_sid('/gallery');
		$album_loginlink = append_sid('/ucp.php?mode=login');
		if (!$this->gallery_auth->acl_check_global('m_'))
		{
			$this->misc->not_authorised($album_backlink, $album_loginlink, 'LOGIN_EXPLAIN_UPLOAD');
		}
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery_mcp'));
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery'));
		$this->display->display_albums(false, $this->config['load_moderators']);
		// This is the overview page, so we will need to create some queries
		// We will use the special moderate helper
		
		$this->moderate->build_queue('short', 'report_image_open');
		$this->moderate->build_queue('short', 'image_waiting');

		return $this->helper->render('gallery/moderate_overview.html', $this->user->lang('GALLERY'));
	}
	
	/**
	* Index Controller
	*	Route: gallery/modarate/image/{image_id}
	*
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function image($image_id)
	{
		$quick_action = $this->request->variable('action', '');

		// If we have quick mode (EDIT, DELETE) just send us to the page we need
		switch($quick_action)
		{
			case 'image_edit':
				redirect('gallery/image/' . $image_id . '/edit');
			break;
			case 'images_delete':
				redirect('gallery/image/' . $image_id . '/delete');
			break;
			case 'images_approve':
				redirect('gallery/moderate/image/' . $image_id . '/approve');
			break;
		}
		
		return $this->helper->render('gallery/moderate_overview.html', $this->user->lang('GALLERY'));
	}

	/**
	* Index Controller
	*	Route: gallery/modarate/image/{image_id}/approve
	*
	* @return Symfony\Component\HttpFoundation\Response A Symfony Response object
	*/
	public function approve($image_id)
	{
		$image_data = $this->image->get_image_data($image_id);
		$album_data = $this->album->get_info($image_data['image_album_id']);
		
		$album_backlink = append_sid('/gallery');
		$image_backlink = append_sid('/gallery/image/' . $image_id);
		$album_loginlink = append_sid('/ucp.php?mode=login');
		$meta_refresh_time = 3;
		$this->gallery_auth->load_user_premissions($this->user->data['user_id']);
		if (!$this->gallery_auth->acl_check('m_status', $image_data['image_album_id'], $album_data))
		{
			$this->misc->not_authorised($album_backlink, $album_loginlink, 'LOGIN_EXPLAIN_UPLOAD');
		}
		$action_ary = $this->request->variable('action', array('' => 0));
		list($action, ) = each($action_ary);

		if ($action == 'disapprove')
		{
			redirect('gallery/image/' . $image_id . '/delete');
		}
		$show_notify = true;
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery_mcp'));
		$this->user->add_lang_ext('phpbbgallery/core', array('gallery'));
		$this->user->add_lang('mcp');
			if (confirm_box(true))
			{
				$np = $this->request->variable('notify_poster', '');
				$notify_poster = ($action == 'approve' && $np);
				$image_id_ary = array($image_id);
				$this->image->approve_image($image_id_ary, $album_data['album_id']);
				// To DO - add notification
				$message = sprintf($this->user->lang['WAITING_APPROVED_IMAGE'][1]);
				meta_refresh($meta_refresh_time, $image_backlink);
				trigger_error($message);
			}
			else
			{
				$this->template->assign_vars(array(
					'S_NOTIFY_POSTER'			=> $show_notify,
					'S_' . strtoupper($action)	=> true,
					'S_CONFIRM_ACTION'	=> $this->helper->route('phpbbgallery_moderate_image_approve', array('image_id' => $image_id)),
				));
				$action_msg = $this->user->lang['QUEUES_A_APPROVE2_CONFIRM'];
				$s_hidden_fields = build_hidden_fields(array(
					'action'		=> 'approve',
				));
				confirm_box(false, $action_msg, $s_hidden_fields, 'mcp_approve.html');
			}

		return $this->helper->render('gallery/moderate_overview.html', $this->user->lang('GALLERY'));
	}
}