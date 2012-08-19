<?php

/**
 * Rah_pathway plugin for Textpattern CMS.
 *
 * @author Jukka Svahn
 * @date 2012-
 * @license GNU GPLv2
 * @link https://github.com/gocom/rah_pathway
 * 
 * Copyright (C) 2012 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	new rah_pathway();

class rah_pathway {
	
	/**
	 * @var string Used article field
	 */
	
	public $field = 'custom_7';
	
	/**
	 * @var string Requested URL
	 */
	
	private $page_uri;
	
	/**
	 * @var array URL of the real URL params
	 */
	
	private $makeout;

	/**
	 * Constructor
	 */
	
	public function __construct() {
		$this->page_uri = trim(serverSet('REQUEST_URI'), '/');

		register_callback(array($this, 'route'), 'pretext');
		register_callback(array($this, 'restrict'), 'pretext_end');
		register_callback(array($this, 'permlink_handler'), 'pretext_end');
		register_callback(array($this, 'sweep'), 'pretext_end');
		register_callback(array($this, 'sanitize_url'), 'sanitize_for_url');
		
		if(txpinterface == 'admin') {
			global $event;
			register_callback(array($this, 'permlink_handler'), $event, '', 1);
		}
	}
	
	/**
	 * Route
	 */
	
	public function route() {
	
		foreach(array('id') as $name) {
			
			if(isset($_POST[$name])) {
				$this->makeout[$name] = $_POST[$name];
			}
			
			else if(isset($_GET[$name])) {
				$this->makeout[$name] = $_GET[$name];
			}
			
			else {
				$this->makeout[$name] = null;
			}
		}
	
		if(!$this->page_uri) {
			return;
		}
		
		$id = 
			safe_field(
				'ID',
				'textpattern',
				$this->field."='".doSlash($this->page_uri)."' limit 1"
			);
		
		if(!$id) {
			return;
		}
		
		$_POST['id'] = $_GET['id'] = $id;
	}
	
	/**
	 * Sweet modifications from the memory
	 */
	
	public function sweep() {
		foreach($this->makeout as $name => $value) {
			
			if($value === null) {
				unset($_GET[$name], $_POST[$name]);
			}
			
			else {
				$_POST[$name] = $_GET[$name] = $value;
			}
		}
	}
	
	/**
	 * Register permlink handler
	 */
	
	public function permlink_handler() {
		global $prefs;
		$prefs['custom_url_func'] = array($this, 'permlink');
	}
	
	/**
	 * Sanitizer, allow free URLs
	 */
	
	public function sanitize_url($ent, $step, $pre, $url) {
		global $event;
		
		if($this->field === 'url_title' && $event === 'article') {
			return $url;
		}
		
		return '';
	}

	/**
	 * Form new permlinks
	 */
	
	public function permlink($data) {
		
		if(empty($data['thisid']) || empty($data['url_title'])) {
			return false;
		}
		
		if(isset($data[$this->field])) {
			$page_url = $data[$this->field];
		}
		
		else {
			$page_url = 
				@safe_field(
					$this->field,
					'textpattern',
					'ID='.intval($data['thisid']).' limit 1'
				);
		}
		
		if(!$page_url) {
			return false;
		}
		
		return hu.$page_url;
	}
	
	/**
	 * Restrict access to the real permlink
	 */
	
	public function restrict() {
		// Todo: chunk URLs to a relative state correctly.
	}
}

?>