<?php

class ZadaniaService
{

    /** @var Doctrine\DBAL\Connection */
    private $db;
    private $user;

    public function __construct(\Doctrine\DBAL\Connection $db, $session)
    {
        $this->db = $db;
        $this->user = $session->get('user');
    }

    public function getAll($uzatvorene = FALSE)
    {
        $user = $this->user;

        $zadania = $this->db->fetchAll("SELECT z.id, z.nazov, z.cas_uzatvorenia, p.skratka AS predmet FROM zadania AS z "
                . "LEFT JOIN predmety AS p ON z.predmet_id = p.id "
                . "WHERE z.trieda_id = ? AND ( z.stav = ? OR ( z.stav = 1 AND NOW() ".($uzatvorene ? '>' : '<=')." z.cas_uzatvorenia ) ) "
                . "ORDER BY z.cas_uzatvorenia DESC", array($user['trieda_id'], $uzatvorene ? 0 : 2));

        if ( empty($zadania) ) {
            return array();
        }

        $zadaniaIds = array_map(function ($v){ return (int)$v['id']; }, $zadania);
    
        // TODO: Mozeme dat k predchodziemu ako JOIN
        $stmt = $this->db->executeQuery("SELECT id, zadanie_id, poznamka, cas_odovzdania, cas_upravenia FROM odovzdania WHERE zadanie_id IN (?)", 
            array($zadaniaIds), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
        $odovzdania = $stmt->fetchAll();
        $odovzdaniaIds = array_map(function ($v){ return (int)$v['id']; }, $odovzdania);
    
        if ( empty($odovzdania) ) {
            $subory = array();
        } else {
            $stmt = $this->db->executeQuery("SELECT id, odovzdanie_id, nazov, velkost, cesta, cas_odovzdania, cas_upravenia FROM subory WHERE odovzdanie_id IN (?)", 
                array($odovzdaniaIds), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
            $subory = $stmt->fetchAll();
        }
    
        foreach ($zadania as &$zadanie) {
            $zadanie['subory'] = array();
            foreach ($odovzdania as $o) {
                if ($o['zadanie_id'] == $zadanie['id']) {
                    $zadanie['odovzdanie'] = $o;
                    foreach ($subory as $s) {
                        if ($o['id'] == $s['odovzdanie_id']) {
                            $zadanie['subory'][] = $s;
                        }
                    }
                } // xDDD
            }
        }
        unset($zadanie); // !!!!

        return $zadania;
    }

}