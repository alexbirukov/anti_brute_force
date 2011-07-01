<?php
	/*
	Copyight: Solutions Nitriques 2011
	License: MIT, see the LICENCE file
	*/

	if(!defined("__IN_SYMPHONY__")) die("<h2>Error</h2><p>You cannot directly access this file</p>");

	// facade
	require_once(EXTENSIONS . '/anti_brute_force/lib/class.ABF.php');

	/**
	 *
	 * Anti Brute Force Decorator/Extension
	 * @author nicolasbrassard
	 *
	 */
	class extension_anti_brute_force extends Extension {

		/**
		 * Name of the extension
		 * @var string
		 */
		const EXT_NAME = 'Anti Brute Force';

		/**
		 * Key of the length setting
		 * @var string
		 */
		const SETTING_LENGTH = 'length';

		/**
		 * Key of the failed count setting
		 * @var string
		 */
		const SETTING_FAILED_COUNT = 'failed-count';

		/**
		 * Key of the group of setting
		 * @var string
		 */
		const SETTING_GROUP = 'force-domain';

		/**
		 * private variable for holding the errors encountered when saving
		 * @var array
		 */
		protected $errors = array();

		/**
		 * Credits for the extension
		 */
		public function about() {
			return array(
				'name'			=> self::EXT_NAME,
				'version'		=> '1.0',
				'release-date'	=> '2011-07-01',
				'author'		=> array(
					'name'			=> 'Solutions Nitriques',
					'website'		=> 'http://www.nitriques.com/open-source/',
					'email'			=> 'open-source (at) nitriques.com'
				),
				'description'	=> __('Secure your backend login page against brute force attacks'),
				'compatibility' => array(
					'2.2.1' => true,
					'2.2' => true
				)
	 		);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/login/',
					'delegate' => 'AuthorLoginFailure',
					'callback' => 'authorLoginFailure'
				),
				array(
					'page' => '/login/',
					'delegate' => 'AuthorLoginSuccess',
					'callback' => 'authorLoginSuccess'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'addCustomPreferenceFieldsets'
				),
				array(
					'page'      => '/system/preferences/',
					'delegate'  => 'Save',
					'callback'  => 'save'
				)
			);
		}

		public function authorLoginFailure($context) {
			var_dump($context);
			die();
			ABF::instance()->registerFailure();
		}

		public function authorLoginSuccess($context) {
			ABF::instance()->unregisterFailure();
			ABF::instance()->removeExpiredEntries();
		}

		public function install() {
			$intalled = ABF::instance()->install();

			if ($intalled) {
				// set default values
				$default_values = array(
					'settings' => array (
						self::SETTING_GROUP => array (
							self::SETTING_LENGTH => 60,
							self::SETTING_FAILED_COUNT => 5
						)
					)
				);
				$this->save($default_values);
			}

			return $intalled;
		}

		public function update($previousVersion) {
			$about = $this->about();
			return ABF::instance()->update($previousVersion,$about['version']);
		}

		public function uninstall() {
			return ABF::instance()->uninstall();
		}

		/**
		 * Delegate handle that adds Custom Preference Fieldsets
		 * @param string $page
		 * @param array $context
		 */
		public function addCustomPreferenceFieldsets($context) {
			// creates the field set
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', self::EXT_NAME));

			// create a paragraph for short intructions
			$p = new XMLElement('p', __('Define here when and how IP are blocked'), array('class' => 'help'));

			// append intro paragraph
			$fieldset->appendChild($p);

			// create a wrapper
			$wrapper = new XMLElement('div');
			$wrapper->setAttribute('class', 'group');

			// error wrapper
			$err_wrapper = new XMLElement('div');

			// append labels to field set
			$wrapper->appendChild($this->generateField(self::SETTING_FAILED_COUNT, 'Fail count limit'));
			$wrapper->appendChild($this->generateField(self::SETTING_LENGTH, 'Blocked length <em>in minutes</em>'));

			// append field before errors
			$err_wrapper->appendChild($wrapper);

			// error management
			if (count($this->errors) > 0) {
				// set css and anchor
				$err_wrapper->setAttribute('class', 'invalid');
				$err_wrapper->setAttribute('id', 'error');

				foreach ($this->errors as $error) {
					// adds error message
					$err = new XMLElement('p', $error);

					// append to $wrapper
					$err_wrapper->appendChild($err);
				}
			}

			// wrapper into fieldset
			$fieldset->appendChild($err_wrapper);

			// adds the field set to the wrapper
			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Quick utility function to make a input field+label
		 * @param string $settingName
		 * @param string $textKey
		 */
		public function generateField($settingName, $textKey) {
			// create the label and the input field
			$label = Widget::Label();
			$input = Widget::Input(
						'settings[' . self::SETTING_GROUP . '][' . $settingName .']',
						self::getConfigVal($settingName),
						'text'
					);

			// set the input into the label
			$label->setValue(__($textKey). ' ' . $input->generate());

			return $label;
		}
		private static function getConfigVal($key) {
			return Symphony::Configuration()->get($key, self::SETTING_GROUP);
		}

		/**
		 * Delegate handle that saves the preferences
		 * @param array $context
		 */
		public function save($context){
			$this->saveOne($context, self::SETTING_LENGTH);
			$this->saveOne($context, self::SETTING_FAILED_COUNT);
		}

		/**
		 *
		 * Save one parameter
		 * @param array $context
		 * @param string $key
		 */
		public function saveOne($context, $key){
			// get the input
			$input = $context['settings'][self::SETTING_GROUP][$key];
			$iVal = intval($input);

			// verify it is a good domain
			if (strlen($input) > 0 && is_int($iVal) && $iVal > 0) {

				// set config                    (name, value, group)
				Symphony::Configuration()->set($key, $iVal, self::SETTING_GROUP);

				// save it
				Administration::instance()->saveConfig();

			} else {
				// don't save

				// set error message
				$error = __('"%s" is not a valid positive integer',  array($input));
			 	array_push($this->errors, $error);

				//echo $error;die;

				// add an error into the stack
				$context['errors'][self::SETTING_GROUP][$key] = $error;
			}
		}

	}

?>