<?php

class SpecialSivugVehashlama extends SpecialPage {
    private $database;
    private $itemsPerPage;
    private $pageSizes = [20, 50, 100, 200];
    private $defaultPageSize = 50;
    
    public function __construct() {
        parent::__construct( 'SivugVehashlama', 'sivugvehashlama' );
        $this->database = new SivugVehashlamaDatabase();
    }
    
    public function execute( $subPage ) {
        $user = $this->getUser();
        $request = $this->getRequest();
        $output = $this->getOutput();
        
        if ( !$user->isAllowed( 'sivugvehashlama' ) ) {
            $output->addWikiMsg( 'sivugvehashlama-permission-denied' );
            return;
        }
        
        $this->setItemsPerPage( $request );
        
        $this->setHeaders();
        $output->addModules( 'ext.sivugVehashlama' );
        
        if ( $request->getVal( 'action' ) === 'viewsource' ) {
            $pageId = $request->getInt( 'pageid' );
            if ( $pageId ) {
                $this->showPageSource( $pageId );
                return;
            }
        }
        
        $this->handleActions( $request, $user );
        
        $this->displayInterface( $subPage );
    }
    
    private function setItemsPerPage( $request ) {
        $itemsPerPage = $request->getInt( 'limit', 0 );
        
        if ( !in_array( $itemsPerPage, $this->pageSizes ) ) {
            $userOptionsLookup = \MediaWiki\MediaWikiServices::getInstance()->getUserOptionsLookup();
            $itemsPerPage = $userOptionsLookup->getOption( $this->getUser(), 'sivugvehashlama-pagesize', $this->defaultPageSize );
            
            if ( !in_array( $itemsPerPage, $this->pageSizes ) ) {
                $itemsPerPage = $this->defaultPageSize;
            }
        } else {
            $userOptionsManager = \MediaWiki\MediaWikiServices::getInstance()->getUserOptionsManager();
            $userOptionsManager->setOption( $this->getUser(), 'sivugvehashlama-pagesize', $itemsPerPage );
            $userOptionsManager->saveOptions( $this->getUser() );
        }
        
        $this->itemsPerPage = $itemsPerPage;
    }
    
    private function handleActions( $request, $user ) {
        $action = $request->getVal( 'action' );
        $pageId = $request->getInt( 'pageid' );
        $title = Title::newFromID( $pageId );
        
        if ( !$action || !$pageId || !$title ) {
            return;
        }
        
        $logEntry = null;
        $logType = 'sivugvehashlama';
        
        switch ( $action ) {
            case 'marksimple':
                $this->database->markPageAsSimple( $pageId, $user->getId() );
                $logEntry = 'marksimple';
                break;
                
            case 'markcomplex':
                $this->database->markPageAsComplex( $pageId, $user->getId() );
                $logEntry = 'markcomplex';
                break;
                
            case 'simpledone':
                $this->database->markPageAsDone( $pageId );
                $logEntry = 'marksimpledone';
                break;
                
            case 'complexdone':
                $this->database->markPageAsDone( $pageId );
                $logEntry = 'markcomplexdone';
                break;
        }
        
        if ( $logEntry ) {
            $logger = new ManualLogEntry( $logType, $logEntry );
            $logger->setPerformer( $user );
            $logger->setTarget( $title );
            $logger->publish( $logger->insert() );
        }
    }
    
    private function displayInterface( $subPage ) {
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $this->addTabs( $subPage );
        
        $tab = $subPage ?: 'pending';
        
        switch ( $tab ) {
            case 'simple':
                $this->showSimplePages();
                break;
                
            case 'complex':
                $this->showComplexPages();
                break;
                
            case 'pending':
            default:
                $this->showPendingPages();
                break;
        }
    }
    
    private function addTabs( $currentTab ) {
        $output = $this->getOutput();
        $currentTab = $currentTab ?: 'pending';
        
        $tabs = [
            'pending' => 'sivugvehashlama-tab-pending',
            'simple' => 'sivugvehashlama-tab-simple',
            'complex' => 'sivugvehashlama-tab-complex'
        ];
        
        $navigation = [];
        foreach ( $tabs as $tab => $msg ) {
            $cssClass = ( $currentTab === $tab ) ? 'selected' : '';
            $tabUrl = $this->getPageTitle( $tab )->getLocalURL();
            $tabText = $this->msg( $msg )->text();
            
            $navigation[] = Html::rawElement(
                'li',
                [ 'class' => $cssClass ],
                Html::element( 'a', [ 'href' => $tabUrl ], $tabText )
            );
        }
        
        $navHtml = Html::rawElement( 'ul', [], implode( '', $navigation ) );
        $output->addHTML( Html::rawElement( 'div', [ 'class' => 'sivug-tabs' ], $navHtml ) );
    }

    private function showPendingPages() {
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $offset = $request->getInt( 'offset', 0 );
        
        $pagesData = $this->database->getPendingPages( $this->itemsPerPage, $offset );
        $pages = $pagesData['pages'];
        $total = $pagesData['total'];
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-pending' )->text() ) );
        
        $this->addPageSizeSelector( 'pending' );
        
        if ( count( $pages ) === 0 ) {
            $output->addHTML( Html::element( 'p', [], $this->msg( 'sivugvehashlama-no-items' )->text() ) );
            return;
        }
        
        $html = Html::openElement( 'div', [ 'class' => 'sivug-pending-list' ] );
        
        foreach ( $pages as $page ) {
            $title = Title::newFromID( $page['page_id'] );
            if ( !$title ) {
                continue;
            }
            
            $html .= $this->formatPendingItem( $title, $page['page_id'] );
        }
        
        $html .= Html::closeElement( 'div' );
        $output->addHTML( $html );
        
        $this->addPaginationLinks( 'pending', $offset, $total );
    }
    
    private function formatPendingItem( $title, $pageId ) {
        $markSimpleUrl = $this->getPageTitle()->getLocalURL( [
            'action' => 'marksimple',
            'pageid' => $pageId
        ] );
        
        $markComplexUrl = $this->getPageTitle()->getLocalURL( [
            'action' => 'markcomplex',
            'pageid' => $pageId
        ] );
        
        $html = Html::openElement( 'div', [ 'class' => 'sivug-item' ] );
        
        $html .= Html::rawElement( 'div', [ 'class' => 'sivug-item-title' ],
            Html::rawElement( 'h3', [], 
                Html::rawElement( 'a', 
                    [ 'href' => $title->getLocalURL() ],
                    $title->getPrefixedText()
                )
            )
        );
        
        $html .= Html::openElement( 'div', [ 'class' => 'sivug-item-actions' ] );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => '#',
                'class' => 'sivug-button sivug-view-source',
                'data-pageid' => $pageId,
                'data-title' => $title->getPrefixedText()
            ],
            $this->msg( 'sivugvehashlama-view-source' )->text()
        );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $markSimpleUrl,
                'class' => 'sivug-button sivug-mark-simple'
            ],
            $this->msg( 'sivugvehashlama-mark-simple' )->text()
        );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $markComplexUrl,
                'class' => 'sivug-button sivug-mark-complex'
            ],
            $this->msg( 'sivugvehashlama-mark-complex' )->text()
        );
        
        $html .= Html::closeElement( 'div' );
        $html .= Html::closeElement( 'div' );
        
        return $html;
    }
    
    private function showSimplePages() {
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $offset = $request->getInt( 'offset', 0 );
        
        $pagesData = $this->database->getSimplePages( $this->itemsPerPage, $offset );
        $pages = $pagesData['pages'];
        $total = $pagesData['total'];
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-simple' )->text() ) );
        
        $this->addPageSizeSelector( 'simple' );
        
        if ( count( $pages ) === 0 ) {
            $output->addHTML( Html::element( 'p', [], $this->msg( 'sivugvehashlama-no-items' )->text() ) );
            return;
        }
        
        $this->showClassifiedPages( $pages, 'simple' );
        
        $this->addPaginationLinks( 'simple', $offset, $total );
    }
    
    private function showComplexPages() {
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $offset = $request->getInt( 'offset', 0 );
        
        $pagesData = $this->database->getComplexPages( $this->itemsPerPage, $offset );
        $pages = $pagesData['pages'];
        $total = $pagesData['total'];
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-complex' )->text() ) );
        
        $this->addPageSizeSelector( 'complex' );
        
        if ( count( $pages ) === 0 ) {
            $output->addHTML( Html::element( 'p', [], $this->msg( 'sivugvehashlama-no-items' )->text() ) );
            return;
        }
        
        $this->showClassifiedPages( $pages, 'complex' );
        
        $this->addPaginationLinks( 'complex', $offset, $total );
    }
    
    private function showClassifiedPages( $pages, $type ) {
        $output = $this->getOutput();
        
        $html = Html::openElement( 'table', [ 'class' => 'wikitable sivug-table' ] );
        
        $html .= Html::openElement( 'tr' );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama' )->text() );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama-classification-date' )->text() );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama-action' )->text() );
        $html .= Html::closeElement( 'tr' );
        
        foreach ( $pages as $page ) {
            $title = Title::newFromID( $page['page_id'] );
            if ( !$title ) {
                continue;
            }
            
            $markDoneUrl = $this->getPageTitle()->getLocalURL( [
                'action' => $type . 'done',
                'pageid' => $page['page_id']
            ] );
            
            $html .= Html::openElement( 'tr' );
            
            $html .= Html::rawElement( 'td', [],
                Html::rawElement( 'a', 
                    [ 'href' => $title->getLocalURL() ],
                    $title->getPrefixedText()
                )
            );
            
            $html .= Html::element( 'td', [], 
                $this->getLanguage()->timeanddate( wfTimestamp( TS_MW, $page['timestamp'] ) ) 
            );
            
            $html .= Html::rawElement( 'td', [],
                Html::rawElement( 'a', 
                    [ 
                        'href' => $markDoneUrl,
                        'class' => 'sivug-button sivug-mark-done'
                    ],
                    $this->msg( 'sivugvehashlama-mark-done' )->text()
                )
            );
            
            $html .= Html::closeElement( 'tr' );
        }
        
        $html .= Html::closeElement( 'table' );
        $output->addHTML( $html );
    }
    
    private function addPageSizeSelector( $tab ) {
        $output = $this->getOutput();
        $request = $this->getRequest();
        
        $offset = $request->getInt( 'offset', 0 );
        
        $html = Html::openElement( 'div', [ 'class' => 'sivug-page-size-selector' ] );
        $html .= Html::element( 'span', [], $this->msg( 'sivugvehashlama-page-size' )->text() . ': ' );
        
        foreach ( $this->pageSizes as $size ) {
            $class = ( $size == $this->itemsPerPage ) ? 'sivug-page-size-current' : '';
            
            $html .= Html::rawElement( 'a',
                [
                    'href' => $this->getPageTitle( $tab )->getLocalURL( [
                        'limit' => $size,
                        'offset' => $offset
                    ] ),
                    'class' => 'sivug-page-size ' . $class
                ],
                $size
            );
            
            $html .= ' ';
        }
        
        $html .= Html::closeElement( 'div' );
        $output->addHTML( $html );
    }
    
    private function addPaginationLinks( $tab, $offset, $total ) {
        $output = $this->getOutput();
        
        if ( $total <= $this->itemsPerPage ) {
            return;
        }
        
        $html = Html::openElement( 'div', [ 'class' => 'sivug-pagination' ] );
        
        $currentPage = floor( $offset / $this->itemsPerPage ) + 1;
        $totalPages = ceil( $total / $this->itemsPerPage );
        
        if ( $offset > 0 ) {
            $prevOffset = max( 0, $offset - $this->itemsPerPage );
            $html .= Html::rawElement( 'a',
                [
                    'href' => $this->getPageTitle( $tab )->getLocalURL( [
                        'limit' => $this->itemsPerPage,
                        'offset' => $prevOffset
                    ] ),
                    'class' => 'sivug-page-link sivug-prev'
                ],
                '&lt; ' . $this->msg( 'sivugvehashlama-prev' )->text()
            );
        }
        
        $html .= Html::element( 'span',
            [ 'class' => 'sivug-page-info' ],
            $this->msg( 'sivugvehashlama-page-of', $currentPage, $totalPages )->text()
        );
        
        if ( $offset + $this->itemsPerPage < $total ) {
            $nextOffset = $offset + $this->itemsPerPage;
            $html .= Html::rawElement( 'a',
                [
                    'href' => $this->getPageTitle( $tab )->getLocalURL( [
                        'limit' => $this->itemsPerPage,
                        'offset' => $nextOffset
                    ] ),
                    'class' => 'sivug-page-link sivug-next'
                ],
                $this->msg( 'sivugvehashlama-next' )->text() . ' &gt;'
            );
        }
        
        $html .= Html::closeElement( 'div' );
        $output->addHTML( $html );
    }
    
    public function showPageSource( $pageId ) {
        $output = $this->getOutput();
        $title = Title::newFromID( $pageId );
        
        if ( !$title ) {
            return;
        }
        
        $output->setPageTitle( $this->msg( 'sivugvehashlama-view-source' )->text() . ': ' . $title->getPrefixedText() );
        
        $wikiPageFactory = \MediaWiki\MediaWikiServices::getInstance()->getWikiPageFactory();
        $page = $wikiPageFactory->newFromTitle( $title );
        $content = $page->getContent();
        
        if ( !$content ) {
            return;
        }
        
        $text = $content->getNativeData();
        
        $output->addHTML(
            Html::element( 'pre', [ 'class' => 'sivug-source' ], $text )
        );
        
        $html = Html::openElement( 'div', [ 'class' => 'sivug-source-actions' ] );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $this->getPageTitle()->getLocalURL(),
                'class' => 'sivug-button'
            ],
            $this->msg( 'cancel' )->text()
        );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $this->getPageTitle()->getLocalURL( [
                    'action' => 'marksimple',
                    'pageid' => $pageId
                ] ),
                'class' => 'sivug-button sivug-mark-simple'
            ],
            $this->msg( 'sivugvehashlama-mark-simple' )->text()
        );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $this->getPageTitle()->getLocalURL( [
                    'action' => 'markcomplex',
                    'pageid' => $pageId
                ] ),
                'class' => 'sivug-button sivug-mark-complex'
            ],
            $this->msg( 'sivugvehashlama-mark-complex' )->text()
        );
        
        $html .= Html::closeElement( 'div' );
        $output->addHTML( $html );
    }

    protected function getGroupName() {
        return 'other';
    }
}