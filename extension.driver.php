<?php

	class Extension_WebHooks extends Extension {
		public function __construct(Array $args){
			parent::__construct($args);
		}

		public function about() {
			return array(
				'name' => 'WebHooks',
				'version' => '0.0.1',
				'release-date' => '2011-09-01',
				'author' => array(
					'name' => 'Wilhelm Murdoch',
					'website' => 'http://thedrunkenepic.com/',
					'email' => 'wilhelm.murdoch@gmail.com'
				)
			);
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
					'name' => __('WebHooks'),
					'link' => '/hooks/'
				)
			);
		}

		public static function baseURL(){
			return SYMPHONY_URL . '/extension/webhooks/hooks';
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => '__pushNotification'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => '__pushNotification'
				),
				array(
					'page' => '/publish/',
					'delegate' => 'Delete',
					'callback' => '__pushNotification'
				),
			);
		}

		public function __pushNotification(array $context) {
			switch($context['delegate']) {
				case 'EntryPostCreate':
				case 'EntryPostEdit':
				case 'Delete':
				default:
					//return;
			}
			echo '<pre>';
			print_r($context['section']->fetchFieldsSchema());
			print_r($context['entry']->getData());
			exit();
		}
	}