<?php

namespace EasyDeployWorkflows\Tasks\Web;

use EasyDeployWorkflows\Tasks;


/**
 * Specific Task that is bound to the AOE installbinaries
 */
class RunPackageInstallBinaries extends \EasyDeployWorkflows\Tasks\AbstractServerTask  {

	/**
	 * @var boolean
	 */
	private $createBackupBeforeInstalling = TRUE;

	/**
	 * @var boolean
	 */
	private $silentMode = FALSE;

	/**
	 * @var string
	 */
	protected $phpbinary = 'php';

	/**
	 * @var string
	 */
	protected $targetSystemPath;

	/**
	 * @var bool
	 */
	protected $needBackupToInstall = TRUE;

	/**
	 * @var string
	 */
	protected $packageFolder;

	/**
	 * @var \EasyDeploy_Helper_Downloader
	 */
	protected $downloader;

	/**
	 * Flag to de/activate verbose mode.
	 *
	 * @var boolean
	 */
	private $verbose = FALSE;

	public function __construct() {
		parent::__construct();
		$this->injectDownloader(new \EasyDeploy_Helper_Downloader());
	}

	/**
	 * @param \EasyDeploy_Helper_Downloader $downloader
	 */
	public function injectDownloader(\EasyDeploy_Helper_Downloader $downloader) {
		$this->downloader = $downloader;
	}

	/**
	 * @param string $packageFolder
	 */
	public function setPackageFolder($packageFolder) {
		$this->packageFolder = $packageFolder;
	}

	/**
	 * @param string $targetSystemPath
	 */
	public function setTargetSystemPath($targetSystemPath) {
		$this->targetSystemPath = $targetSystemPath;
	}

	/**
	 * @param string $bin
	 */
	public function setPHPBinary($bin) {
		if (file_exists($bin) && is_executable($bin)) {
			$this->phpbinary = $bin;
		} else {
			print $this->logger->log('PHP binary '.$bin.' does not exist or is not executable.', \EasyDeployWorkflows\Logger\Logger::MESSAGE_TYPE_WARNING);
		}
	}

	/**
	 * Default is set to true
	 *
	 * @depreciated this is a concept of the install strategy - you should pass a initialised strategie
	 *
	 * @param boolean $createBackup
	 * @return void
	 */
	public function setCreateBackupBeforeInstalling($createBackup) {
		$this->createBackupBeforeInstalling = (boolean) $createBackup;
	}

	/**
	 * Set this flag to force the installation without any confirmation.
	 *
	 * @param boolean $activate
	 */
	public function setSilentMode($activate) {
		$this->silentMode = $activate;
	}

	/**
	 * @param boolean $verboseMode
	 */
	public function setVerboseMode($verboseMode) {
		$this->verbose = filter_var($verboseMode, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @param  Tasks\TaskRunInformation $taskRunInformation
	 * @param  \EasyDeploy_AbstractServer $server
	 * @return void
	 * @throws \Exception
	 */
	protected function runOnServer(\EasyDeployWorkflows\Tasks\TaskRunInformation $taskRunInformation,\EasyDeploy_AbstractServer $server) {
		$additionalParameters = '';
		$installBinariesFolder = $this->replaceConfigurationMarkers($this->packageFolder.'/installbinaries',$taskRunInformation->getWorkflowConfiguration(),$taskRunInformation->getInstanceConfiguration());

		if (!$server->isDir($installBinariesFolder)) {
			throw new \Exception('No Installbinaries are available in '.$installBinariesFolder);
		}

		// Fix permissions
		$this->logger->log('chmod -R ug+x '.$installBinariesFolder,\EasyDeployWorkflows\Logger\Logger::MESSAGE_TYPE_INFO);
		$server->run('chmod -R ug+x '.$installBinariesFolder, FALSE, FALSE, $this->logger->getLogFile());

		if ($this->createBackupBeforeInstalling === TRUE && $this->needBackupToInstall === TRUE) {
			$additionalParameters .=' --createNewMasterBackup=1';
		}

		if ($this->silentMode === TRUE) {
			$additionalParameters .= ' --silent';
		}

		if ($this->needBackupToInstall === TRUE) {
			$additionalParameters .= ' --backupstorageroot="' . $this->getBackupStorageRoot($taskRunInformation, $server) . '"';
		}

		$command = $this->phpbinary . ' ' . $installBinariesFolder.'/install.php --systemPath="' . $this->targetSystemPath  . '" --environmentName="' . $taskRunInformation->getInstanceConfiguration()->getEnvironmentName() . '"'.$additionalParameters;

		$this->logger->log('Run Installbinary: '.$command);

		// Install package
		$interactionMode = !$this->silentMode;
		$logFile = $this->verbose ? NULL : $this->logger->getLogFile();
		$server->run($command, $interactionMode, FALSE, $logFile);
	}

	/**
	 * gets the relevant backupstorage root
	 */
	protected function getBackupStorageRoot(\EasyDeployWorkflows\Tasks\TaskRunInformation $taskRunInformation,\EasyDeploy_AbstractServer $server) {

		$backupMasterEnvironment = $taskRunInformation->getWorkflowConfiguration()->getBackupMasterEnvironment();
		$backupStorageRoot = $taskRunInformation->getWorkflowConfiguration()->getBackupStorageRootFolder();
		$backupStorageRoot = $this->replaceConfigurationMarkers(
			$backupStorageRoot,
			$taskRunInformation->getWorkflowConfiguration(),
			$taskRunInformation->getInstanceConfiguration()
		);

		if ($this->hasBackupStorage($server,$backupStorageRoot,$backupMasterEnvironment)) {
			return $backupStorageRoot;
		}

		$this->logger->log('Ohoh! Master Backup not available... Getting at least a minified backup', \EasyDeployWorkflows\Logger\Logger::MESSAGE_TYPE_WARNING);

		$minifiedBackupRootFolder = $taskRunInformation->getWorkflowConfiguration()->getBackupStorageMinifiedRootFolder();
		$minifiedBackupRootFolder = $this->replaceConfigurationMarkers($minifiedBackupRootFolder,$taskRunInformation->getWorkflowConfiguration(),$taskRunInformation->getInstanceConfiguration());

		if (empty($minifiedBackupRootFolder)) {
			throw new \Exception('No minified Backup Root Folder Given. Cannot proceed without backup!');
		}

		$server->run('mkdir -p ' . $minifiedBackupRootFolder);

		if (!$taskRunInformation->getWorkflowConfiguration()->hasMinifiedBackupSource()) {
			throw new \Exception('No minified Backup source given. Check minified root configuration');
		}

		$minifiedBackupSource = $taskRunInformation->getWorkflowConfiguration()->getMinifiedBackupSource();
		$minifiedBackupSource = $this->replaceConfigurationMarkers($minifiedBackupSource,$taskRunInformation->getWorkflowConfiguration(),$taskRunInformation->getInstanceConfiguration());

		$baseName = pathinfo(parse_url($minifiedBackupSource, PHP_URL_PATH),PATHINFO_BASENAME);

		$this->downloader->download($server,$minifiedBackupSource, $minifiedBackupRootFolder);
		$server->run('cd '.$minifiedBackupRootFolder.'; tar -xzf '.$baseName);
		$server->run('cd '.$minifiedBackupRootFolder.'; mv '.$backupMasterEnvironment.'Minified '.$backupMasterEnvironment);

		if (!$this->hasBackupStorage($server,$minifiedBackupRootFolder, $backupMasterEnvironment)) {
			throw new \Exception('Even no minified Backup is available. Check the download Source');
		}

		//fake that minified is new
		$server->run('date +\'%Y-%m-%d %H:%M:%S\' > "'.$minifiedBackupRootFolder.'/'.$backupMasterEnvironment.'/backup_successful.txt"');

		// Now switch to use minified Backup in deployment
		$this->logger->log('Finished getting minified backup');
		return $minifiedBackupRootFolder;
	}
	/**
	 * @return boolean
	 * @throws \EasyDeployWorkflows\Exception\InvalidConfigurationException
	 */
	public function validate() {
		if (empty($this->packageFolder)) {
			throw new \EasyDeployWorkflows\Exception\InvalidConfigurationException('packageFolder not set');
		}
		if (empty($this->targetSystemPath)) {
			throw new \EasyDeployWorkflows\Exception\InvalidConfigurationException('targetSystemPath not set');
		}
		if (empty($this->phpbinary)) {
			throw new \EasyDeployWorkflows\Exception\InvalidConfigurationException('phpbinary not set');
		}
		return true;
	}

	/**
	 * @param \EasyDeploy_AbstractServer $server
	 * @param string $root
	 * @param string $environment
	 * @return bool
	 */
	private function hasBackupStorage(\EasyDeploy_AbstractServer $server, $root, $environment) {
		if ($server->isDir($root)
			&& $server->isDir($root.'/'.$environment)
			&& $server->isDir($root.'/'.$environment.'/files') ) {

			$this->logger->log('Fine! Backup seems available in '.$root);
			return true;
		}
		return false;
	}

	/**
	 * @param boolean $needBackupToInstall
	 */
	public function setNeedBackupToInstall($needBackupToInstall)
	{
		$this->needBackupToInstall = $needBackupToInstall;
	}

	/**
	 * @return boolean
	 */
	public function getNeedBackupToInstall()
	{
		return $this->needBackupToInstall;
	}
}
