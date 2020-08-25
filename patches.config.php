<?php

return [
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