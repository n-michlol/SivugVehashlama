<?php

namespace Mediawiki\Extension\SivugVehashlama;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\TablePager;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\ILoadBalancer;

class ClasificationPager extends TablePager {

    private array $mHeaders = null;
    private LinkBatchFactory $linkBatchFactory;
    private LinkRenderer $linkRenderer;
    public array $mConds;
    private string $sizeType;
    private bool $complex, $noredirect;
    private int $size, $namespace;


    /**
     * @param ILoadBalancer $loadBalancer
     * @param IContextSource $context
     * @param LinkRenderer $linkRenderer
     * @param LinkBatchFactory $linkBatchFactory
	 * @param array $conds
	 * @param bool $complex
	 * @param int $namespace
	 * @param string $sizeType
	 * @param int $size
	 * @param bool $noredirect
	 */
    public function __construct( 
        ILoadBalancer $loadBalancer, 
        IContextSource $context, 
        LinkRenderer $linkRenderer,
        LinkBatchFactory $linkBatchFactory,
        array $conds,
        int $namespace,
        string $sizeType,
        int $size,
        bool $noredirect,
        bool $complex,
        ) {
        $this->mDb = $loadBalancer->getConnection( DB_REPLICA );
        parent::__construct( $context, $linkRenderer );
        $this->linkBatchFactory = $linkBatchFactory;
        $this->linkRenderer = $linkRenderer;
        $this->mConds = $conds;
        $this->complex = $complex;
        $this->namespace = $namespace;
        $this->sizeType = $sizeType;
        $this->size = $size;
        $this->noredirect = $noredirect;
        $this->mHeaders = [
                'page_title' => 'Page Title',
                'sv_complex' => 'Complex Status',
            ];
    }

    public function preprocessResults( $result ) {
		# Do a link batch query
		$lb = $this->linkBatchFactory->newLinkBatch();
        foreach ( $result as $row ) {
            $lb->add( $row->page_namespace, $row->page_title );
        }   
        $lb->execute();
    }


    protected function getFieldNames() {
        return $this->mHeaders;
    }

    public function getQueryInfo() {
        $db = $this->getDatabase();
        $conds = $this->mConds;

        // Always select all from sivugvehashlama_pages, and join page table for extra data
        // So, sivugvehashlama_pages is the "main" table, and page is joined
        if ( $this->complex === null ) {
            $conds[] = 'sv_complex IS NULL';
        } else {
            $conds[] = 'sv_complex = ' . ( $this->complex ? 1 : 0 );
        }
        if ( $this->sizeType == 'min' ) {
            $conds[] = 'page_len >= ' . $this->size;
        } elseif ( $this->sizeType == 'max' ) {
            $conds[] = 'page_len <= ' . $this->size;
        }
        if ( $this->noredirect ) {
            $conds[] = 'page_is_redirect = 0';
        }
        if ( $this->namespace !== null ) {
            $conds[] = 'page_namespace = ' . $db->addQuotes( $this->namespace );
        }

        return [
            'tables' => [
                'sivugvehashlama_pages',
                'page'
            ],
            'fields' => [
                'page_namespace',
                'page_title',
                'sv_complex',
                'page_len',
                'page_is_redirect',
                'page_id',
                'sv_id'
            ],
            'conds' => $conds,
            'join_conds' => [
                'page' => [
                    'LEFT JOIN',
                    [ 'sv_page_id = page_id' ]
                ]
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function isFieldSortable( $field ) {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function formatValue( $name, $value ){
        /** @var stdClass $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->linkRenderer;
        switch ( $name ) {
            case 'page_title':
                $title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				if ( !$title ) {
					$formatted = Html::element(
						'span',
						[ 'class' => 'mw-invalidtitle' ],
						Linker::getInvalidTitleDescription(
							$this->getContext(),
							$row->page_namespace,
							$row->page_title
						)
					);
				} else {
					$formatted = $linkRenderer->makeLink( $title );
				}
				if ( $row->page_len !== null ) {
					$formatted .= $this->getLanguage()->getDirMark() .
						' ' . Html::rawElement(
							'span',
							[ 'class' => 'mw-protectedpages-length' ],
							Linker::formatRevisionSize( $row->page_len )
						);
				}
                return $formatted;
            case 'sv_complex':
                $formatted = '';
                if ( $value === null ) {
                    $formatted = Html::rawElement(
                        'span',
                        [ 'class' => 'mw-sivugvehashlama-pending' ],
                        $this->msg( 'sivugvehashlama-pending' )->escaped()
                    );
                } elseif ( $value ) {
                    $formatted = Html::rawElement(
                        'span',
                        [ 'class' => 'mw-sivugvehashlama-complex' ],
                        $this->msg( 'sivugvehashlama-complex' )->escaped() 
                    );
                } else {
                    $formatted = Html::rawElement(
                        'span',
                        [ 'class' => 'mw-sivugvehashlama-simple' ],
                        $this->msg( 'sivugvehashlama-simple' )->escaped()
                    );
                }
                return $formatted;
        }
    }

    public function getDefaultSort(){
        return 'page_title';
    }

    public function getIndexField() {
        return 'page_title';
    }
}