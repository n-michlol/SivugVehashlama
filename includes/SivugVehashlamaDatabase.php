<?php

class SivugVehashlamaDatabase {

    public function getPendingPages($limit = null, $offset = 0) {
        $dbr = wfGetDB( DB_REPLICA );
        
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
        $dbr = wfGetDB( DB_REPLICA );
        
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
            [ 'page_id', 'user_id', 'timestamp' ],
            [ 'status' => $type ],
            __METHOD__,
            $options
        );
        
        $pages = [];
        foreach ( $result as $row ) {
            $pages[] = [
                'page_id' => $row->page_id,
                'user_id' => $row->user_id,
                'timestamp' => $row->timestamp
            ];
        }
        
        return [
            'pages' => $pages,
            'total' => $totalRows
        ];
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