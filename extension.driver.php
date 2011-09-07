<?php
	class Extension_WebHooks extends Extension {
		private $about;
		public function __construct() {
			$this->about = array(
				'name'         => 'WebHooks',
				'version'      => '0.0.1',
				'release-date' => '2011-09-01',
				'dependencies' => array(
					'pager' => '1.0.0'
				),
				'author'       => array(
					'name'	  => 'Wilhelm Murdoch',
					'website' => 'http://thedrunkenepic.com/',
					'email'	  => 'wilhelm.murdoch@gmail.com'
				)
			);
		}

		public function about() {
			return $this->about;
		}

		public function install() {
			return Symphony::Database()->import("
				DROP TABLE IF EXISTS `sym_extensions_webhooks`;
				CREATE TABLE `sym_extensions_webhooks` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`label` varchar(64) DEFAULT NULL,
					`section_id` int(11) DEFAULT NULL,
					`event` enum('POST','PUT','DELETE') DEFAULT NULL,
					`url` varchar(256) DEFAULT NULL,
					`is_active` tinyint(1) DEFAULT 1,
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
			");
		}

		public function uninstall() {
			return Symphony::Database()->import("
				DROP TABLE IF EXISTS `sym_extensions_webhooks`
			");
		}

		public function fetchNavigation() {
			return array(
				array(
					'location' => __('System'),
					'name' => __($this->about['name']),
					'link' => '/hooks/'
				)
			);
		}

		public function getSubscribedDelegates() {
			return array();
		}
	}