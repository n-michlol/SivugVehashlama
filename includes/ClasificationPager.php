<?php

namespace Mediawiki\Extension\SivugVehashlama;

use LogEventsList;
use LogPage;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\CommentStore\CommentStore;
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
    private CommentStore $commentStore;
    private RowCommentFormatter $rowCommentFormatter;
    private array $formattedComments = [];
    public array $mConds;
    private string $sizeType;
    private bool $noredirect;
    private int $size, $namespace;
    private string $status;


    /**
     * @param ILoadBalancer $loadBalancer
     * @param IContextSource $context
     * @param LinkRenderer $linkRenderer
     * @param LinkBatchFactory $linkBatchFactory
     * @param CommentStore $commentStore
     * @param RowCommentFormatter $rowCommentFormatter
	 * @param array $conds
     * @param int $namespace
     * @param string $sizeType
     * @param int $size
     * @param bool $noredirect
	 * @param string $status
	 */
    public function __construct( 
        ILoadBalancer $loadBalancer, 
        IContextSource $context, 
        LinkRenderer $linkRenderer,
        LinkBatchFactory $linkBatchFactory,
        CommentStore $commentStore,
        RowCommentFormatter $rowCommentFormatter,
        array $conds,
        int $namespace,
        string $sizeType,
        int $size,
        bool $noredirect,
        string $status,
        ) {
        $this->mDb = $loadBalancer->getConnection( DB_REPLICA );
        parent::__construct( $context, $linkRenderer );
        $this->linkBatchFactory = $linkBatchFactory;
        $this->linkRenderer = $linkRenderer;
        $this->commentStore = $commentStore;
        $this->rowCommentFormatter = $rowCommentFormatter;
        $this->mConds = $conds;
        $this->status = $status;
        $this->namespace = $namespace;
        $this->sizeType = $sizeType;
        $this->size = $size;
        $this->noredirect = $noredirect;
        $this->mHeaders = [
            // TODO: Add the header names for each column
                'page_title' => '',
                'sv_complex' => '',
                'actor_user' => '',
                'log_comment' => '',
                'log_timestamp' => '',
            ];
    }

    public function preprocessResults( $result ) {
		# Do a link batch query
		$lb = $this->linkBatchFactory->newLinkBatch();
        foreach ( $result as $row ) {
            $lb->add( $row->page_namespace, $row->page_title );
        }   
        $lb->execute();
        $this->formattedComments = $this->rowCommentFormatter->formatRows( $result, 'log_comment' );
    }


    protected function getFieldNames() {
        return $this->mHeaders;
    }

    public function getQueryInfo() {
        $db = $this->getDatabase();
        $conds = $this->mConds;
        $commentQuery = $this->commentStore->getJoin( 'log_comment' );

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
        
        if ( $this->status === 'simple' ) {
            $conds[] = 'sv_complex = 0';
        } elseif ( $this->status === 'complex' ) {
            $conds[] = 'sv_complex = 1';
        } elseif ( $this->status === 'pending' ) {
            $conds[] = 'sv_complex IS NULL';
        } elseif ( $this->status === 'done' ) {
            // if we are looking for done pages, this is coplicated, because theywont appear in the sivugvehashlama_pages table
            // so the tables and query will be different
            // first we need to get from log_search table the pages that have ls_field = 'sv_id' and the ls_value from that lines which should be the sv_id is null in sivugvehashlama_pages
            // then get the log_page which is the page_id and get that pages
            return [
                'tables' => [
                    'log_search',
                    'sivugvehashlama_pages',
                    'page',
                    'logparen' => [ 'logging', 'actor' ] + $commentQuery['tables'],
                ],
                'fields' => [
                    'page_namespace',
                    'page_title',
                    'page_len',
                    'page_id',
                    'MAX(log_timestamp) AS log_timestamp', // Add this line
                    'log_deleted',
                    'actor_name',
                    'actor_user'
                ] + $commentQuery['fields'],
                'conds' => $conds,
                'join_conds' => [
                    'log_search' => [
                        'JOIN', [
                            'ls_field' => 'sv_id'
                        ]
                    ],
                    'logparen' => [
                        'JOIN', [
                            'ls_log_id = log_id'
                        ]
                    ],
                    'page' => [
                        'JOIN', [
                            'log_page = page_id'
                        ]
                    ],
                    'sivugvehashlama_pages' => [
                        'LEFT JOIN', [
                            $db->buildStringCast( 'sv_id' ) . ' = ls_value'
                        ]
                    ],
                    'actor' => [
                        'JOIN', [
                            'actor_id = log_actor'
                        ]
                    ]
                ] + $commentQuery['joins'],
                'options' => [
                    'GROUP BY' => 'page_id' // Add this line
                ]
            ];
        }
       
        return [
            'tables' => [
                'sivugvehashlama_pages',
                'page',
                'log_search',
                'logparen' => [ 'logging', 'actor' ] + $commentQuery['tables'],
            ],
            'fields' => [
                'page_namespace',
                'page_title',
                'sv_complex',
                'page_len',
                'page_id',
                'sv_id',
                'MAX(log_timestamp) AS log_timestamp', 
				'log_deleted',
				'actor_name',
				'actor_user'
			] + $commentQuery['fields'],
            'conds' => $conds,
            'join_conds' => [
                'page' => [
                    'LEFT JOIN',
                    [ 'sv_page_id = page_id' ]
                ],
                'log_search' => [
					'LEFT JOIN', [
						'ls_field' => 'sv_id', 'ls_value = ' . $db->buildStringCast( 'sv_id' )
					]
				],
				'logparen' => [
					'LEFT JOIN', [
						'ls_log_id = log_id'
					]
				],
				'actor' => [
					'JOIN', [
						'actor_id=log_actor'
					]
				]
            ] + $commentQuery['joins'],
            'options' => [
				'GROUP BY' => 'page_id' 
			]
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
        $formatted = '';
        switch ( $name ) {
            case 'actor_user':
				// when timestamp is null, this is a old protection row
				if ( $row->log_timestamp === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-protectedpages-unknown' ],
						$this->msg( 'protectedpages-unknown-performer' )->escaped()
					);
				} else {
					$username = $row->actor_name;
					if ( LogEventsList::userCanBitfield(
						$row->log_deleted,
						LogPage::DELETED_USER,
						$this->getAuthority()
					) ) {
						$formatted = Linker::userLink( (int)$value, $username )
							. Linker::userToolLinks( (int)$value, $username );
					} else {
						$formatted = $this->msg( 'rev-deleted-user' )->escaped();
					}
					if ( LogEventsList::isDeleted( $row, LogPage::DELETED_USER ) ) {
						$formatted = '<span class="history-deleted">' . $formatted . '</span>';
					}
				}
				break;
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
                break;
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
                break;
            case 'log_comment':
				// when timestamp is null, this is an old protection row
				if ( $row->log_timestamp === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-protectedpages-unknown' ],
						$this->msg( 'protectedpages-unknown-reason' )->escaped()
					);
				} else {
					if ( LogEventsList::userCanBitfield(
						$row->log_deleted,
						LogPage::DELETED_COMMENT,
						$this->getAuthority()
					) ) {
						$formatted = $this->formattedComments[$this->getResultOffset()];
					} else {
						$formatted = $this->msg( 'rev-deleted-comment' )->escaped();
					}
					if ( LogEventsList::isDeleted( $row, LogPage::DELETED_COMMENT ) ) {
						$formatted = '<span class="history-deleted">' . $formatted . '</span>';
					}
				}
				break;
            case 'log_timestamp':
				// when timestamp is null, this is a old protection row
				if ( $value === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-lockedpages-unknown' ],
						$this->msg( 'lockedpages-unknown-timestamp' )->escaped()
					);
				} else {
					$formatted = htmlspecialchars( $this->getLanguage()->userTimeAndDate(
						$value,
						$this->getUser()
					) );
				}
				break;
        }
        return $formatted;
    }

    public function getDefaultSort(){
        return 'page_title';
    }

    public function getIndexField() {
        return 'page_title';
    }
}