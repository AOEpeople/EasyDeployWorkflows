<?php

namespace EasyDeployWorkflows\Workflows\Application;

use EasyDeployWorkflows\Workflows as Workflows;

class ReleaseFolderApplicationWorkflow extends Workflows\TaskBasedWorkflow {

	/**
	 * @var MagentoApplicationConfiguration
	 */
	protected $workflowConfiguration;

	/**
	 * Can be used to do individual workflow initialisation and/or checks
	 */
	protected function workflowInitialisation() {

		$this->addTask('check correct deploy node',
			new \EasyDeployWorkflows\Tasks\Common\CheckCorrectDeployNode());

		$this->addTasksToDownloadFromSourceToReleaseFolder();
		$this->addUpdateNextSymlinkTask();
		$this->addPreSetupTasks();
		$this->addWriteVersionFileTask();
		$this->addSetupTasks();
		$this->addSymlinkSharedFoldersTasks();
		$this->addPostSetupTasks();
		$this->addSmokeTestTasks();
		$this->addSwitchTask();
		$this->addPostSwitchTasks();
		$this->addCleanupTasks();

	}

	protected function addTasksToDownloadFromSourceToReleaseFolder() {
		if ($this->workflowConfiguration->getSource() instanceof \EasyDeployWorkflows\Source\File\FileSourceInterface) {
			//we expect this to be an archive - so we download it to deliveryfolder first
			$task = new \EasyDeployWorkflows\Tasks\Common\SourceEvaluator();
			$task->setSource($this->workflowConfiguration->getSource());
			$task->setParentFolder($this->getFinalDeliveryFolder());
			$task->setNotIfPathExists($this->getFinalDeliveryFolder() . $this->workflowConfiguration->getSource()->getFileName());
			$task->addServersByName($this->workflowConfiguration->getInstallServers());
			$this->addTask('Download Filesource to Deliveryfolder',$task);

			$task = $this->getUnzipPackageTask();
			$task->addServersByName($this->workflowConfiguration->getInstallServers());
			$task->setChangeToDirectory($this->workflowConfiguration->getReleaseBaseFolder());
			$this->addTask('Untar Package', $task);

			$task = new \EasyDeployWorkflows\Tasks\Common\Rename();
			$task->addServersByName($this->workflowConfiguration->getInstallServers());
			$task->setSource($this->workflowConfiguration->getReleaseBaseFolder().$this->workflowConfiguration->getSource()->getFileName());
			$task->setTarget($this->workflowConfiguration->getReleaseBaseFolder().$this->workflowConfiguration->getReleaseVersion());
			$this->addTask('Rename Unzipped Package to Release', $task);
		}
		else {
			/** @var \EasyDeployWorkflows\Source\Folder\FolderSourceInterface $source */
			$source = $this->workflowConfiguration->getSource();
			$source->setIndividualTargetFolderName($this->workflowConfiguration->getReleaseVersion());
			$task = new \EasyDeployWorkflows\Tasks\Common\SourceEvaluator();
			$task->setSource($source);
			$task->setParentFolder($this->getFinalReleaseBaseFolder());
			$task->setNotIfPathExists($this->getFinalReleaseBaseFolder() . $this->workflowConfiguration->getReleaseVersion());
			$task->addServersByName($this->workflowConfiguration->getInstallServers());
			$this->addTask('Download Foldersource to Releasefolder',$task);
		}
	}

	protected function addUpdateNextSymlinkTask() {
		$task = new \EasyDeployWorkflows\Tasks\Release\UpdateNext();
		$task->setReleasesBaseFolder($this->getFinalReleaseBaseFolder());
		$task->setNextRelease($this->workflowConfiguration->getReleaseVersion());
		$task->addServersByName($this->workflowConfiguration->getInstallServers());
		$this->addTask('Update next symlink',$task);
	}

	/**
	 * Possibility to add some tasks
	 *
	 * @return void
	 */
	protected function addPreSetupTasks() {
		foreach ($this->workflowConfiguration->getPreSetupTasks() as $name => $task) {
			$this->addTask($name,$task);
		}
	}

	/**
	 * add version file write
	 */
	protected function addWriteVersionFileTask() {
		$task = new \EasyDeployWorkflows\Tasks\Common\WriteVersionFile();
		$task->setTargetPath($this->getFinalReleaseBaseFolder().'next');
		$task->setVersion($this->workflowConfiguration->getReleaseVersion());
		$this->addTask('Write Version File',$task);
	}

	/**
	 * Installation of Magento
	 *
	 * @return void
	 */
	protected function addSetupTasks()
	{
		$task = new \EasyDeployWorkflows\Tasks\Common\RunCommand();
		$task->setChangeToDirectory($this->getFinalReleaseBaseFolder().'next');
		$command = $this->replaceMarkers($this->workflowConfiguration->getSetupCommand());
		$task->setCommand($command);
		$task->addServersByName($this->workflowConfiguration->getInstallServers());
		$this->addTask('Setup Script',$task);
	}

	/**
	 * Symlinks media folder
	 */
	protected function addSymlinkSharedFoldersTasks() {

	}

	/**
	 * Possibility to add some tasks
	 *
	 * @return void
	 */
	protected function addPostSetupTasks() {
		foreach ($this->workflowConfiguration->getPostSetupTasks() as $name => $task) {
			$this->addTask($name,$task);
		}
	}

	/**
	 */
	protected function addSmokeTestTasks()
	{

	}

	protected function addSwitchTask() {
		$task = new \EasyDeployWorkflows\Tasks\Release\UpdateCurrentAndPrevious();
		$task->setReleasesBaseFolder($this->getFinalReleaseBaseFolder());
		$task->addServersByName($this->workflowConfiguration->getInstallServers());
		$this->addTask('Switch current symlink',$task);
	}

	protected function addPostSwitchTasks() {

	}

	/**
	 * clean up old releases
	 */
	protected function addCleanupTasks()
	{
		$task = new \EasyDeployWorkflows\Tasks\Release\CleanupReleases();
		$task->setReleasesBaseFolder($this->getFinalReleaseBaseFolder());
		$task->addServersByName($this->workflowConfiguration->getInstallServers());
		$this->addTask('Cleanup old Releases',$task);

	}


	protected function getUnzipPackageTask()
	{
		$step = new \EasyDeployWorkflows\Tasks\Common\Untar();
		$step->autoInitByPackagePath($this->getFinalDeliveryFolder() . '/' . $this->workflowConfiguration->getDownloadSource()->getFileName());
		$step->setMode(\EasyDeployWorkflows\Tasks\Common\Untar::MODE_SKIP_IF_EXTRACTEDFOLDER_EXISTS);
		return $step;
	}

	/**
	 * @return string
	 */
	protected function getFinalReleaseBaseFolder() {
		return $this->replaceMarkers($this->workflowConfiguration->getReleaseBaseFolder());
	}



}