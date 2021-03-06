<?php

namespace App\Repository;

use App\Entity;
use Doctrine;
use PDO;

class Entry
{
    /** @var Doctrine\DBAL\Connection */
    private $db;
    private $user;


    public function __construct(Doctrine\DBAL\Connection $db, $session)
    {
        $this->db = $db;
        $this->user = $session->get('user');
    }


    public function getCount($uzatvorene = false): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(z.id) FROM zadania AS z " . "LEFT JOIN predmety AS p ON z.predmet_id = p.id " . "WHERE z.trieda_id = ? AND ( z.stav = ? OR ( z.stav = 1 AND NOW() " . ($uzatvorene ? '>' : '<=') . " z.cas_uzatvorenia ) ) ",
            [
                $this->user['trieda_id'],
                $uzatvorene ? 0 : 2
            ], 0, [PDO::PARAM_INT, PDO::PARAM_INT]);
    }


    public function getFromOffset($offset = 0, $count = 50, $uzatvorene = false)
    {
        $user = $this->user;

        $zadania = $this->db->fetchAll("SELECT z.id, z.nazov, z.cas_uzatvorenia, p.skratka AS predmet FROM zadania AS z " . "LEFT JOIN predmety AS p ON z.predmet_id = p.id " . "WHERE z.trieda_id = ? AND ( z.stav = ? OR ( z.stav = 1 AND NOW() " . ($uzatvorene ? '>' : '<=') . " z.cas_uzatvorenia ) ) " . "ORDER BY z.cas_uzatvorenia DESC LIMIT ?, ?",
            [$user['trieda_id'], $uzatvorene ? 0 : 2, $offset, $count],
            [PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT]);

        if (empty($zadania)) {
            return array();
        }

        $zadaniaIds = array_map(function ($v) { return (int)$v['id']; }, $zadania);

        $stmt = $this->db->executeQuery("SELECT id, zadanie_id, poznamka, cas_odovzdania, cas_upravenia FROM odovzdania WHERE zadanie_id IN (?)",
            [$zadaniaIds], [Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

        $odovzdania    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $odovzdaniaIds = array_map(function ($v) { return (int)$v['id']; }, $odovzdania);

        if (empty($odovzdania)) {
            $subory = array();
        } else {
            $stmt = $this->db->executeQuery("SELECT id, odovzdanie_id, nazov, velkost, cesta, cas_odovzdania, cas_upravenia FROM subory WHERE odovzdanie_id IN (?)",
                [$odovzdaniaIds], [Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            $subory = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                }
            }
        }
        unset($zadanie);

        return $zadania;
    }


    public function getAll($uzatvorene = false)
    {
        return $this->getFromOffset(0, 50, $uzatvorene);
    }

    public function getAllForTeacher($uzatvorene = false)
    {
        $user = $this->user;

        $zadania = $this->db->fetchAll("SELECT z.id, z.nazov, z.cas_uzatvorenia, t.rocnik, t.kod, z.trieda_id, p.skratka AS predmet FROM zadania AS z "
                . "LEFT JOIN predmety AS p ON z.predmet_id = p.id "
                . "LEFT JOIN triedy AS t ON z.trieda_id = t.id "
                . "WHERE z.pouzivatel_id = ? AND ( z.stav = ? OR ( z.stav = 1 AND NOW() ".($uzatvorene ? '>' : '<=')." z.cas_uzatvorenia ) ) "
                . "ORDER BY z.cas_uzatvorenia DESC", array($user['id'], $uzatvorene ? 0 : 2));

        if (empty($zadania)) {
            return array();
        }

        $triedyIds = array_map(function ($v) { return (int)$v['trieda_id']; }, $zadania);
        $zadaniaIds = array_map(function ($v) { return (int)$v['id']; }, $zadania);

        // pocet ludi v triede
        $stmt = $this->db->executeQuery("SELECT trieda_id, COUNT(*) AS pocet_ziakov FROM pouzivatelia WHERE trieda_id IN(?) GROUP BY trieda_id",
            array($triedyIds), [Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
        $triedy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $triedy = array_combine(array_map(function ($v) { return (int)$v['trieda_id']; }, $triedy), $triedy);

        // pocet odovzdanych k zadaniu
        $stmt = $this->db->executeQuery("SELECT zadanie_id, trieda_id, COUNT(*) AS pocet_ziakov FROM odovzdania AS o
            LEFT JOIN zadania AS z ON o.zadanie_id = z.id
            WHERE z.trieda_id IN(?) AND o.zadanie_id IN(?) GROUP BY o.zadanie_id",
            array($triedyIds, $zadaniaIds),
            [Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
        $odovzdania = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $odovzdania = array_combine(array_map(function ($v) { return (int)$v['zadanie_id']; }, $odovzdania), $odovzdania);

        foreach ($zadania as &$zadanie) {
            $zadanie['odovzdanych'] = (isset($odovzdania[$zadanie['id']]['pocet_ziakov']) ? $odovzdania[$zadanie['id']]['pocet_ziakov']:0).'/'.$triedy[$zadanie['trieda_id']]['pocet_ziakov'];
        }

        return $zadania;
    }
    
    public function getFileList($zadanieId)
    {
        return $this->db->fetchAll("SELECT s.nazov, s.cesta, p.login FROM subory AS s"
                . " LEFT JOIN odovzdania AS o ON s.odovzdanie_id = o.id"
                . " LEFT JOIN zadania AS z ON o.zadanie_id = z.id"
                . " LEFT JOIN pouzivatelia AS p ON o.pouzivatel_id = p.id"
                . " WHERE o.zadanie_id = ?", array($zadanieId));
    }

    public function getNotes($zadanieId)
    {
        return $this->db->fetchAll("SELECT o.poznamka, p.login FROM odovzdania AS o"
            . " LEFT JOIN pouzivatelia AS p ON o.pouzivatel_id = p.id"
            . " WHERE o.zadanie_id = ? AND o.poznamka IS NOT NULL AND p.login IS NOT NULL", array($zadanieId));
    }
    
    public function save(Entity\Entry $zadanie)
    {
        $zadanieData = array(
            'nazov' => $zadanie->getNazov(),
            'trieda_id' => $zadanie->getTriedaId(),
            'pouzivatel_id' => $zadanie->getPouzivatelId(),
            'predmet_id' => $zadanie->getPredmetId(),
            'stav' => $zadanie->getStav(),
            'cas_uzatvorenia' => $zadanie->getCasUzatvorenia()
        );
        
        if ($zadanie->getId()) {
            $this->db->update('zadania', $zadanieData, array('zadanie_id' => $zadanie->getId()));
        } else {
            $this->db->insert('zadania', $zadanieData, array(PDO::PARAM_STR, PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT, 'datetime'));
            $id = $this->db->lastInsertId();
            $zadanie->setId($id);
        }
    }
    
    public function delete($id)
    {
        return $this->db->delete('zadania', array('id' => $id));
    }
}
