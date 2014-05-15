ALTER TABLE `pouzivatelia`
ADD `heslo` varchar(64) COLLATE 'utf8_general_ci' NOT NULL AFTER `login`,
COMMENT=''; -- 0.012 s

UPDATE `pouzivatelia` SET `heslo` = '098f6bcd4621d373cade4e832627b4f6'; -- 0.001 s