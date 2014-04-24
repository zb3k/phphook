<?php



class CmsHook {

	protected $version = '1.0.0';

	protected $driver;
	protected $installed = array();

	protected $config = array(
		'cms'            => '',
		'users'          => array('admin'),
		'base_path'      => '../',
		'cmshook_folder' => 'cmshook',
		'mods_folder'    => 'mods',
		'storage_folder' => 'storage',
	);

	protected $mod_config = array(
		'version'         => '1.0',
		'description'     => '',
		'author'          => '',
		'email'           => '',
		'site'            => '',
		'cms'             => '',
		'cms_version'     => '',
		'cmshook_version' => '1.0.*',
		'require'         => array(),
	);

	//-------------------------------------------------------------------------

	public function __construct(array $config = array()) {
		define('DS', DIRECTORY_SEPARATOR);

		$this->set_config($config);

		chdir($this->path('base'));


		// Load driver
		$driver_class = 'cmshook_' . $this->config('cms') . '_driver';
		$driver_file  = $this->path('app', 'drivers' . DS . $driver_class . '.php');
		if (file_exists($driver_file)) {
			require_once $driver_file;
		}
		if (!class_exists($driver_class)) {
			$this->error('Driver clss "%s" not found', $driver_class);
		}
		$this->driver = new $driver_class($this);


		// Check user
		if (!in_array($this->driver->get_current_user(), (array)$this->config('users'))) {
			$this->error('Access denied!');
		}

		$installed_file = $this->path('cmshook', 'installed.lock.php');
		if (file_exists($installed_file)) {
			$this->installed = unserialize(file_get_contents($installed_file));
		}
	}

	//-------------------------------------------------------------------------

	public function __destruct() {
		$installed_file = $this->path('cmshook', 'installed.lock.php');
		if ($this->installed) {
			file_put_contents($installed_file, serialize($this->installed));
		} elseif (file_exists($installed_file)) {
			unlink($installed_file);
		}
	}

	//-------------------------------------------------------------------------

	public function error($message) {
		$arguments = func_get_args();
		$message = call_user_func_array('sprintf', $arguments);

		die($this->make_view('fatal_error', array('message' => $message)));
	}

	//-------------------------------------------------------------------------

	public function set_config($config) {
		$config = array_merge($this->config, $config);

		$config['base_path']    = realpath($config['base_path']) . DS;
		$config['cmshook_path'] = realpath($config['base_path'] . $config['cmshook_folder']) . DS;
		$config['mods_path']    = realpath($config['cmshook_path'] . $config['mods_folder']) . DS;
		$config['storage_path'] = realpath($config['cmshook_path'] . $config['storage_folder']) . DS;

		// Check paths
		$paths = array('base_path', 'mods_path', 'storage_path', 'cmshook_path');
		foreach ($paths as $path_key) {
			if (!file_exists($config[$path_key]) || $config[$path_key] == DS) {
				$this->error('Incorrect path "%s": %s', $path_key, $config[$path_key]);
			}
		}

		$config['app_path'] = $config['cmshook_path'] . 'app' . DS;

		$this->config = $config;
	}

	//-------------------------------------------------------------------------

	public function config($key, $default = null) {
		return isset($this->config[$key]) ? $this->config[$key] : $default;
	}

	//-------------------------------------------------------------------------

	public function path($key, $file = '') {
		if ($path = $this->config($key . '_path')) {
			return $path . $file;
		}
	}

	//-------------------------------------------------------------------------

	public function get_version() {
		return $this->version;
	}

	//-------------------------------------------------------------------------

	public function get_modules($key = null) {
		static $modules;

		if ($modules === null) {
			$modules   = array();
			$mods_path = $this->path('mods');
			$base_path = $this->path('base');
			$files     = scandir($mods_path);

			foreach ($files as $f) {
				if ($f{0} == '.') continue;
				$mod_dir = $mods_path . $f . DS;
				if (is_dir($mod_dir) && file_exists($mod_dir.'config.php')) {
					$mod_config = array_merge($this->mod_config, (array)(include $mod_dir.'config.php'));

					$modules[$f]            = (object) $mod_config;
					$modules[$f]->path      = str_replace($base_path, '', $mod_dir);
					$modules[$f]->name      = $f;
					$modules[$f]->installed = isset($this->installed[$f]);
				}
			}
		}

		if ($key) {
			return isset($modules[$key]) ? $modules[$key] : false;
		}

		usort($modules, array($this, 'sort_modules'));

		return $modules;
	}

	//-------------------------------------------------------------------------

	protected function sort_modules($a, $b) {
		if ($a->installed == $b->installed) {
			return 0;
		}
		return ($a->installed > $b->installed) ? -1 : 1;
	}

	//-------------------------------------------------------------------------

	public function run() {
		$route         = !empty($_GET['route']) ? $_GET['route'] : 'index';
		$arguments     = explode('/', $route);
		$action        = array_shift($arguments);
		$action_method = 'action_' . $action;

		if (!method_exists($this, $action_method)) {
			$this->error('PAGE NOT FOUND');
		}

		echo call_user_func_array(array($this, $action_method), $arguments);
	}

	//-------------------------------------------------------------------------

	public function make_view($view, $data = array()) {
		$view_file = $this->path('app', "views/{$view}.view.php");
		if (!file_exists($view_file)) {
			die("View \"{$view}\" not found!");
		}
		if ($data) {
			extract($data);
		}

		ob_start();

		include $view_file;

		return ob_get_clean();
	}

	//-------------------------------------------------------------------------

	public function get_files($path, $relpath = '') {
		$result = array();

		if (file_exists($path) && is_dir($path)) {
			$path  = rtrim($path, DS) . DS;
			$files = scandir($path);
			foreach ($files as $f) {
				if ($f{0} != '.') {
					$file_path = $path . $f;
					if (is_dir($file_path)) {
						$result = array_merge($result, $this->get_files($file_path, $relpath . $f . DS));
					} else {
						$result[] = $relpath . $f;
					}
				}
			}
		}

		return $result;
	}

	/**************************************************************************
		ACTIONS
	**************************************************************************/

	public function action_index() {
		$all_modules        = $this->get_modules();
		$installed_mods     = array();
		$not_installed_mods = array();

		foreach ($all_modules as $name => $mod) {
			if ($mod->installed) {
				$installed_mods[$name] =& $all_modules[$name];
			} else {
				$not_installed_mods[$name] =& $all_modules[$name];
			}
		}

		$data = array(
			'version'            => $this->get_version(),
			'cms_name'           => $this->driver->get_name(),
			'cms_version'        => $this->driver->get_version(),
			'all_modules'        => $all_modules,
			'installed_mods'     => $installed_mods,
			'not_installed_mods' => $not_installed_mods,
		);

		return $this->make_view('modules_control', $data);
	}

	//-------------------------------------------------------------------------

	public function action_install($module_name) {
		$this->install_mod($module_name);

		$this->redirect('index');
	}

	//-------------------------------------------------------------------------

	public function action_uninstall($module_name) {
		$this->uninstall_mod($module_name);

		$this->redirect('index');
	}

	//-------------------------------------------------------------------------

	public function action_restore() {
		$this->restore();
		$this->redirect('index');
	}

	protected function redirect($route) {
		header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?route=' . $route);
	}

	/**************************************************************************
		PROCESS
	**************************************************************************/

	public function restore() {
		$storage_path = $this->path('storage');
		$files = $this->get_files($storage_path);
		foreach ($files as $f) {
			if ($f == '__cmshook__.php') continue;
			copy($storage_path.$f, $f);
		}
		$this->destroy_dir($storage_path);
		mkdir($storage_path);
		$this->installed = array();
	}

	//-------------------------------------------------------------------------

	protected function install_mod($module_name) {
		$module = $this->get_modules($module_name);

		if (!$module) {
			$this->error('Module not found');
		}

		if ($files = $this->get_files($module->path . 'hooks')) {
			foreach ($files as $f) {
				$this->make_hooks($module->path . 'hooks/' . $f);
			}
		}

		$this->installed_add($module_name, $module->version);
	}

	//-------------------------------------------------------------------------

	public function uninstall_mod($module_name) {
		$installed = $this->installed;
		unset($installed[$module_name]);

		$this->restore();

		foreach ($installed as $mod => $ver) {
			$this->install_mod($mod);
		}
	}

	//-------------------------------------------------------------------------

	protected function installed_add($module_name, $version) {
		$this->installed[$module_name] = $version;
	}

	//-------------------------------------------------------------------------

	protected function make_hooks($inject_file) {
		$hooks  = array();
		$buffer = '';
		$index  = 0;
		$file   = '';

		if ($fh = fopen($inject_file, 'r')) {
			while (($str = fgets($fh)) !== false) {
				if (substr($str, 0, 6) == '@SIMOD') {
					$parts = explode(' ', $str);
					$act   = strtolower(trim(trim($parts[1]), ':'));
					switch ($act) {
						case 'file':
							$file = trim($parts[2]);
							break;

						case 'end':
							$index++;
							break;

						default:
							if (isset($hooks[$index][$act])) {
								$index ++;
							}
							$hooks[$index]['file'] = $file;
							$buffer =& $hooks[$index][$act];
					}
				} else {
					$buffer .= $str;
				}
			}
			fclose($fh);
		}

		foreach ($hooks as $inject) {
			$file    = $inject['file'];
			$search  = isset($inject['find']) ? $inject['find'] : false;
			$replace = isset($inject['replace']) ? $inject['replace'] : false;

			if ($this->storage($file) && $search && $replace) {
				$content = file_get_contents($file);

				$search_regexp = preg_quote($search, '@');
				$search_regexp = preg_replace('/\n/', '(\r\n|\n|\r)', $search_regexp);
				// $search_regexp = preg_replace('/\t/', '\t', $search_regexp);
				$search_regexp = '@' . $search_regexp . '@um';

				$new_content = preg_replace($search_regexp, $replace, $content);

				if ($new_content == $content) {
					$this->error('Could not find the lines: <br><pre class="bg-primary">%s</pre>File: <code>%s</code>', htmlspecialchars($search), $file);
				}

				file_put_contents($file, $new_content);
			}
		}
	}

	//-------------------------------------------------------------------------

	protected function storage($file) {

		if (!file_exists($file)) {
			$this->error('File not found: ' . $file);
		}

		$storage_file = $this->path('storage', $file);

		if (!file_exists($storage_file)) {
			if (!$this->copy_file($file, $storage_file)) {
				$this->error('Could not copy file: %s', $file);
			}
		}

		return true;
	}

	//-------------------------------------------------------------------------

	protected function copy_file($src, $dest) {
		$dir = dirname($dest);

		if (!file_exists($dir) || !is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		return copy($src, $dest);
	}

	//-------------------------------------------------------------------------

	protected function destroy_dir($dir) {
		if (!is_dir($dir) || is_link($dir)) {
			return unlink($dir);
		}

		foreach (scandir($dir) as $file) {
			if ($file == '.' || $file == '..') continue;
			if (!$this->destroy_dir($dir . DS . $file)) {
				chmod($dir . DS . $file, 0777);
				if (!$this->destroy_dir($dir . DS . $file)) return false;
			}
		}

		return rmdir($dir);
	}
}