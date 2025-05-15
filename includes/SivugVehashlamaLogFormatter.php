<?php

class SivugVehashlamaLogFormatter extends LogFormatter {
    protected function getMessageParameters() {
        $params = parent::getMessageParameters();
        
        if ( isset( $params[2] ) && $params[2] instanceof Title ) {
            $params[2] = $params[2]->getPrefixedText();
        }
        
        return $params;
    }
    
    protected function getMessageKey() {
        $entry = $this->entry;
        $type = $entry->getType();
        $action = $entry->getSubtype();
        $key = 'logentry-' . $type . '-' . $action;
        
        return $key;
    }
}