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
    
    function setupSourceViewers() {
        $( '.sivug-view-source' ).on( 'click', function ( e ) {
            e.preventDefault();
            
            var pageId = $( this ).data( 'pageid' );
            var pageTitle = $( this ).data( 'title' );
            var $item = $( this ).closest( '.sivug-item' );
            
            if ( $item.find( '.sivug-source-container' ).length > 0 ) {
                $item.find( '.sivug-source-container' ).toggle();
                return;
            }
            
            var $loadingIndicator = $( '<div>' )
                .addClass( 'sivug-loading' )
                .text( mw.msg( 'sivugvehashlama-loading' ) || 'טוען...' );
            
            $item.append( $loadingIndicator );
            
            new mw.Api().get( {
                action: 'query',
                prop: 'revisions',
                rvprop: 'content',
                pageids: pageId,
                formatversion: '2'
            } ).done( function ( data ) {
                $loadingIndicator.remove();
                
                if ( !data.query || !data.query.pages || !data.query.pages[ 0 ] || 
                     !data.query.pages[ 0 ].revisions || !data.query.pages[ 0 ].revisions[ 0 ] ) {
                    mw.notify( mw.msg( 'sivugvehashlama-error-loading' ) || 'שגיאה בטעינת קוד המקור', { type: 'error' } );
                    return;
                }
                
                var content = data.query.pages[ 0 ].revisions[ 0 ].content;
                var $container = $( '<div>' ).addClass( 'sivug-source-container' );
                
                $container.append( 
                    $( '<h4>' )
                        .text( pageTitle || data.query.pages[ 0 ].title )
                );
                
                $container.append(
                    $( '<pre>' )
                        .addClass( 'sivug-source' )
                        .text( content )
                );
                
                var $actions = $( '<div>' ).addClass( 'sivug-source-actions' );
                
                $actions.append(
                    $( '<a>' )
                        .addClass( 'sivug-button sivug-close-source' )
                        .text( mw.msg( 'sivugvehashlama-close' ) || 'סגור' )
                        .on( 'click', function() {
                            $container.slideUp( 'fast' );
                        } )
                );
                
                var $markSimple = $( '.sivug-mark-simple', $item ).clone();
                var $markComplex = $( '.sivug-mark-complex', $item ).clone();
                
                $actions.append( $markSimple, $markComplex );
                $container.append( $actions );
                
                $item.append( $container );
                $container.hide().slideDown( 'fast' );
                
                addConfirmations();
            } ).fail( function () {
                $loadingIndicator.remove();
                mw.notify( mw.msg( 'sivugvehashlama-error-loading' ) || 'שגיאה בטעינת קוד המקור', { type: 'error' } );
            } );
        } );
    }
    
    function init() {
        addConfirmations();
        setupSourceViewers();
    }
    
    $( init );
    
} )( mediaWiki, jQuery );