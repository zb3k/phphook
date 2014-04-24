<?php



class cmshook_simpla_driver {

	//-------------------------------------------------------------------------

	protected $cmshook;
	protected $name = 'Simpla';

	//-------------------------------------------------------------------------

	public function __construct($cmshook) {
		$this->cmshook = $cmshook;
	}

	//-------------------------------------------------------------------------

	public function get_name() {
		return $this->name;
	}

	//-------------------------------------------------------------------------

	public function get_current_user() {
		return $_SERVER['PHP_AUTH_USER'];
	}

	//-------------------------------------------------------------------------

	public function get_version() {
		static $version;

		if ($version === null) {
			require_once $this->cmshook->path('base', 'api/Config.php');

			$config  = new Config;
			$version = $config->version;
		}

		return $version;
	}

	//-------------------------------------------------------------------------
}