<?php

namespace EasyDeployWorkflows\Workflows;

use EasyDeployWorkflows\Workflows;

require_once dirname(__FILE__) . '/../AbstractPart.php';

abstract class AbstractWorkflow extends \EasyDeployWorkflows\AbstractPart {
	/**
	 * @var InstanceConfiguration
	 */
	protected $instanceConfiguration;

	/**
	 * @var AbstractConfiguration
	 */
	protected $workflowConfiguration;

	/**
	 * @var \EasyDeploy_Helper_Downloader
	 */
	protected $downloader;

	/**
	 * @var string a speaking title
	 */
	protected $title;



	/**
	 * @param InstanceConfiguration $instanceConfiguration
	 * @param AbstractWorkflowConfiguration $workflowConfiguration
	 */
	public function __construct(InstanceConfiguration $instanceConfiguration, AbstractWorkflowConfiguration $workflowConfiguration) {
		parent::__construct();
		$instanceConfiguration->validate();
		$workflowConfiguration->validate();

		$this->instanceConfiguration = $instanceConfiguration;
		$this->workflowConfiguration = $workflowConfiguration;
		$this->workflowInitialisation();
	}

	/**
	 * @param \EasyDeploy_Helper_Downloader $downloader
	 */
	public function injectDownloader(\EasyDeploy_Helper_Downloader $downloader) {
		$this->downloader = $downloader;
	}

	/**
	 * Can be used to do individual workflow initialisation and/or checks
	 */
	protected function workflowInitialisation() {

	}

	/**
	 * @param $string
	 * @return mixed
	 */
	protected function replaceMarkers($string) {
		return $this->replaceConfigurationMarkers($string,$this->workflowConfiguration,$this->instanceConfiguration);
	}

	/**
	 * @return \EasyDeployWorkflows\Tasks\TaskRunInformation
	 */
	protected function createTaskRunInformation() {
		$taskRunInformation = new \EasyDeployWorkflows\Tasks\TaskRunInformation();
		$taskRunInformation->setInstanceConfiguration($this->instanceConfiguration);
		$taskRunInformation->setWorkflowConfiguration($this->workflowConfiguration);
		return $taskRunInformation;
	}


	protected function getFinalDeliveryFolder() {
		if ($this->workflowConfiguration->hasDeliveryFolder()) {
			return $this->replaceMarkers($this->workflowConfiguration->getDeliveryFolder());
		}
		return $this->replaceMarkers($this->instanceConfiguration->getDeliveryFolder());
	}

	/**
	 * @param string $title
	 */
	public function setTitle($title) {
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}
	/**
	 * @return mixed
	 */
	abstract function deploy();
}