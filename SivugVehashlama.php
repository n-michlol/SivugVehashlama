<?php

if ( function_exists( 'wfLoadExtension' ) ) {
    wfLoadExtension( 'SivugVehashlama' );
    $wgMessagesDirs['SivugVehashlama'] = __DIR__ . '/i18n';
    $wgExtensionMessagesFiles['SivugVehashlamaAlias'] = __DIR__ . '/SivugVehashlama.alias.php';
    $wgExtensionMessagesFiles['SivugVehashlamaMagic'] = __DIR__ . '/SivugVehashlama.i18n.magic.php';
    return;
} else {
    die( 'This extension requires MediaWiki 1.43+' );
}