<?php

namespace ILAB\Namespacer\Models;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Package {
	/** @var string  */
	private $name;

	/** @var string  */
	private $path;

	/** @var string */
	private $version;

	/** @var array  */
	private $namespaces = [];

	/** @var array  */
	private $sourceFiles = [];

	/** @var null|string  */
	private $outputPath = null;

	public function __construct(string $name, string $path, string $version) {
		$this->name = $name;
		$this->path = trailingslashit($path);
		$this->version = $version;
	}

	//region Properties

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return string
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * @return array
	 */
	public function getNamespaces(): array {
		return $this->namespaces;
	}

	/**
	 * @return array
	 */
	public function getSourceFiles(): array {
		return $this->sourceFiles;
	}

	//endregion

	//region Processing

	/**
	 * @param string $packagePrefix
	 * @param string $namespacePrefix
	 * @param string $outputPath
	 * @param Package[] $packages
	 */
	public function process(string $packagePrefix, string $namespacePrefix, string $outputPath, array $packages) {
		$outputPath = trailingslashit($outputPath);
		$this->outputPath = $outputPath;

		$composerFile = $this->path.'composer.json';
		if (!file_exists($composerFile)) {
			throw new \Exception("Missing composer.json for package {$this->name}.");
		}

		$config = json_decode(file_get_contents($composerFile), true);

		$config['name'] = $packagePrefix.'-'.$config['name'];
		$config['version'] = $this->getVersion();

		if (isset($config['require'])) {
			foreach($config['require'] as $packageName => $version) {
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
					unset($config['require'][$packageName]);
					continue;
				}

				if ($packageName === 'kylekatarnls/update-helper') {
					unset($config['require'][$packageName]);
					continue;
				}

				if (!isset($packages[$packageName])) {
					throw new \Exception("Cannot find related package $packageName for {$this->name}.");
				}

				$package = $packages[$packageName];
				unset($config['require'][$packageName]);
				$config['require'][$packagePrefix.'-'.$packageName] = $package->getVersion();
			}
		}

		if (isset($config['autoload'])) {
			if (isset($config['autoload']['psr-0'])) {
				foreach($config['autoload']['psr-0'] as $namespace => $directory) {
					$tempPsr0Path = $this->path.'tmp/';
					$psr0Path = trailingslashit($this->path.$directory);
					mkdir($tempPsr0Path, 0755, true);
					`mv {$psr0Path}* $tempPsr0Path`;

					$namespacePath = ltrim(str_replace("\\", "/", $namespacePrefix), '\\');
					$newPsr0Path = trailingslashit(trailingslashit($this->path.$directory).$namespacePath);
					mkdir($newPsr0Path, 0755, true);
					`mv {$tempPsr0Path}* $newPsr0Path`;
					@rmdir($tempPsr0Path);

					$config['autoload']['psr-0'][$namespacePrefix.$namespace] = $directory;
					unset($config['autoload']['psr-0'][$namespace]);
				}
			}

			if (isset($config['autoload']['psr-4'])) {
				foreach($config['autoload']['psr-4'] as $namespace => $directory) {
					$config['autoload']['psr-4'][$namespacePrefix.$namespace] = $directory;
					unset($config['autoload']['psr-4'][$namespace]);
				}
			}
		}

		unset($config['extra']['branch-alias']['dev-master']);

		@mkdir("$outputPath{$this->name}", 0755, true);
		`cp -r {$this->path} $outputPath{$this->name}/`;

		file_put_contents($outputPath.$this->name.'/composer.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$finder = new Finder();
		$finder
			->followLinks()
			->name("*.php")
			->name("*.inc");

		$this->sourceFiles = [];
		/** @var SplFileInfo $file */
		foreach($finder->in(trailingslashit($outputPath.$this->name))->exclude('composer') as $file) {
			$this->sourceFiles[] = $file->getRealPath();
		}

		$namespaceRegex = '/^\s*namespace\s+([^;]+)/m';
		$idiotNamespaceRegex = '/^\s*\<\?php\s+namespace\s+([^;]+)/m';
		foreach($this->sourceFiles as $file) {
			$matches = [];
			preg_match_all($namespaceRegex, file_get_contents($file), $matches, PREG_SET_ORDER, 0);
			if (count($matches) > 0) {
				foreach($matches as $match) {
					if ($match[1] == "'.__NAMESPACE__.'") {
						continue;
					}

					if (!in_array($match[1], $this->namespaces)) {
						$this->namespaces[] = $match[1];
					}
				}
			} else {
				preg_match_all($idiotNamespaceRegex, file_get_contents($file), $matches, PREG_SET_ORDER, 0);
				if (count($matches) > 0) {
					foreach($matches as $match) {
						if ($match[1] == "'.__NAMESPACE__.'") {
							continue;
						}

						if (!in_array($match[1], $this->namespaces)) {
							$this->namespaces[] = $match[1];
						}
					}
				}
			}
		}
	}

	/**
	 * @param Configuration $configuration
	 * @param ConsoleSectionOutput $output
	 * @param ProgressBar $progressBar
	 * @param string $namespacePrefix
	 * @param array $namespaces
	 */
	public function renamespace($configuration, $output, $progressBar, string $namespacePrefix, array $namespaces) {
		$namespacePrefixString = str_replace("\\", "\\\\", $namespacePrefix);
		$namespacePrefixStringRegex = str_replace("\\", "\\\\", $namespacePrefixString);

		foreach($this->sourceFiles as $file) {
			if (!empty($output) && !empty($this->outputPath)) {
				$relativeFile = str_replace($this->outputPath.$this->name, '', $file);
				$output->overwrite("Re-namespacing package {$this->name} ... $relativeFile");
			}

			if (!empty($progressBar)) {
				$progressBar->advance();
			}

			$source = file_get_contents($file);

			$currentNamespace = null;
			preg_match("#^\s*namespace\s+([^;]+)#m", $source, $matches);
			if (count($matches) > 1) {
				$currentNamespace = $matches[1];
			} else {
				preg_match("#^\s*\<\?php\s+namespace\s+([^;]+)#m", $source, $matches);
				if (count($matches) > 1) {
					$currentNamespace = $matches[1];
				}
			}

			$source = $configuration->start($source, $currentNamespace, $namespacePrefix, $this->name, $file);

			$currentNamespaceRegexSafe = str_replace("\\","\\\\", $currentNamespace);
			$source = preg_replace("#^\s*namespace\s+$currentNamespaceRegexSafe\s*;#m", "\nnamespace $namespacePrefix$currentNamespace;", $source, -1, $count);
			$source = preg_replace("#^\s*\<\?php\s+namespace\s+$currentNamespaceRegexSafe\s*;#m", "<?php\n\nnamespace $namespacePrefix$currentNamespace;", $source, -1, $count);

			$changes = 0;
			foreach($namespaces as $namespace) {
				$source = $configuration->before($source, $namespace, $currentNamespace, $namespacePrefix, $this->name, $file);

				$namespace = $namespace."\\";
				$stringNamespace = str_replace("\\", "\\\\", $namespace);
				$stringNamespaceRegex = str_replace("\\", "\\\\", $stringNamespace);

				$namespaceTrimmed = rtrim($namespace, "\\");
				$stringNamespaceTrimmed = rtrim(str_replace("\\", "\\\\", $namespace), "\\");


				$source = preg_replace("#^\\s*use\\s+$stringNamespace#m", "use $namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#^\\s*use\\s+$stringNamespaceTrimmed;#m", "use $namespacePrefix$namespaceTrimmed;", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\s+$stringNamespaceRegex#m", " $namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\\"$stringNamespaceRegex#m", "\"$namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\'$stringNamespaceRegex#m", "'$namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\s+\\\\\\\\$stringNamespaceRegex#m", " \\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\\"\\\\\\\\$stringNamespaceRegex#m", "\"\\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\'\\\\\\\\$stringNamespaceRegex#m", "'\\\\$namespacePrefixStringRegex$stringNamespace\\", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\s+$stringNamespace#m", " $namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\\"$stringNamespace#m", "\"$namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\'$stringNamespace#m", "'$namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\'\\\\$stringNamespace#m", "'\\$namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\(\s*\\\\$stringNamespace#m", "(\\$namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\s+\\\\$stringNamespace#m", " \\$namespacePrefix$namespace", $source, -1, $count);
				$changes += $count;

				$source = preg_replace("#\\s+$namespacePrefixString(.*)\s*\(#m", ' \\NAMESPACEPLACEHOLDER$1(', $source, -1, $count);
				$source = str_replace('NAMESPACEPLACEHOLDER', $namespacePrefix, $source);
				$changes += $count;

				$source = $configuration->after($source, $namespace, $currentNamespace, $namespacePrefix, $this->name, $file);
			}

			$source = $configuration->end($source, $currentNamespace, $namespacePrefix, $this->name, $file);

			file_put_contents($file, $source);
		}
	}

	//endregion

}