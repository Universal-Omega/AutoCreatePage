<?php

use MediaWiki\Edit\PreparedEdit;

class AutoCreatePageHooks {
	public static function onRegistration() {
		global $wgAutoCreatePageMaxRecursion, $wgAutoCreatePageIgnoreEmptyTitle, $wgAutoCreatePageNamespaces, $wgContentNamespaces;

		/**
		 * This is decreased during page creation to avoid infinite recursive creation of pages.
		 */
		$wgAutoCreatePageMaxRecursion = 1;

		$wgAutoCreatePageIgnoreEmptyTitle = false;

		$wgAutoCreatePageNamespaces = $wgContentNamespaces;
	}

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'createPage', [ __CLASS__, 'createPageIfNotExisting' ] );
	}

	/**
	 * @param array $rawParams
	 * @return string
	 */
	public static function createPageIfNotExisting( array $rawParams ) {
		global $wgAutoCreatePageMaxRecursion, $wgAutoCreatePageIgnoreEmptyTitle, $wgAutoCreatePageNamespaces;

		if ( $wgAutoCreatePageMaxRecursion <= 0 ) {
			return 'Error: Recursion level for auto-created pages exeeded.'; //TODO i18n
		}

		if ( isset( $rawParams[0] ) && isset( $rawParams[1] ) && isset( $rawParams[2] ) ) {
			$parser = $rawParams[0];
			$newPageTitleText = $rawParams[1];
			$newPageContent = $rawParams[2];
		} else {
			throw new MWException( 'Hook invoked with missing parameters.' );
		}

		if ( empty( $newPageTitleText ) ) {
			if ( $wgAutoCreatePageIgnoreEmptyTitle === false ) {
				return 'Error: this function must be given a valid title text for the page to be created.'; //TODO i18n
			} else {
				return '';
			}
		}

		// Create pages only if the page calling the parser function is within defined namespaces
		if ( !in_array( $parser->getTitle()->getNamespace(), $wgAutoCreatePageNamespaces ) ) {
			return '';
		}

		// Get the raw text of $newPageContent as it was before stripping <nowiki>:
		$newPageContent = $parser->mStripState->unstripNoWiki( $newPageContent );

		// Store data in the parser output for later use:
		$createPageData = $parser->getOutput()->getExtensionData( 'createPage' );
		if ( is_null( $createPageData ) ) {
			$createPageData = [];
		}
		$createPageData[$newPageTitleText] = $newPageContent;
		$parser->getOutput()->setExtensionData( 'createPage', $createPageData );

		return '';
	}

	
	/**
	 * @param WikiPage &$wikiPage
	 * @param PreparedEdit &$editInfo
	 * @param bool $changed
	 */
	public static function onArticleEditUpdates( WikiPage &$wikiPage, PreparedEdit &$editInfo, bool $changed ) {
		global $wgAutoCreatePageMaxRecursion;

		$createPageData = $editInfo->output->getExtensionData( 'createPage' );
		if ( is_null( $createPageData ) ) {
			return true; // no pages to create
		}

		// Prevent pages to be created by pages that are created to avoid loops:
		$wgAutoCreatePageMaxRecursion--;

		$sourceTitle = $wikiPage->getTitle();
		$sourceTitleText = $sourceTitle->getPrefixedText();

		foreach ( $createPageData as $pageTitleText => $pageContentText ) {
			$pageTitle = Title::newFromText( $pageTitleText );

			if ( !is_null( $pageTitle ) && !$pageTitle->isKnown() && $pageTitle->canExist() ){
				$newWikiPage = new WikiPage( $pageTitle );
				$pageContent = ContentHandler::makeContent( $pageContentText, $sourceTitle );
				$newWikiPage->doEditContent( $pageContent,
					"Page created automatically by parser function on page [[$sourceTitleText]]" ); //TODO i18n
			}
		}

		// Reset state. Probably not needed since parsing is usually done here anyway:
		$editInfo->output->setExtensionData( 'createPage', null ); 
		$wgAutoCreatePageMaxRecursion++;
	}
}

