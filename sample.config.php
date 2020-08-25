<?php

return [
	/** These functions are called once the source file has been loaded but before all of the namespace changes are processed. */
	"start" => [
		function(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		},
	],

	/** These functions are called once per namespace being processed before the regex's are run. */
	"before" => [
		function(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string $namespace The namespace currently being processed
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		}
	],

	/** These functions are called once per namespace being processed after the regex's are run. */
	"after" => [
		function(string $source, string $namespace, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string $namespace The namespace currently being processed
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		}
	],

	/** These functions are called before the changed source file is saved, after all the processing has taken place. */
	"end" => [
		function(string $source, ?string $currentNamespace, string $namespacePrefix, string $package, string $file) {
			/**
			 * @var string $source The PHP source file contents
			 * @var string|null $currentNamespace The current namespace of the source file (without new prefix)
			 * @var string $namespacePrefix The new namespace prefix
			 * @var string $package The name of the composer package
			 * @var string $file The complete path to the source file
			 */
			return $source; // You should always return the $source after manipulating it
		},
	]
];