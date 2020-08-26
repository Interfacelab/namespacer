<?php

namespace ILAB\Namespacer\Models;

class Configuration {
	private $config = [];

	public function __construct(string $configFile = null) {
		if (!empty($configFile) && file_exists($configFile)) {
			$this->config = include $configFile;
		}
	}

	public function prepare(string $package, array $config, string $path, string $namespacePrefix) {
		if (isset($this->config['prepare'])) {
			foreach($this->config['prepare'] as $func) {
				$config = call_user_func($func, $package, $config, $path, $namespacePrefix);
			}
		}

		return $config;
	}

	public function start(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
		if (isset($this->config['start'])) {
			foreach($this->config['start'] as $func) {
				$source = call_user_func($func, $source, $currentNamespace, $namespacePrefix, $package, $file);
			}
		}

		return $source;
	}

	public function before(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
		if (isset($this->config['before'])) {
			foreach($this->config['before'] as $func) {
				$source = call_user_func($func, $source, $namespace, $currentNamespace, $namespacePrefix, $package, $file);
			}
		}

		return $source;
	}

	public function after(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
		if (isset($this->config['after'])) {
			foreach($this->config['after'] as $func) {
				$source = call_user_func($func, $source, $namespace, $currentNamespace, $namespacePrefix, $package, $file);
			}
		}

		return $source;
	}

	public function end(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
		if (isset($this->config['end'])) {
			foreach($this->config['end'] as $func) {
				$source = call_user_func($func, $source, $currentNamespace, $namespacePrefix, $package, $file);
			}
		}

		return $source;
	}
}