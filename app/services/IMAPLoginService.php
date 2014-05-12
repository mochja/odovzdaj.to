<?php

class IMAPLoginService
{
    private $state;
    /**
     *
     * @var Doctrine\DBAL\Connection
     */
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function auth($username, $password)
    {
//        $imap = imap_open("{localhost:143/imap/novalidate-cert}", $username, $password);
        $imap = true;
        
        if (!$imap) {
            return FALSE;
        } else if ($user = $this->db->fetchAssoc("SELECT p.id, p.meno, p.role, t.rocnik, t.kod, p.trieda_id FROM pouzivatelia AS p LEFT JOIN triedy AS t ON p.trieda_id = t.id WHERE p.login = ?", array($username))) {
            return $user;
        } else {
            return FALSE;
        }
    }
}