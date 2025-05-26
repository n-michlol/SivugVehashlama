<?php

namespace Mediawiki\Extension\SivugVehashlama;

use MediaWiki\Pager\TablePager;

class ClasificationPager extends TablePager {

    protected function getFieldNames() {
        static $headers = null;
        if ( $headers === null ) {
            $headers = [
                'page_title' => 'Page Title',
                'sv_complex' => 'Complex Status',
            ];
        }
        return $headers;
    }

    public function getQueryInfo(){
        return [];
    }

    /**
     * @inheritDoc
     */
    public function isFieldSortable( $field ){
        return false;
    }

    /**
     * @inheritDoc
     */
    public function formatValue( $name, $value ){
        /** @var stdClass $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();
    }

    public function getDefaultSort(){
        return 'page_title';
    }

    public function getIndexField() {
        return 'page_title';
    }
}