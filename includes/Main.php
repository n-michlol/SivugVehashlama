<?php

namespace Mediawiki\Extension\SivugVehashlama;

use ManualLogEntry;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\ILoadBalancer;

class Main {
    public static const PAGES_TABLE_NAME = 'sivugvehashlama_pages';
    public static const PAGES_PAGE_COLUMN = 'sv_page_id';
    public static const PAGES_COMPLEX_COLUMN = 'sv_complex';
    public static const PAGE_STATUS = [
        0 => 'simple', // complex = false
        1 => 'complex', // complex = true
        2 => 'pending', // complex = null
    ];

    private ILoadBalancer $loadBalancer;

    public function __construct( ILoadBalancer $loadBalancer ) {
        $this->loadBalancer = $loadBalancer;
    }

    /**
     * Fetches a list of pages with their associated complex.
     * @param string|null $complex bool or null, omit for all.
     * @return array<int,string> ( pageid => complexStatus ).
     */
    public function getPagesList( bool|null $complex = '' ): array {
        $db = $this->loadBalancer->getConnection( DB_REPLICA );
        $conds = '';
        if ( $complex !==  '' ) {
            $conds = [ self::PAGES_COMPLEX_COLUMN => $complex ];
        }
        $rows = $db->select(
            self::PAGES_TABLE_NAME,
            [ self::PAGES_PAGE_COLUMN, self::PAGES_COMPLEX_COLUMN ],
            $conds,
            __METHOD__
        );
        $pages = [];
        foreach ( $rows as $row ) {
            $pageId = (int)$row->{self::PAGES_PAGE_COLUMN};
            $complexValue = $row->{self::PAGES_COMPLEX_COLUMN};
            if ( $complexValue === null ) {
                $complexValue = 2; // 2 means 'pending'
            } else {
                $complexValue = (int)$complexValue;
            }
            $pages[$pageId] = self::PAGE_STATUS[$complexValue];
        }
        return $pages;
    }

    /**
     * Fetches the status of a page.
     * @param int $pageId The ID of the page to check.
     * @return string|false 
     */
    public function getPageStatus( int $pageId ): string|false {
        $db = $this->loadBalancer->getConnection( DB_REPLICA );
        $row = $db->selectRow(
            self::PAGES_TABLE_NAME,
            [ self::PAGES_COMPLEX_COLUMN ],
            [ self::PAGES_PAGE_COLUMN => $pageId ],
            __METHOD__
        );
        return $row && $row->{self::PAGES_COMPLEX_COLUMN} !== null
            ? self::PAGE_STATUS[(int)$row->{self::PAGES_COMPLEX_COLUMN}]
            : self::PAGE_STATUS[2]; // 2 means 'pending'
    }

    public function updatePage( User $performer, int $pageId, ?bool $complex, string $comment, ): Status {
        $db = $this->loadBalancer->getConnection( DB_PRIMARY );
        $row = $db->selectRow(
            self::PAGES_TABLE_NAME,
            [ 'sv_id', self::PAGES_COMPLEX_COLUMN ],
            [ self::PAGES_PAGE_COLUMN => $pageId ],
            __METHOD__
        );
        $svId = 0;
        $currentComplex = null;
        $classification = $complex !== null ? ( $complex ? 'complex' : 'simple' ) : 'pending';
        $logEntry = '';
        if ( $row ) {
            $svId = $row->sv_id;
            $currentComplex = $row->{self::PAGES_COMPLEX_COLUMN};
        } 
        if ( $svId > 0 && $currentComplex === $complex ) {
            return Status::newGood();
        }

        if ( $svId === 0 ) {
            // If the page does not exist, insert it
            $insertValues = [
                self::PAGES_PAGE_COLUMN => $pageId,
            ];
            if ( $complex !== null ) {
                $insertValues[] = [ self::PAGES_COMPLEX_COLUMN => $complex ? 1 : 0 ];
            } 
            $db->insert(
                self::PAGES_TABLE_NAME,
                $insertValues,
                __METHOD__
            );
            if ( $db->affectedRows() === 0 ) {
                return Status::newFatal( 'sivugvehashlama-page-insert-failed', [ $pageId ] );
            }
            $logEntry = 'add';
            $svId = $db->insertId();
        } else {
            // If the page exists, update it
            $complexToAdd = null;
            if ( $complex !== null ) {
                $complexToAdd = $complex ? 1 : 0;
            }
            $db->update(
                self::PAGES_TABLE_NAME,
                [ self::PAGES_COMPLEX_COLUMN => $complexToAdd ],
                [ self::PAGES_PAGE_COLUMN => $pageId, 'sv_id' => $svId ],
                __METHOD__
            );
            
            if ( $db->affectedRows() === 0 ) {
                return Status::newFatal( 'sivugvehashlama-page-update-failed', [ $pageId ] );
            }
            $logEntry = 'mark';
        }

        $log = new ManualLogEntry( 'sivugvehashlama', $logEntry );
        $title = Title::newFromID( $pageId );
        $log->setTarget( $title );
        $log->setComment( "$comment" );
        $log->setPerformer( $performer );
        $log->setParameters( [
            '4::complex' => wfMessage( "sivugvehashlama-$logEntry-$classification" ),
        ]);
        $log->setRelations( [ 'sv_page_id' => $pageId, 'sv_id' => $svId ] );
        $logId = $log->insert();

        return Status::newGood( $logId );
        
    }

    public function markPageAsDone ( int $pageId, User $performer, string $comment, ): Status {
        $db = $this->loadBalancer->getConnection( DB_PRIMARY );

        // Check if the page exists
        $svId = $db->selectRow(
            self::PAGES_TABLE_NAME,
            [ 'sv_id' ],
            [ self::PAGES_PAGE_COLUMN => $pageId ],
            __METHOD__
        );

        if ( !$svId ) {
            return Status::newFatal( 'sivugvehashlama-page-not-found', [ $pageId ] );
        }

        $db->delete(
            self::PAGES_TABLE_NAME,
            [ self::PAGES_PAGE_COLUMN => $pageId ],
            __METHOD__
        );

        //  add log entry
        $title = Title::newFromID( $pageId );
        $log = new ManualLogEntry( 'sivugvehashlama', 'done' );
        $log->setTarget( $title );
        $log->setComment( "$comment" );
        $log->setPerformer( $performer );
        $log->setRelations( [ 'sv_page_id' => $pageId, 'sv_id' => $svId ] );
        $logId = $log->insert();

        return Status::newGood( $logId );
    }
}