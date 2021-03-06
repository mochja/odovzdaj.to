<?php

namespace App\Repository;

use Doctrine;

class Subject
{
    /** @var Doctrine\DBAL\Connection */
    private $db;
    private $user;


    public function __construct(Doctrine\DBAL\Connection $db, $session)
    {
        $this->db = $db;
        $this->user = $session->get('user');
    }
    
    public function getList()
    {
        $list = $this->db->fetchAll('SELECT id, nazov FROM predmety');
        
        $pair = array();
        foreach ($list as $predmet) {
            $pair[$predmet['id']] = $predmet['nazov'];
        }
        unset($list);
        
        return $pair;
    }
}
