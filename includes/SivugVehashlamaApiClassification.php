<?php

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiWatchlistTrait;
use Wikimedia\ParamValidator\ParamValidator;

class SivugVehashlamaApiClassification extends ApiBase {

    use ApiWatchlistTrait;

    public function execute () {}
    public function getAllowedParams () {
        return [
            'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ApiBase::PARAM_HELP_MSG => 'apihelp-sivugvehashlama-param-title',
			],
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-sivugvehashlama-param-pageid',
			],
            'complex' => [
                ParamValidator::PARAM_DEFAULT => null,
                ParamValidator::PARAM_TYPE => 'boolean',
                ApiBase::PARAM_HELP_MSG => 'apihelp-sivugvehashlama-param-complex',
            ],
            'done' => [
                ParamValidator::PARAM_DEFAULT => null,
                ParamValidator::PARAM_TYPE => 'boolean',
                ApiBase::PARAM_HELP_MSG => 'apihelp-sivugvehashlama-param-done',
            ],
        ] + $this->getWatchlistParams();
    }
    public function mustBePosted() {
        return true;
    }
    public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}
    /**
	 * @inheritDoc
	 */
	public function getExamples() {
		return [
			'api.php?title=Main_Page&action=sivugvehashlama&token=TOKEN' => 'apihelp-sivugvehashlama-example-1'
		];
	}
}
        