<?php

namespace kovarp\Hellofront;

use Nette\Utils\FileSystem;
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

	/** @var string */
	private $pagesRootPath;

	/** @var array */
	private $pages;

	/**
	 * HTMLGenerator constructor.
	 *
	 * @param $projectPath
	 */
	public function __construct( $projectPath ) {
		$this->projectPath   = $projectPath;
		$this->outputFolder  = 'build';
		$this->pagesRootPath = 'app/templates/pages/';
		$this->pages         = array();

		$this->loadPagesToGenerate();
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
	 * Load pages from templates folder
	 */
	private function loadPagesToGenerate() {
		$this->pages[] = '';

		$dirs = scandir( $this->pagesRootPath );

		foreach ( $dirs as $dir ) {
			if ( $dir != '.' && $dir != '..' ) {
				$dirPages = scandir( $this->pagesRootPath . $dir );
				foreach ( $dirPages as $dirPage ) {
					if ( $dirPage != '.' && $dirPage != '..' ) {
						$this->pages[] = strtolower( $dir ) . '/' . str_replace( '.latte', '', $dirPage );
					}
				}
			}
		}

		// Don't generate the main homepage twice
		if ( ( $key = array_search( 'homepage/default', $this->pages ) ) !== FALSE ) {
			unset( $this->pages[ $key ] );
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
		$url = 'http://localhost' . $this->projectPath . $page . '?staticGenerator';

		do {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
			curl_setopt( $ch, CURLOPT_BINARYTRANSFER, TRUE );
			$content  = curl_exec( $ch );
			$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			$dom = new DOMDocument;
			$dom->loadHTML( $content );

			if ( $httpcode == 301 ) {
				foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
					$url = $node->getAttribute( 'href' );
				}
			}

			curl_close( $ch );
		} while ( $httpcode == 301 );

		// Rewrite links to build
		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			$url = $node->getAttribute( 'href' );

			if ( strpos( $url, $this->projectPath ) !== FALSE ) {
				$node->setAttribute( 'href',
					str_replace( $this->projectPath, $this->projectPath . $this->outputFolder . '/', $url ) );
			}
		}

		return mb_convert_encoding( $dom->saveHTML(), 'UTF-8', 'HTML-ENTITIES' );
	}

	/**
	 * Save HTML content in HTML file.
	 *
	 * @param $page
	 * @param $content
	 */
	private function saveContentToFile( $page, $content ) {
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