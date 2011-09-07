<?php
	require_once TOOLKIT.'/class.administrationpage.php';
	require_once TOOLKIT.'/class.sectionmanager.php';

	class ContentExtensionWebhooksHooks extends AdministrationPage {
		private $sectionNamesArray;
		public function __construct(Administration &$parent) {
			parent::__construct($parent);

			$SectionManager = new SectionManager($this->_Parent);

			$this->sectionNamesArray = array();
			foreach($SectionManager->fetch(NULL, 'ASC', 'sortorder') as $Section) {
				$this->sectionNamesArray[$Section->get('id')] = $Section->get('name');
			}
		}
		public function __viewIndex() {
			if(Symphony::ExtensionManager()->fetchStatus('pager') === EXTENSION_ENABLED) {
				require_once EXTENSIONS.'/pager/lib/class.pager.php';
			} else {
				$this->pageAlert(
					__(
						'WebHooks cannot be used without the `Pager` extension. Either enable it or download it from<a href="%1$s" accesskey="c">GitHub</a>.',
						array(
							'https://github.com/wilhelm-murdoch/pager'
						)
					),
					Alert::ERROR
				);
				return;
			}

			$this->setPageType('table');
			$this->setTitle(__(
				'%1$s &ndash; %2$s',
				array(
					__('Symphony'),
					__('WebHooks')
				)
			));

			$this->appendSubheading(
				__('WebHooks'), 
				Widget::Anchor(
					__('Create New'), 
					Administration::instance()->getCurrentPageURL().'new/', 
					__('Create a new webhook'), 
					'create button', 
					NULL, 
					array('accesskey' => 'c')
				)
			);

			$WebHookTableHead = array(
					array(__('Label'),   'col'),
					array(__('Section'), 'col'),
					array(__('Event'),   'col'),
					array(__('Active'),  'col'),
					array(__('URL'),     'col')
			);

			$totalWebHooks = array_pop(Symphony::Database()->fetch("SELECT COUNT(1) AS count FROM `sym_extensions_webhooks`"));

			$Pager = Pager::factory($totalWebHooks['count'], Symphony::Configuration()->get('pagination_maximum_rows', 'symphony'), 'pg');

			$webHooks = Symphony::Database()->fetch('
				SELECT
					`id`,
					`label`,
					`section_id`,
					`event`,
					`url`,
					`is_active` 
				FROM `sym_extensions_webhooks`
				ORDER BY `id` DESC '.$Pager->getLimit(true)
			);

			$webHookTableBody = array();
			if(false == $webHooks) {
				$webHookTableBody[] = Widget::TableRow(array(
						Widget::TableData(__('None found.'), 'inactive', NULL, count($WebHookTableHead))
					)
				);
			} else foreach($webHooks as $webHook) {
				$labelRow = Widget::TableData(Widget::Anchor($webHook['label'], Administration::instance()->getCurrentPageURL()."edit/{$webHook['id']}"));
				$labelRow->appendChild(Widget::Input('items['.$webHook['id'].']', 'on', 'checkbox'));

				$webHookTableBody[] = Widget::TableRow(array(
						$labelRow,
						Widget::TableData($this->sectionNamesArray[$webHook['section_id']]),
						Widget::TableData($webHook['event']),
						Widget::TableData((bool) $webHook['is_active'] ? 'Yes' : 'No'),
						Widget::TableData(Widget::Anchor($webHook['url'], $webHook['url']))
					), 
					'odd'
				);
			}

			$webHookTable = Widget::Table(
				Widget::TableHead($WebHookTableHead),
				NULL,
				Widget::TableBody($webHookTableBody),
				'selectable'
			);

			$this->Form->appendChild($webHookTable);

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('disable', false, __('Disable')),
				array('enable', false, __('Enable')),
				array('delete', false, __('Delete'), 'confirm', null, array(
					'data-message' => __('Are you sure you want to delete the selected webhooks(s)')
				))
			);

			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$this->Form->appendChild($tableActions);
			$this->Form->appendChild($Pager->save());
		}

		public function __actionIndex() {
			if(false === isset($_POST['with-selected'])) {
				return;
			}

			foreach($_POST['items'] as $id => $state) {
				switch($_POST['with-selected']) {
					case 'enable':
						Symphony::Database()->update(array('is_active' => true), 'sym_extensions_webhooks', '`id` = '.(int) $id);
						break;
					case 'disable':
						Symphony::Database()->update(array('is_active' => false), 'sym_extensions_webhooks', '`id` = '.(int) $id);
						break;
					case 'delete':
						Symphony::Database()->delete('sym_extensions_webhooks', '`id` = '.(int) $id);
						break;
				}
			}
		}

		public function __actionNew() {
			require_once TOOLKIT.'/util.validators.php';
			$fields = $_POST['fields'];
			$this->_errors = array();

			if(false === isset($fields['label']) || trim($fields['label']) == '')
				$this->_errors['label'] = __('`Label` is a required field.');

			if(false === isset($this->sectionNamesArray[$fields['section_id']]))
				$this->_errors['section_id'] = __('`Section` is a required field.');

			if(false === isset($fields['url']) || false === preg_match($validators['URI'], $fields['url']) || false == trim($fields['url']))
				$this->_errors['url'] = __('`URL` is a required field and must be a valid address.');

			if(empty($this->_errors) && false === isset($fields['id'])) {
				$uniqueConstraintCheck = array_pop(Symphony::Database()->fetch("
					SELECT COUNT(1) AS count 
					FROM `sym_extensions_webhooks`
					WHERE
						    `section_id` = ".(int) $fields['section_id']."
						AND `event`      = '".trim($fields['event'])."'
						AND `url`        = '".trim($fields['url'])."'
				"));

				if($uniqueConstraintCheck['count']) {
					$this->_errors = array(
						'section_id' => __('Unique constraint violation.'),
						'event'      => __('Unique constraint violation.'),
						'url'        => __('Unique constraint violation.')
					);

					$this->pageAlert(
						__('The WebHook could not be saved. There has been a unique constraint violation. Please ensure you have a unique combination of `event`, `section` and `URL`.'),
						Alert::ERROR
					);
					return;
				}
			}

			if($this->_errors) {
				$this->pageAlert(
					__('The WebHook could not be saved. Please ensure you filled out the form properly.'),
					Alert::ERROR
				);
				return;
			}

			try {
				if(isset($fields['id'])) {
					Symphony::Database()->update(array(
						'label'      => General::sanitize($fields['label']),
						'section_id' => (int) $fields['section_id'],
						'event'      => $fields['event'],
						'url'        => General::sanitize($fields['url']),
						'is_active'  => isset($fields['is_active']) ? TRUE : FALSE
					), 'sym_extensions_webhooks', '`id` = '.(int) $fields['id']);
				} else {
					$id = Symphony::Database()->insert(array(
						'label'      => General::sanitize($fields['label']),
						'section_id' => (int) $fields['section_id'],
						'event'      => $fields['event'],
						'url'        => General::sanitize($fields['url']),
						'is_active'  => isset($fields['is_active']) ? TRUE : FALSE
					), 'sym_extensions_webhooks');
				}
			} catch(Exception $Exception) {
				$this->pageAlert(
					$Exception->getMessage(),
					Alert::ERROR
				);
				return;
			}

			if(isset($fields['id'])) {
				$this->pageAlert(
					__('WebHook has been updated successfully!'),
					Alert::SUCCESS
				);
				return;				
			}
			redirect(Administration::instance()->getCurrentPageURL()."/edit/{$id}/created/");
			return;
		}

		public function __viewNew(array $fields = array()) {
			if(false === empty($_POST) && false == $fields) $fields = $_POST['fields'];

			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('WebHooks'))));
			$this->appendSubheading(false === isset($fields['id']) ? __('Untitled') : $fields['label']);

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('WebHook Settings')));

			$label = Widget::Label(__('Label'));
			$label->appendChild(Widget::Input(
				'fields[label]', General::sanitize($fields['label'])
			));

			if(isset($this->_errors['label']))
				$label = $this->wrapFormElementWithError($label, $this->_errors['label']);

			$url = Widget::Label(__('URL'));
			$url->appendChild(Widget::Input(
				'fields[url]', General::sanitize($fields['url'])
			));

			if(isset($this->_errors['url']))
				$url = $this->wrapFormElementWithError($url, $this->_errors['url']);

			$options = array(array(NULL, false, __('Select One...')));
			foreach($this->sectionNamesArray as $id => $name) {
				$options[] = array($id, false, $name);
			}

			if(isset($fields['section_id'])) {
				foreach($options as &$option) {
					if($option[0] == $fields['section_id'])
						$option[1] = true;
				}
			}

			$section = Widget::Label(__('Target Section'));
			$section->appendChild(Widget::Select('fields[section_id]', $options));

			if(isset($this->_errors['section_id']))
				$section = $this->wrapFormElementWithError($section, $this->_errors['section_id']);

			$options = array(
				array('POST',   false, 'Add'), 
				array('PUT',    false, 'Edit'), 
				array('DELETE', false, 'Delete')
			);

			if(isset($fields['event'])) {
				foreach($options as &$option) {
					if($option[0] == $fields['event'])
						$option[1] = true;
				}
			}

			$event = Widget::Label(__('Event'));
			$event->appendChild(Widget::Select('fields[event]', $options));

			if(isset($this->_errors['event']))
				$event = $this->wrapFormElementWithError($event, $this->_errors['event']);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$group->appendChild($section);
			$group->appendChild($event);

			$isActive = Widget::Label();
			$isActiveCheckbox = Widget::Input('fields[is_active]', 'yes', 'checkbox');
			if(isset($fields['id']) || $fields['is_active'])
				$isActiveCheckbox->setAttribute('checked', 'checked');

			$isActive->setValue(__('%1$s Activate this WebHook', array($isActiveCheckbox->generate())));

			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			$actions->appendChild(Widget::Input(
				'action[save]', isset($fields['id']) ? __('Update WebHook') : __('Create WebHook'),
				'submit', array('accesskey' => 's')
			));

			if(isset($fields['id'])){
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this webhook'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to delete this webhook?')));
				$actions->appendChild($button);
			}

			$fieldset->appendChild($label);
			$fieldset->appendChild($group);
			$fieldset->appendChild($url);
			$fieldset->appendChild($isActive);
			$fieldset->appendChild($actions);

			if(isset($fields['id'])) {
				$fieldset->appendChild(Widget::Input('fields[id]', $fields['id'], 'hidden'));
			}

			$this->Form->appendChild($fieldset);
		}

		public function __actionEdit() {
			if(isset($_POST['action']['delete']) && isset($_POST['fields']['id'])) {
				$_POST['with-selected'] = 'delete';
				$_POST['items'] = array($_POST['fields']['id'] => '');
				return $this->__actionIndex();
			}
			return $this->__actionNew();
		}

		public function __viewEdit() {
			if(
				isset($this->_context[0]) && $this->_context[0] === 'edit' && 
				isset($this->_context[1]) && is_numeric($this->_context[1])
			) {
				$webHook = Symphony::Database()->fetch('
					SELECT
						`id`,
						`label`,
						`section_id`,
						`event`,
						`url`,
						`is_active` 
					FROM `sym_extensions_webhooks`
					WHERE `id` = '.(int) $this->_context[1]
				);

				if(false == $webHook) {
					$this->pageAlert(
						__('The WebHook you specified could not be located.'),
						Alert::ERROR
					);
					return $this->__viewIndex();
				}

				if(isset($this->_context[2]) && $this->_context[2] == 'created') {
					$this->pageAlert(
						__(
							'WebHook created at %1$s. <a href="%2$s" accesskey="c">Create another?</a> <a href="%3$s" accesskey="a">View all WebHooks</a>',
							array(
								DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__),
								SYMPHONY_URL . '/extension/webhooks/hooks/new/',
								SYMPHONY_URL . '/extension/webhooks/hooks/'
							)
						),
						Alert::SUCCESS
					);
				}

				return $this->__viewNew($webHook[0]);
			}
		}
	}