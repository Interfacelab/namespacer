<?php

namespace ILAB\Namespacer\Commands;

use ILAB\Namespacer\Models\Configuration;
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
		$this->setDescription("Renamespaces a composer.json project for use in WordPress plugins.");

		$this->addArgument("dest", InputArgument::REQUIRED, "The path to save the renamespaced libraries to.");

		$this->addOption("composer", null, InputOption::VALUE_REQUIRED, "The path to the composer.json containing the packages to renamespace.", null);
		$this->addOption("source", null, InputOption::VALUE_REQUIRED, "The path to the directory containing the composer.json to renamespace.  This directory should already have had `composer update` run in it.", null);

		$this->addOption("package", null, InputOption::VALUE_REQUIRED, "The prefix to add to packages", "mcloud");
		$this->addOption("namespace", null, InputOption::VALUE_REQUIRED, "The prefix to add to namespaces", "MediaCloud\\Vendor\\");

		$this->addOption("config", null, InputOption::VALUE_REQUIRED, "The path to the configuration to use, if required.", null);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		//region Directories

		if (empty($input->getOption('composer')) && empty($input->getOption('source'))) {
			$output->writeln("<error>You must specify either the --composer or --source option.</error>");
			return Command::FAILURE;
		}

		$packagePrefix = $input->getOption('package');
		$namespacePrefix = $input->getOption('namespace');

		$namespacePrefix = rtrim($namespacePrefix, "\\")."\\";

		$tempPath = trailingslashit($this->rootDir.uniqid());
		@mkdir($tempPath, 0755, true);

		if (!empty($input->getOption('composer'))) {
			$originalComposer = $input->getOption('composer');
			if (strpos($originalComposer, '/') !== 0) {
				$originalComposer = $this->rootDir.$originalComposer;
			} else {
				$originalComposer = $originalComposer;
			}

			if (!file_exists($originalComposer)) {
				rmdir($tempPath);
				$output->writeln("<error>Composer file does not exist at $originalComposer.</error>");
				return Command::FAILURE;
			}

			$sourcePath = trailingslashit($tempPath.'project');
			@mkdir($sourcePath, 0755, true);

			copy($originalComposer, $sourcePath.'composer.json');

			$output->writeln("Creating project ... ");
			$output->writeln("");
			`cd $sourcePath && composer update`;
			$output->writeln("");
		} else {
			$sourcePath = $input->getOption('source');
			if (strpos($sourcePath, '/') !== 0) {
				$sourcePath = trailingslashit($this->rootDir.$sourcePath);
			} else {
				$sourcePath = trailingslashit($sourcePath);
			}

			if (!file_exists($sourcePath)) {
				$output->writeln("<error>Input directory $sourcePath does not exist.</error>");
				return Command::FAILURE;
			}
		}

		$outputPath = $input->getArgument('dest');
		if (strpos($outputPath, '/') !== 0) {
			$outputPath = trailingslashit($this->rootDir.$outputPath);
		} else {
			$outputPath = trailingslashit($outputPath);
		}

		if (file_exists($outputPath)) {
			if (file_exists($outputPath.'lib')) {
				`rm -rf {$outputPath}lib`;
			}
		} else {
			if(!mkdir($outputPath, 0755, true)) {
				$output->writeln("<error>Could not create output directory.</error>");
				return Command::FAILURE;
			}
		}

		$projectOutputPath = $tempPath.'build/';
		$libraryOutputPath = $tempPath.'library/';

		@mkdir($projectOutputPath, 0755, true);
		@mkdir($libraryOutputPath, 0755, true);

		$configPath = null;
		if (!empty($input->getOption('config'))) {
			$configPath = $input->getOption('config');
			if (strpos($configPath, '/') !== 0) {
				$configPath = $this->rootDir.$configPath;
			}

			if (!file_exists($configPath)) {
				$output->writeln("<error>Config file $configPath does not exist.</error>");
				return Command::FAILURE;
			}
		}

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
				["Destination", $outputPath],
				["Config", $configPath],
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
		$configuration = new Configuration($configPath);
		
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
			$package->renamespace($configuration, $packageSection, $packageProgress, $namespacePrefix, $allNamespaces);
		}

		$packageSection->overwrite("Finished re-namespacing packages.");
		$packageProgress->finish();

		$packageProgressSection->clear();

		$output->writeln("");
		
		//endregion

		//region Finished

		$project->save($projectOutputPath, $packagePrefix);
		$output->writeln("Saved project.  Running composer ...");
		$output->writeln("");

		`cd {$projectOutputPath} && composer update`;
		`rm -rf {$projectOutputPath}vendor/bin`;
		rename($projectOutputPath.'vendor', $outputPath.'lib');
		`rm -rf $tempPath`;

		$output->writeln("");
		$output->writeln("Finished.");

		//endregion

		return Command::SUCCESS;
	}
}