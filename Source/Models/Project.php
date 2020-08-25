<?php

namespace ILAB\Namespacer\Models;

class Project {
	/** @var string */
	private $sourcePath;

	/** @var array  */
	private $packages = [];

	/** @var array  */
	private $packageTableData = [];

	private $config = [];

	public function __construct(string $sourcePath) {
		$this->sourcePath = trailingslashit($sourcePath);

		$composerLock = $sourcePath.'composer.lock';
		if (!file_exists($composerLock)) {
			throw new \Exception("Composer lock file missing.");
		}

		$lockConfig = json_decode(file_get_contents($composerLock), true);
		if (!isset($lockConfig['packages'])) {
			throw new \Exception("No installed packages.");
		}

		foreach($lockConfig['packages'] as $package) {
			$packageName = $package['name'];
			$version = $package['version'];
			$path = trailingslashit($sourcePath.'vendor/'.$packageName);

			$package = new Package($packageName, $path, $version);
			$this->packages[$packageName] = $package;
			$this->packageTableData[] = [$packageName, $version];
		}

		$composerFile = $sourcePath.'composer.json';
		if (!file_exists($composerFile)) {
			throw new \Exception("Composer file missing.");
		}

		$this->config = json_decode(file_get_contents($composerFile), true);
	}

	/**
	 * @return array
	 */
	public function getPackages(): array {
		return $this->packages;
	}

	/**
	 * @return array
	 */
	public function getPackageTableData(): array {
		return $this->packageTableData;
	}

	public function addRepository(string $packageName) {
		$this->config['repositories'][] = [
			'type' => 'path',
			'url' => '../library/'.$packageName,
			'options' => [
				'symlink' => false
			]
		];
	}

	public function save(string $savePath, string $packagePrefix) {
		$composerFile = trailingslashit($savePath).'composer.json';

		foreach($this->packages as $packageName => $package) {
			$this->addRepository($packageName);
		}

		if (isset($this->config['require'])) {
			foreach($this->config['require'] as $packageName => $version) {
				if (strpos($packageName, $packagePrefix.'-') === 0) {
					continue;
				}

				if (strpos($packageName, 'ext-') === 0) {
					continue;
				}

				if (strpos($packageName, 'lib-') === 0) {
					continue;
				}

				if ($packageName === 'php') {
					continue;
				}

				if ($packageName === 'composer-plugin-api') {
					unset($this->config['require'][$packageName]);
					continue;
				}

				if (!isset($this->packages[$packageName])) {
					continue;
				}

				$package = $this->packages[$packageName];
				unset($this->config['require'][$packageName]);
				$this->config['require'][$packagePrefix.'-'.$packageName] = $package->getVersion();
			}
		}

		file_put_contents($composerFile, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
}