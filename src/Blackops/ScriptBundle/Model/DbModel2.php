<?php

namespace Blackops\ScriptBundle\Model;


class DbModel2 extends BaseModel
{
    public function getActiveWebsites($ids = null)
    {
        $sql = "
            SELECT
            id, url, have_event, report_weeks, daily_scraping
            FROM website
            WHERE active = 1
        ";

        if (!is_null($ids)) {
            $sql .= "
                and id in ($ids)
            ";
        }

        $statement = $this->conn->executeQuery($sql, array(), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function createTemporaryProductTableByDayInterval($temporaryTableName, $fromTable, $priceColumn, $qtyColumn, $dayInterval)
    {
        $sql = "
            CREATE TEMPORARY TABLE $temporaryTableName (
                product_id   int(11) not null,
                $priceColumn decimal(15,2) not null,
                $qtyColumn   int(11) not null,
                key product_id_idx (product_id)) (
            SELECT
                product_id,
                price as $priceColumn,
                qty as $qtyColumn
            FROM $fromTable
            WHERE created = (date(now() - interval $dayInterval day)));
        ";

        echo $sql;

        $this->conn->executeQuery($sql, array(), array());
        return;
    }

    public function getWeeklyData($table, $startDay = 1, $endDay = 8, $getQtyDiff = true)
    {
        $productTable          = $table['product'];
        $withEventSelectQuery  = '';
        $withEventFromQuery    = '';
        $productJoinTableQuery = '';
        $productWhereQuery     = '';

        if (isset($table['composite']) && isset($table['event'])) {
            $withEventSelectQuery = ', oe.name as eventName, oe.startdate as startDate, oe.enddate as endDate';
            $withEventFromQuery   = "
                LEFT OUTER JOIN {$table['composite']} op on (p.id = op.product_id)
                LEFT OUTER JOIN {$table['event']} oe on (op.event_id = oe.id)
            ";
        }

        for ($i = $endDay; $i >= $startDay; $i--) {
            $tableName = $table['name'] . '_p' . $i;
            $productJoinTableQuery .= "
                LEFT OUTER JOIN {$tableName} on (p.id = {$tableName}.product_id)
            ";
        }

        if ($getQtyDiff) {
            $productWhereQuery .= "
                WHERE
                    (
            ";
            for ($j = $endDay; $j > $startDay; $j--) {
                $tableNameCurrent = $table['name'] . '_p' . $j;
                $tableNameNext    = $table['name'] . '_p' . ($j - 1);
                $idCurrent = $j;
                $idNext    = $j - 1;
                if ($j < $endDay) {
                    $productWhereQuery .= "
                        or
                    ";
                }
                $productWhereQuery .= "
                    {$tableNameCurrent}.qty{$idCurrent} <> {$tableNameNext}.qty{$idNext}
                ";
            }
            $productWhereQuery .= "
                    )
            ";
        }

        $sql = "
            SELECT *, p.name as pName, p.id as pid $withEventSelectQuery
            FROM
                $productTable p
                $withEventFromQuery
                $productJoinTableQuery
            $productWhereQuery
            GROUP BY p.id
            ORDER BY p.id;
        ";

        echo $sql;

        $statement = $this->conn->executeQuery($sql, array(), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }
}