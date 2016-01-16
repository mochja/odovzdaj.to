<?php

namespace App\Services;

use Doctrine;
use App\Entity;

class LoginService
{

    /**
     * @var Doctrine\DBAL\Connection
     */
    private $db;


    public function __construct(Doctrine\DBAL\Connection $db)
    {
        $this->db = $db;
    }


    public function auth(Entity\User $loginUser)
    {
        $imap = true;

        if ( ! $imap) {
            return false;
        } elseif ($user = $this->db->fetchAssoc("SELECT p.id, p.heslo, p.meno, p.role, t.rocnik, t.kod, p.trieda_id FROM pouzivatelia AS p LEFT JOIN triedy AS t ON p.trieda_id = t.id WHERE p.login = ?",
            [$loginUser->getLogin()])
        ) {
            return $user['heslo'] === md5($loginUser->getPassword()) ? $user : false;
        } else {
            return false;
        }
    }
}
