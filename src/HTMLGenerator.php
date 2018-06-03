<?php

namespace kovarp\Hellofront;

use Nette\Utils\FileSystem;
use Nette\Http\Url;
use DOMDocument;

/**
 * Class HTMLGenerator
 * @package kovarp\Hellofront
 */
class HTMLGenerator {

	/** @var string */
	private $projectPath;

	/** @var string */
	private $outputFolder;

	/** @var array */
	private $pages;

	/**
	 * HTMLGenerator constructor.
	 *
	 * @param array  $pages
	 */
	public function __construct($pages, $projectPath) {
		$this->outputFolder = 'build';
		$this->pages = $pages;
		$this->projectPath = $projectPath;
	}

	/**
	 * Generate templates
	 */
	public function generate() {
		FileSystem::delete( 'temp/cache' );
		FileSystem::delete( $this->outputFolder );
		@mkdir( $this->outputFolder, 0755, TRUE );

		foreach ( $this->pages as $page ) {
			$this->saveContentToFile( $page, $this->getPageContent( $page ) );
		}
	}

	/**
	 * Return the HTML content of the page.
	 *
	 * @param $page
	 *
	 * @return string
	 */
	private function getPageContent( $page ) {
		$url = $page . '?staticGenerator';

		do {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
			curl_setopt( $ch, CURLOPT_BINARYTRANSFER, TRUE );
			$content  = curl_exec( $ch );
			$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );


			if ( $httpcode == 301 ) {
				$dom = new DOMDocument;
				$dom->formatOutput = true;
				$dom->loadHTML( $content );

				foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
					$url = $node->getAttribute( 'href' );
				}
			}

			curl_close( $ch );
		} while ( $httpcode == 301 );

		return str_replace('buildpathtorewrite', 'build', $content );
	}

	/**
	 * Save HTML content in HTML file.
	 *
	 * @param $page
	 * @param $content
	 */
	private function saveContentToFile( $page, $content ) {
		$url = new Url($page);
		$page = $url->path;
		$page = substr($page, strlen($this->projectPath . '/') + 1);

		var_dump($page);

		if ( $page == '' ) {
			$page = 'index';
		}
		$page .= '.html';

		$page = str_replace( 'default', 'index', $page );
		$page = str_replace( '.phtml', '', $page );

		$filepath = $this->outputFolder . '/' . $page;

		$isInFolder = preg_match( "/^(.*)\/([^\/]+)$/", $filepath, $filepathMatches );
		if ( $isInFolder ) {
			$folderName = $filepathMatches[1];
			if ( ! is_dir( $folderName ) ) {
				mkdir( $folderName, 0777, TRUE );
			}
		}
		file_put_contents( $filepath, $content );
	}
}