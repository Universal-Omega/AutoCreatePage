<?php

use MediaWiki\Edit\PreparedEdit;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RenderedRevision;

class AutoCreatePageHooks {

	/**
	 * @param Title $title
	 * @param RenderedRevision $renderedRevision
	 * @param array &$updates
	 */
	public static function onRevisionDataUpdates( Title $title, RenderedRevision $renderedRevision, array &$updates ) {
		global $wgAutoCreatePageMaxRecursion;

		$output = $renderedRevision->getRevisionParserOutput();
		$createPageData = $output->getExtensionData( 'createPage' );

		if ( is_null( $createPageData ) ) {
			return true; // no pages to create
		}
		// Prevent pages to be created by pages that are created to avoid loops:
		$wgAutoCreatePageMaxRecursion--;

		$sourceTitleText = $title->getPrefixedText();

		foreach ( $createPageData as $pageTitleText => $pageContentText ) {
			$pageTitle = Title::newFromText( $pageTitleText );

			if ( !is_null( $pageTitle ) && !$pageTitle->isKnown() && $pageTitle->canExist() ){
				$newWikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $pageTitle );
				$pageContent = ContentHandler::makeContent( $pageContentText, $pageTitle );

				// WikiPage:doEditContent has been removed, page update is being refactored.
				// please check out https://github.com/wikimedia/mediawiki/blob/master/docs/pageupdater.md
				// the following takes care of this change for REL1_39.
				$updater = $newWikiPage->newPageUpdater( $renderedRevision->getRevision()->getUser() );
				$updater->setContent( \MediaWiki\Revision\SlotRecord::MAIN, $pageContent );
				$updater->setRcPatrolStatus( RecentChange::PRC_PATROLLED );
				$comment = CommentStoreComment::newUnsavedComment(
					wfMessage( 'autocreatepage-revision-comment', $sourceTitleText )->inContentLanguage()->text()
				);
				$updater->saveRevision( $comment );
			}
		}

		// Reset state. Probably not needed since parsing is usually done here anyway:
		$output->setExtensionData( 'createPage', null );
		$wgAutoCreatePageMaxRecursion++;
	}

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'createPage', [ __CLASS__, 'createPageIfNotExisting' ] );
	}

	/**
	 * @param Parser $parser
	 * @param string $newPageTitleText
	 * @param string $newPageContent
	 * @return string
	 *
	 * @throws MWException
	 */
	public static function createPageIfNotExisting( Parser $parser, string $newPageTitleText, string $newPageContent ) {
		global $wgAutoCreatePageMaxRecursion, $wgAutoCreatePageIgnoreEmptyTitle,
			$wgAutoCreatePageNamespaces, $wgContentNamespaces, $wgAutoCreatePageIgnoreEmptyContent;

		if ( $wgAutoCreatePageMaxRecursion <= 0 ) {
			return wfMessage( 'autocreatepage-error-recursion-level-exceeded' )->inContentLanguage()->text();
		}

		if ( empty( $newPageTitleText ) ) {
			if ( $wgAutoCreatePageIgnoreEmptyTitle === false ) {
				return wfMessage( 'autocreatepage-error-empty-title' )->inContentLanguage()->text();
			} else {
				return '';
			}
		}

		if ( !isset( $newPageContent ) ) {
			if ( $wgAutoCreatePageIgnoreEmptyContent === false ) {
				return wfMessage( 'autocreatepage-error-empty-content' )->inContentLanguage()->text();
			} else {
				return '';
			}
		}

		$namespaces = $wgAutoCreatePageNamespaces ?: $wgContentNamespaces;
		// Create pages only if the page calling the parser function is within defined namespaces
		if ( !in_array( $parser->getPage()->getNamespace(), $namespaces ) ) {
			return '';
		}

		// Get the raw text of $newPageContent as it was before stripping <nowiki>:
		$newPageContent = $parser->getStripState()->unstripNoWiki( $newPageContent );

		// Store data in the parser output for later use:
		$createPageData = $parser->getOutput()->getExtensionData( 'createPage' );
		if ( is_null( $createPageData ) ) {
			$createPageData = [];
		}
		$createPageData[$newPageTitleText] = $newPageContent;
		$parser->getOutput()->setExtensionData( 'createPage', $createPageData );

		return '';
	}
}

