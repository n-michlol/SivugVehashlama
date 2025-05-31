<?php

namespace MediaWiki\Extension\SivugVehashlama;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class SivugVehashlamaDatabase {

    private function getDbr(): IDatabase {
        return MediaWikiServices::getInstance()
            ->getConnectionProvider()
            ->getReplicaDatabase();
    }
    
    private function getDbw(): IDatabase {
        return MediaWikiServices::getInstance()
            ->getConnectionProvider()
            ->getPrimaryDatabase();
    }

    public function getPendingPages( $limit = null, $offset = 0 ): array {
        $dbr = $this->getDbr();
        
        $totalRows = $dbr->selectRowCount(
            'sivugvehashlama_pages',
            '*',
            [ 'sv_complex' => null ],
            __METHOD__
        );
        
        $options = [ 'ORDER BY' => 'sv_page_id' ];
        
        if ( $limit !== null ) {
            $options['LIMIT'] = $limit;
            $options['OFFSET'] = $offset;
        }
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'sv_page_id' ],
            [ 'sv_complex' => null ],
            __METHOD__,
            $options
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => (int)$row->sv_page_id
            ];
        }
        
        return [
            'pages' => $pages,
            'total' => (int)$totalRows
        ];
    }
    
    private function getClassifiedPages( string $type, $limit = null, $offset = 0 ): array {
        $dbr = $this->getDbr();
        $complex_value = ($type === 'complex') ? 1 : 0;
        
        $totalRows = $dbr->selectRowCount(
            'sivugvehashlama_pages',
            '*',
            [ 'sv_complex' => $complex_value ],
            __METHOD__
        );
        
        $options = [ 'ORDER BY' => 'sv_page_id DESC' ];
        
        if ( $limit !== null ) {
            $options['LIMIT'] = $limit;
            $options['OFFSET'] = $offset;
        }
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'sv_page_id' ],
            [ 'sv_complex' => $complex_value ],
            __METHOD__,
            $options
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => (int)$row->sv_page_id,
                'timestamp' => null // אין timestamp בטבלה הנוכחית
            ];
        }
        
        return [
            'pages' => $pages,
            'total' => (int)$totalRows
        ];
    }
    
    public function markPageAsSimple( int $pageId, int $userId ): void {
        $this->markPage( $pageId, 0 );
    }
    
    public function markPageAsComplex( int $pageId, int $userId ): void {
        $this->markPage( $pageId, 1 );
    }
    
    private function markPage( int $pageId, int $complex ): void {
        $dbw = $this->getDbw();
        
        $dbw->upsert(
            'sivugvehashlama_pages',
            [
                'sv_page_id' => $pageId,
                'sv_complex' => $complex
            ],
            [ 'sv_page_id' ],
            [ 'sv_complex' => $complex ],
            __METHOD__
        );
    }
    
    public function markPageAsDone( int $pageId ): void {
        $dbw = $this->getDbw();
        
        $dbw->delete(
            'sivugvehashlama_pages',
            [ 'sv_page_id' => $pageId ],
            __METHOD__
        );
    }
    
    public function addPage( int $pageId ): void {
        $dbw = $this->getDbw();
        
        $dbw->insert(
            'sivugvehashlama_pages',
            [
                'sv_page_id' => $pageId,
                'sv_complex' => null
            ],
            __METHOD__,
            [ 'IGNORE' ]
        );
    }
}