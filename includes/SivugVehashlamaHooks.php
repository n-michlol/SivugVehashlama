<?php

namespace MediaWiki\Extension\SivugVehashlama;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SivugVehashlamaHooks implements LoadExtensionSchemaUpdatesHook {

    /**
     * @inheritdoc
     */
    public function onLoadExtensionSchemaUpdates( $updater ) {
        $sqlPath = __DIR__ . '/../sql';
        
        $updater->addExtensionTable(
            'sivugvehashlama_pages',
            "$sqlPath/sivugvehashlama_pages.sql"
        );
        
        return true;
    }

}