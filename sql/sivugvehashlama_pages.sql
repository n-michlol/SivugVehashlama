CREATE TABLE IF NOT EXISTS /*_*/sivugvehashlama_pages (
    page_id INT UNSIGNED NOT NULL PRIMARY KEY,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    timestamp BINARY(14) NOT NULL DEFAULT '19700101000000'
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/svh_status ON /*_*/sivugvehashlama_pages (status);
CREATE INDEX /*i*/svh_timestamp ON /*_*/sivugvehashlama_pages (timestamp);