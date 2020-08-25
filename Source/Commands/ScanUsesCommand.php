<?php

namespace ILAB\Namespacer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ScanUsesCommand extends Command {
	protected static $defaultName = 'scan';

	protected $rootDir = null;

	public function __construct(string $name = null, string $rootDir = null) {
		parent::__construct($name);

		$this->rootDir = trailingslashit($rootDir);
	}

	protected function configure() {
		$this->addArgument("input", InputArgument::REQUIRED, "The path to the directory containing the PHP files to scan.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		//region Directories
		$sourcePath = $input->getArgument('input');
		if (strpos($sourcePath, '/') !== 0) {
			$sourcePath = trailingslashit($this->rootDir.$sourcePath);
		} else {
			$sourcePath = trailingslashit($sourcePath);
		}

		if (!file_exists($sourcePath)) {
			$output->writeln("<error>Input directory $sourcePath does not exist.</error>");
			return Command::FAILURE;
		}

		$finder = new Finder();
		$finder
			->followLinks()
			->name("*.php")
			->name("*.inc");

		$sourceFiles = [];
		/** @var SplFileInfo $file */
		foreach($finder->in(trailingslashit($sourcePath)) as $file) {
			$sourceFiles[] = $file->getRealPath();
		}


		$output->writeln("Found ".count($sourceFiles)." source files.");
		$uses = [];
		foreach($sourceFiles as $sourceFile) {
			$source = file_get_contents($sourceFile);
			preg_match_all('#^\s*use\s+([^;]+)#m', $source, $matches, PREG_SET_ORDER);
			if (count($matches) > 0) {
				foreach($matches as $match) {
					if (!in_array($match[1], $uses)) {
						if (strpos($match[1], 'function ') === 0) {
							continue;
						}

						$uses[] = $match[1];
					}
				}
			}
		}

		sort($uses);

		$output->writeln("");
		$output->writeln($uses);
		$output->writeln("");
		$output->writeln("Finished.");

		return Command::SUCCESS;
	}
}