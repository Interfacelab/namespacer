<?php

return [
	"prepare" => [
		function(string $package, array $config, string $path, string $namespacePrefix) {
			if (($package == 'kraken-io/kraken-php') && isset($config['autoload']['psr-0']['Kraken'])) {
				$srcDir = trailingslashit($path.$config['autoload']['psr-0']['Kraken']);
				$finder = new Symfony\Component\Finder\Finder();
				$files = [];
				foreach($finder->in($srcDir) as $fileInfo) {
					$files[] = $fileInfo->getRealPath();
				}

				if (file_exists($srcDir.'Kraken.php')) {
					$source = file_get_contents($srcDir.'Kraken.php');
					$source = str_replace('<?php', "<?php\n\nnamespace Kraken;\n\nuse \\CURLFile;", $source);
					file_put_contents($srcDir.'Kraken.php', $source);
				}

				$namespacedDir = trailingslashit($srcDir.'Kraken');
				mkdir($namespacedDir, 0755, true);
				foreach($files as $file) {
					rename($file, $namespacedDir.pathinfo($file, PATHINFO_BASENAME));
				}
			}

			return $config; // You should always return the $config after manipulating it
		}
	],

	"start" => [
		function(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			if ($package === 'duncan3dc/blade') {
				$filename = pathinfo($file, PATHINFO_BASENAME);
				if ($filename == 'BladeInstance.php') {
					$source = str_replace("private function getViewFinder(", "protected function getViewFinder(", $source);
					$source = str_replace("private function getViewFactory(", "protected function getViewFactory(", $source);
				}
			} else if ($package === 'smalot/pdfparser') {
				$filename = pathinfo($file, PATHINFO_BASENAME);
				if ($filename == 'Font.php') {
					$source = str_replace('$details[\'Encoding\'] = ($this->has(\'Encoding\') ? (string) $this->get(\'Encoding\') : \'Ansi\');', '$details[\'Encoding\'] = \'Ansi\';', $source);
				}
			}

			return $source;
		},
	],
];