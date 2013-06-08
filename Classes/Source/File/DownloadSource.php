<?php

namespace EasyDeployWorkflows\Source\File;



/**
 * Download Source that uses the standard Downloader

Example Package Path: http://user:password@host.de/path/mypackage.tar.gz
 *
 */
class DownloadSource implements FileSourceInterface  {

	/**
	 * @var string
	 */
	protected $url;

	public function __construct($url = '') {
		$this->setUrl($url);
	}

	/**
	 * Downloads the given source on the given server in the given parent path
	 *
	 * @return void
	 */
	public function getDownloadCommand($parentFolder) {
		$url = $this->buildUrl();
		$parsedUrlParts=parse_url($url);
		if (array_key_exists('port', $parsedUrlParts)) {
			$parsedUrlParts['host'] = $parsedUrlParts['host'] . ':' . $parsedUrlParts['port'];
		}

		$options= '';
		if (isset($parsedUrlParts['user']) && $parsedUrlParts['user'] != '') {
			$options = '--auth-no-challenge --http-user="'.$parsedUrlParts['user'].'" --http-password="'.$parsedUrlParts['pass'].'" ';
		}

		if (!isset($parsedUrlParts['scheme'])) {
			throw new \Exception('No valid Download Source given. ('.$url.')');
		}

		$command = 'cd '.$parentFolder.';';
		$command .= 'wget '.$options.$parsedUrlParts['scheme'].'://'.$parsedUrlParts['host'].$parsedUrlParts['path'].@$parsedUrlParts['fragment'];
		return $command;
	}



	/**
	 * @param string $source
	 */
	public function setUrl($source) {
		$this->url = $source;
	}

	protected function buildUrl() {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getShortExplain() {
		return 'Download from:'.$this->buildUrl();
	}

	/**
	 * @return string
	 */
	public function getFileName() {
		return $this->getFilenameFromPath($this->url);
	}

	/**
	 * @param $path
	 * @return string
	 */
	protected function getFilenameFromPath($path) {
		$dir = dirname($path).DIRECTORY_SEPARATOR;
		return str_replace($dir,'',$path);
	}
}
