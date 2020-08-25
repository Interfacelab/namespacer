<?php

namespace ILAB\Namespacer\Commands;

use ILAB\Namespacer\Models\Project;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RenamespaceCommand extends Command {
	protected static $defaultName = 'renamespace';
	protected $rootDir = null;

	public function __construct(string $name = null, string $rootDir = null) {
		parent::__construct($name);

		$this->rootDir = trailingslashit($rootDir);
	}

	protected function configure() {
		$this->addArgument("input", InputArgument::REQUIRED, "The path to the project containing the composer.json to process.");
		$this->addArgument("output", InputArgument::REQUIRED, "The path to save the fixed libraries to.");

		$this->addOption("package-prefix", "package", InputOption::VALUE_REQUIRED, "The prefix to add to packages", "mcloud");
		$this->addOption("namespace-prefix", "namespace", InputOption::VALUE_REQUIRED, "The prefix to add to namespaces", "MediaCloud\\Vendor\\");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		//region Directories

		$packagePrefix = $input->getOption('package-prefix');
		$namespacePrefix = $input->getOption('namespace-prefix');

		$namespacePrefix = rtrim($namespacePrefix, "\\")."\\";

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

		$outputPath = $input->getArgument('output');
		if (strpos($outputPath, '/') !== 0) {
			$outputPath = trailingslashit($this->rootDir.$outputPath);
		} else {
			$outputPath = trailingslashit($outputPath);
		}

		if (file_exists($outputPath)) {
			`rm -rf $outputPath`;
		}

		if(!mkdir($outputPath, 0755, true)) {
			$output->writeln("<error>Could not create output directory.</error>");
			return Command::FAILURE;
		}

		$projectOutputPath = $outputPath.'project/';
		$libraryOutputPath = $outputPath.'library/';

		@mkdir($projectOutputPath, 0755, true);
		@mkdir($libraryOutputPath, 0755, true);

		//endregion

		//region Project Info
		
		$project = new Project($sourcePath);

		$output->writeln("");

		$table = new Table($output);
		$table
			->setHeaderTitle("Settings")
			->setHeaders(['Setting', 'Value'])
			->setRows([
				["Package Prefix", $packagePrefix],
				["Namespace Prefix", $namespacePrefix],
				["Source", $sourcePath],
				["Library", $libraryOutputPath],
				["Project", $projectOutputPath],
			])
			->render();

		$output->writeln("");

		$table = new Table($output);
		$table
			->setHeaderTitle("Found Packages")
			->setHeaders(['Package', 'Version'])
			->setRows($project->getPackageTableData())
			->render();

		$output->writeln("");
		
		//endregion
		
		//region Package Processing
		
		$packageSection = $output->section();
		$packageSection->writeln("Processing packages ...");

		$packageProgressSection = $output->section();
		$packageProgress = new ProgressBar($packageProgressSection, count($project->getPackages()));
		$packageProgress->setBarWidth(50);
		$packageProgress->start();

		$sourceFileCount = 0;
		$allNamespaces = [];
		foreach($project->getPackages() as $packageName => $package) {
			$packageSection->overwrite("Processing package $packageName ...");
			$packageProgress->advance();
			$package->process($packagePrefix, $namespacePrefix, $libraryOutputPath, $project->getPackages());
			$allNamespaces = array_merge($allNamespaces, $package->getNamespaces());
			$sourceFileCount += count($package->getSourceFiles());
		}

		$packageSection->overwrite("Finished processing packages.");
		$packageProgress->finish();

		$packageProgressSection->clear();

		$output->writeln("");
		$output->writeln("Found ".count($allNamespaces)." namespaces in {$sourceFileCount} source files.");
		
		//endregion
		
		//region Re-namespacing
		
		$packageSection = $output->section();
		$packageSection->writeln("Re-namespacing packages ...");

		$packageProgressSection = $output->section();
		$packageProgress = new ProgressBar($packageProgressSection, $sourceFileCount);
		$packageProgress->setFormat('very_verbose');
		$packageProgress->setBarWidth(50);
		$packageProgress->start();

		foreach($project->getPackages() as $packageName => $package) {
			$packageSection->overwrite("Re-namespacing package $packageName ...");
			$packageProgress->advance();
			$package->renamespace($packageSection, $packageProgress, $namespacePrefix, $allNamespaces);
		}

		$packageSection->overwrite("Finished re-namespacing packages.");
		$packageProgress->finish();

		$packageProgressSection->clear();

		$output->writeln("");
		
		//endregion

		$project->save($projectOutputPath, $packagePrefix);
		$output->writeln("Saved project.  Running composer ...");
		$output->writeln("");

		`cd {$projectOutputPath} && composer update`;
		`rm -rf {$projectOutputPath}vendor/bin`;
		rename($projectOutputPath.'vendor', $outputPath.'lib');
		copy($this->rootDir.'Templates/index.php', $outputPath.'index.php');
		`rm -rf $projectOutputPath`;
		`rm -rf $libraryOutputPath`;

		$output->writeln("");
		$output->writeln("Finished.");

		return Command::SUCCESS;
	}
}