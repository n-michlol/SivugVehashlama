<?php

namespace MediaWiki\Extension\SivugVehashlama;

use MediaWiki\MediaWikiServices;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SivugVehashlamaDatabase {

    private function getDbr() {
        return MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
    }
    
    private function getDbw() {
        return MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
    }

    public function getPendingPages($limit = null, $offset = 0) {
        $dbr = $this->getDbr();
        
        $totalRows = $dbr->selectRowCount(
            'sivugvehashlama_pages',
            '*',
            [ 'status' => 'pending' ],
            __METHOD__
        );
        
        $options = [ 'ORDER BY' => 'page_id' ];
        
        if ($limit !== null) {
            $options['LIMIT'] = $limit;
            $options['OFFSET'] = $offset;
        }
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'page_id' ],
            [ 'status' => 'pending' ],
            __METHOD__,
            $options
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => $row->page_id
            ];
        }
        
        return [
            'pages' => $pages,
            'total' => $totalRows
        ];
    }
    
    public function getSimplePages($limit = null, $offset = 0) {
        return $this->getClassifiedPages( 'simple', $limit, $offset );
    }
    
    public function getComplexPages($limit = null, $offset = 0) {
        return $this->getClassifiedPages( 'complex', $limit, $offset );
    }
    
    private function getClassifiedPages( $type, $limit = null, $offset = 0 ) {
        $dbr = $this->getDbr();
        
        $totalRows = $dbr->selectRowCount(
            'sivugvehashlama_pages',
            '*',
            [ 'status' => $type ],
            __METHOD__
        );
        
        $options = [ 'ORDER BY' => 'timestamp DESC' ];
        
        if ($limit !== null) {
            $options['LIMIT'] = $limit;
            $options['OFFSET'] = $offset;
        }
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'page_id', 'timestamp' ],
            [ 'status' => $type ],
            __METHOD__,
            $options
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => $row->page_id,
                'timestamp' => $row->timestamp
            ];
        }
        
        return [
            'pages' => $pages,
            'total' => $totalRows
        ];
    }
    
    public function markPageAsSimple( $pageId, $userId ) {
        $this->markPage( $pageId, 'simple' );
    }
    
    public function markPageAsComplex( $pageId, $userId ) {
        $this->markPage( $pageId, 'complex' );
    }
    
    public function markPageAsDone( $pageId ) {
        $dbw = $this->getDbw();
        
        $dbw->update(
            'sivugvehashlama_pages',
            [ 'status' => 'done' ],
            [ 'page_id' => $pageId ],
            __METHOD__
        );
    }
    
    private function markPage( $pageId, $status ) {
        $dbw = $this->getDbw();
        
        $dbw->update(
            'sivugvehashlama_pages',
            [
                'status' => $status,
                'timestamp' => $dbw->timestamp()
            ],
            [ 'page_id' => $pageId ],
            __METHOD__
        );
    }
    
    public function addPage( $pageId ) {
        $dbw = $this->getDbw();
        
        $dbw->insert(
            'sivugvehashlama_pages',
            [
                'page_id' => $pageId,
                'status' => 'pending',
                'timestamp' => $dbw->timestamp()
            ],
            __METHOD__,
            [ 'IGNORE' ]
        );
    }
}