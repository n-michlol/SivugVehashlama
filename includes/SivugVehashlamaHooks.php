<?php

namespace MediaWiki\Extension\SivugVehashlama;

use DatabaseUpdater;

class SivugVehashlamaHooks {

    public static function onLoadExtensionSchemaUpdates( $updater ) {
        $sqlPath = __DIR__ . '/../sql';
        
        $updater->addExtensionTable(
            'sivugvehashlama_pages',
            "$sqlPath/sivugvehashlama_pages.sql"
        );
        
        return true;
    }

    public static function onLogActions( &$logActions ) {
        $actions = [
            'marksimple',
            'markcomplex',
            'marksimpledone',
            'markcomplexdone'
        ];
        
        foreach ( $actions as $action ) {
            $logActions['sivugvehashlama/' . $action] = 'logentry-sivugvehashlama-' . $action;
        }
        
        return true;
    }

    public static function onLogFormatter( $type, $action, $entry, &$formatter ) {
        if ( $type === 'sivugvehashlama' ) {
            $formatter = new SivugVehashlamaLogFormatter( $entry );
            return false;
        }
        return true;
    }
}