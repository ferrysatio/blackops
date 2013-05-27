<?php

namespace Blackops\ScriptBundle\Model;

class DbModel extends BaseModel
{
    public function getProductListByWebsite($websiteId)
    {
        $sql = "
            SELECT
            *
            FROM product
            WHERE website_id = :websiteId
        ";

        $statement = $this->conn->executeQuery($sql, array('websiteId' => $websiteId), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function createTemporaryProductTableByDayInterval($temporaryTableName, $priceColumn, $qtyColumn, $dayInterval)
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
            FROM price_qty
            WHERE created = (date(now() - interval $dayInterval day)))
        ";

        $this->conn->executeQuery($sql, array(), array());
        return;
    }

    public function getQtyDifferenceListCurrentWeek($includedWebsiteIds = null, $excludedWebsiteIds = null)
    {
        $sql = "
            SELECT *, p.name as pName, oe.name as eventName, oe.startdate as startDate, oe.enddate as endDate, p.id as pid
            FROM
                product p
                LEFT JOIN website w on (p.website_id = w.id)
                LEFT OUTER JOIN ozsale_product op on (p.id = op.product_id)
                LEFT OUTER JOIN ozsale_event oe on (op.event_id = oe.id)
                LEFT OUTER JOIN p8 as a on (p.id = a.product_id)
                LEFT OUTER JOIN p7 as b on (p.id = b.product_id)
                LEFT OUTER JOIN p6 as c on (p.id = c.product_id)
                LEFT OUTER JOIN p5 as d on (p.id = d.product_id)
                LEFT OUTER JOIN p4 as e on (p.id = e.product_id)
                LEFT OUTER JOIN p3 as f on (p.id = f.product_id)
                LEFT OUTER JOIN p2 as g on (p.id = g.product_id)
                LEFT OUTER JOIN p1 as h on (p.id = h.product_id)
                WHERE
                (
                    a.qty8 <> b.qty7 or
                    b.qty7 <> c.qty6 or
                    c.qty6 <> d.qty5 or
                    d.qty5 <> e.qty4 or
                    e.qty4 <> f.qty3 or
                    f.qty3 <> g.qty2 or
                    g.qty2 <> h.qty1
                )
        ";

        if (!is_null($includedWebsiteIds)) {
            if (is_array($includedWebsiteIds)) {
                $includedIds = implode(',', $includedWebsiteIds);
            } else {
                $includedIds = $includedWebsiteIds;
            }
            $sql .= "
                AND p.website_id in (" . $includedIds . ")
            ";
        }

        if (!is_null($excludedWebsiteIds)) {
            if (is_array($excludedWebsiteIds)) {
                $excludedIds = implode(',', $excludedWebsiteIds);
            } else {
                $excludedIds = $excludedWebsiteIds;
            }
            $sql .= "
                AND p.website_id not in (" . $excludedIds . ")
            ";
        }

        $sql .= "
                GROUP BY p.id
                ORDER BY p.id;
        ";

        $statement = $this->conn->executeQuery($sql, array(), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function getQtyDifferenceListLastWeek($includedWebsiteIds = null, $excludedWebsiteIds = null)
    {
        $sql = "
            SELECT *, p.name as pName, oe.name as eventName, oe.startdate as startDate, oe.enddate as endDate, p.id as pid
            FROM
                product p
                LEFT JOIN website w on (p.website_id = w.id)
                LEFT OUTER JOIN ozsale_product op on (p.id = op.product_id)
                LEFT OUTER JOIN ozsale_event oe on (op.event_id = oe.id)
                LEFT OUTER JOIN p15 as a on (p.id = a.product_id)
                LEFT OUTER JOIN p14 as b on (p.id = b.product_id)
                LEFT OUTER JOIN p13 as c on (p.id = c.product_id)
                LEFT OUTER JOIN p12 as d on (p.id = d.product_id)
                LEFT OUTER JOIN p11 as e on (p.id = e.product_id)
                LEFT OUTER JOIN p10 as f on (p.id = f.product_id)
                LEFT OUTER JOIN p9 as g on (p.id = g.product_id)
                LEFT OUTER JOIN p8 as h on (p.id = h.product_id)
                WHERE
                (
                    a.qty15 <> b.qty14 or
                    b.qty14 <> c.qty13 or
                    c.qty13 <> d.qty12 or
                    d.qty12 <> e.qty11 or
                    e.qty11 <> f.qty10 or
                    f.qty10 <> g.qty9 or
                    g.qty9 <> h.qty8
                )
        ";

        if (!is_null($includedWebsiteIds)) {
            if (is_array($includedWebsiteIds)) {
                $includedIds = implode(',', $includedWebsiteIds);
            } else {
                $includedIds = $includedWebsiteIds;
            }
            $sql .= "
                AND p.website_id in (" . $includedIds . ")
            ";
        }

        if (!is_null($excludedWebsiteIds)) {
            if (is_array($excludedWebsiteIds)) {
                $excludedIds = implode(',', $excludedWebsiteIds);
            } else {
                $excludedIds = $excludedWebsiteIds;
            }
            $sql .= "
                AND p.website_id not in (" . $excludedIds . ")
            ";
        }

        $sql .= "
                GROUP BY p.id
                ORDER BY p.id;
        ";

        $statement = $this->conn->executeQuery($sql, array(), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function getQtyDifferenceList2WeeksAgo($includedWebsiteIds = null, $excludedWebsiteIds = null)
    {
        $sql = "
            SELECT *, p.name as pName, oe.name as eventName, oe.startdate as startDate, oe.enddate as endDate, p.id as pid
            FROM
                product p
                LEFT JOIN website w on (p.website_id = w.id)
                LEFT OUTER JOIN ozsale_product op on (p.id = op.product_id)
                LEFT OUTER JOIN ozsale_event oe on (op.event_id = oe.id)
                LEFT OUTER JOIN p22 as a on (p.id = a.product_id)
                LEFT OUTER JOIN p21 as b on (p.id = b.product_id)
                LEFT OUTER JOIN p20 as c on (p.id = c.product_id)
                LEFT OUTER JOIN p19 as d on (p.id = d.product_id)
                LEFT OUTER JOIN p18 as e on (p.id = e.product_id)
                LEFT OUTER JOIN p17 as f on (p.id = f.product_id)
                LEFT OUTER JOIN p16 as g on (p.id = g.product_id)
                LEFT OUTER JOIN p15 as h on (p.id = h.product_id)
                WHERE
                (
                    a.qty22 <> b.qty21 or
                    b.qty21 <> c.qty20 or
                    c.qty20 <> d.qty19 or
                    d.qty19 <> e.qty18 or
                    e.qty18 <> f.qty17 or
                    f.qty17 <> g.qty16 or
                    g.qty16 <> h.qty15
                )
        ";

        if (!is_null($includedWebsiteIds)) {
            if (is_array($includedWebsiteIds)) {
                $includedIds = implode(',', $includedWebsiteIds);
            } else {
                $includedIds = $includedWebsiteIds;
            }
            $sql .= "
                AND p.website_id in (" . $includedIds . ")
            ";
        }

        if (!is_null($excludedWebsiteIds)) {
            if (is_array($excludedWebsiteIds)) {
                $excludedIds = implode(',', $excludedWebsiteIds);
            } else {
                $excludedIds = $excludedWebsiteIds;
            }
            $sql .= "
                AND p.website_id not in (" . $excludedIds . ")
            ";
        }

        $sql .= "
                GROUP BY p.id
                ORDER BY p.id;
        ";

        $statement = $this->conn->executeQuery($sql, array(), array());
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }
}