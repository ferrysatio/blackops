<?php

namespace Blackops\ScriptBundle\Model;

use Doctrine\DBAL\Connection;

class BaseModel
{
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
}