( function ( mw, $ ) {
    'use strict';
    
    function addConfirmations() {
        $( '.sivug-mark-simple' ).on( 'click', function ( e ) {
            if ( !confirm( mw.msg( 'sivugvehashlama-confirm-simple' ) ) ) {
                e.preventDefault();
                return false;
            }
        } );
        
        $( '.sivug-mark-complex' ).on( 'click', function ( e ) {
            if ( !confirm( mw.msg( 'sivugvehashlama-confirm-complex' ) ) ) {
                e.preventDefault();
                return false;
            }
        } );
        
        $( '.sivug-mark-done' ).on( 'click', function ( e ) {
            if ( !confirm( mw.msg( 'sivugvehashlama-confirm-done' ) ) ) {
                e.preventDefault();
                return false;
            }
        } );
    }
    
    function init() {
        addConfirmations();
    }
    
    $( init );
    
} )( mediaWiki, jQuery );