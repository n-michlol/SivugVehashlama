<?php

class SpecialSivugVehashlama extends SpecialPage {
    private $database;
    
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
        
        $this->setHeaders();
        $output->addModules( 'ext.sivugVehashlama' );
        
        $this->handleActions( $request, $user );
        
        $this->displayInterface( $subPage );
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
        $pages = $this->database->getPendingPages();
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-pending' )->text() ) );
        
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
    }
    
    private function formatPendingItem( $title, $pageId ) {
        $viewSourceUrl = $this->getPageTitle()->getLocalURL( [
            'action' => 'viewsource',
            'pageid' => $pageId
        ] );
        
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
            Html::element( 'h3', [], $title->getPrefixedText() )
        );
        
        $html .= Html::openElement( 'div', [ 'class' => 'sivug-item-actions' ] );
        
        $html .= Html::rawElement( 'a', 
            [ 
                'href' => $viewSourceUrl,
                'class' => 'sivug-button sivug-view-source'
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
        $pages = $this->database->getSimplePages();
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-simple' )->text() ) );
        
        if ( count( $pages ) === 0 ) {
            $output->addHTML( Html::element( 'p', [], $this->msg( 'sivugvehashlama-no-items' )->text() ) );
            return;
        }
        
        $this->showClassifiedPages( $pages, 'simple' );
    }
    
    private function showComplexPages() {
        $output = $this->getOutput();
        $pages = $this->database->getComplexPages();
        
        $output->addHTML( Html::element( 'h2', [], $this->msg( 'sivugvehashlama-complex' )->text() ) );
        
        if ( count( $pages ) === 0 ) {
            $output->addHTML( Html::element( 'p', [], $this->msg( 'sivugvehashlama-no-items' )->text() ) );
            return;
        }
        
        $this->showClassifiedPages( $pages, 'complex' );
    }
    
    private function showClassifiedPages( $pages, $type ) {
        $output = $this->getOutput();
        
        $html = Html::openElement( 'table', [ 'class' => 'wikitable sivug-table' ] );
        
        $html .= Html::openElement( 'tr' );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama' )->text() );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama-classified-by' )->text() );
        $html .= Html::element( 'th', [], $this->msg( 'sivugvehashlama-classification-date' )->text() );
        $html .= Html::element( 'th', [], $this->msg( 'action' )->text() );
        $html .= Html::closeElement( 'tr' );
        
        foreach ( $pages as $page ) {
            $title = Title::newFromID( $page['page_id'] );
            if ( !$title ) {
                continue;
            }
            
            $user = User::newFromId( $page['user_id'] );
            $userName = $user ? $user->getName() : '';
            
            $markDoneUrl = $this->getPageTitle()->getLocalURL( [
                'action' => $type . 'done',
                'pageid' => $page['page_id']
            ] );
            
            $html .= Html::openElement( 'tr' );
            
            $html .= Html::rawElement( 'td', [],
                Html::element( 'a', 
                    [ 'href' => $title->getLocalURL() ],
                    $title->getPrefixedText()
                )
            );
            
            $html .= Html::element( 'td', [], $userName );
            
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
    
    public function showPageSource( $pageId ) {
        $output = $this->getOutput();
        $title = Title::newFromID( $pageId );
        
        if ( !$title ) {
            return;
        }
        
        $output->setPageTitle( $this->msg( 'sivugvehashlama-view-source' )->text() . ': ' . $title->getPrefixedText() );
        
        $page = WikiPage::factory( $title );
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
    
    public function onSpecialPageBeforeExecute( $subPage ) {
        $request = $this->getRequest();
        
        if ( $request->getVal( 'action' ) == 'viewsource' ) {
            $pageId = $request->getInt( 'pageid' );
            if ( $pageId ) {
                $this->showPageSource( $pageId );
                return false;
            }
        }
        
        return true;
    }
}