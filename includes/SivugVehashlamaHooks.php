<?php

namespace MediaWiki\Extension\SivugVehashlama;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;

class SivugVehashlamaHooks implements LoadExtensionSchemaUpdatesHook, PageDeleteCompleteHook {

    /**
     * @inheritdoc
     */
    public function onLoadExtensionSchemaUpdates( $updater ) {
        $type = $updater->getDB()->getType();
        
        $updater->addExtensionTable(
            'sivugvehashlama_pages',
            __DIR__ . '../db/' . $type . '/tables-generated.sql'
        );
        
        return true;
    }

    /**
     * @inheritdoc
     */
    public function onPageDeleteComplete( $page, $user, $reason, $id, $content, $revision, $status ) {
        // Remove the page from the classification table
        $dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
        $dbw->delete(
            'sivugvehashlama_pages',
            [ 'sv_page_id' => $page->getId() ],
            __METHOD__
        );
        
        return true;
    }

}