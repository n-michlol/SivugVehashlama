<?php

class SivugVehashlamaDatabase {

    public function getPendingPages() {
        $dbr = wfGetDB( DB_REPLICA );
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'page_id' ],
            [ 'status' => 'pending' ],
            __METHOD__,
            [ 'ORDER BY' => 'page_id' ]
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => $row->page_id
            ];
        }
        
        return $pages;
    }
    
    public function getSimplePages() {
        return $this->getClassifiedPages( 'simple' );
    }
    
    public function getComplexPages() {
        return $this->getClassifiedPages( 'complex' );
    }
    
    private function getClassifiedPages( $type ) {
        $dbr = wfGetDB( DB_REPLICA );
        
        $result = $dbr->select(
            'sivugvehashlama_pages',
            [ 'page_id', 'user_id', 'timestamp' ],
            [ 'status' => $type ],
            __METHOD__,
            [ 'ORDER BY' => 'timestamp DESC' ]
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => $row->page_id,
                'user_id' => $row->user_id,
                'timestamp' => $row->timestamp
            ];
        }
        
        return $pages;
    }
    
    public function markPageAsSimple( $pageId, $userId ) {
        $this->markPage( $pageId, $userId, 'simple' );
    }
    
    public function markPageAsComplex( $pageId, $userId ) {
        $this->markPage( $pageId, $userId, 'complex' );
    }
    
    public function markPageAsDone( $pageId ) {
        $dbw = wfGetDB( DB_PRIMARY );
        
        $dbw->update(
            'sivugvehashlama_pages',
            [ 'status' => 'done' ],
            [ 'page_id' => $pageId ],
            __METHOD__
        );
    }
    
    private function markPage( $pageId, $userId, $status ) {
        $dbw = wfGetDB( DB_PRIMARY );
        
        $dbw->update(
            'sivugvehashlama_pages',
            [
                'status' => $status,
                'user_id' => $userId,
                'timestamp' => $dbw->timestamp()
            ],
            [ 'page_id' => $pageId ],
            __METHOD__
        );
    }
    
    public function addPage( $pageId ) {
        $dbw = wfGetDB( DB_PRIMARY );
        
        $dbw->insert(
            'sivugvehashlama_pages',
            [
                'page_id' => $pageId,
                'status' => 'pending',
                'user_id' => 0,
                'timestamp' => $dbw->timestamp()
            ],
            __METHOD__,
            [ 'IGNORE' ]
        );
    }
}