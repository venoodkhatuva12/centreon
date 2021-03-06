<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 *
 */
class CentreonTimeperiod
{
    /**
     *
     * @var type
     */
    protected $db;

    /**
     *  Constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     *
     * @param type $values
     * @return type
     */
    public function getObjectForSelect2($values = array(), $options = array())
    {
        $items = array();

        $explodedValues = implode(',', $values);
        if (empty($explodedValues)) {
            $explodedValues = "''";
        }

        # get list of selected timeperiods
        $query = "SELECT tp_id, tp_name "
            . "FROM timeperiod "
            . "WHERE tp_id IN (" . $explodedValues . ") "
            . "ORDER BY tp_name ";

        $resRetrieval = $this->db->query($query);
        while ($row = $resRetrieval->fetchRow()) {
            $items[] = array(
                'id' => $row['tp_id'],
                'text' => $row['tp_name']
            );
        }

        return $items;
    }

    /**
     *
     * @param string $name
     * @return string
     */
    public function getTimperiodIdByName($name)
    {
        $query = "SELECT tp_id FROM timeperiod 
                WHERE tp_name = '" . $this->db->escape($name) . "'";

        $res = $this->db->query($query);

        if (!$res->numRows()) {
            return null;
        }
        $row = $res->fetchRow();

        return $row['tp_id'];
    }

    /**
     *
     * @param integer $tpId
     * @return string
     */
    public function getTimperiodException($tpId)
    {
        $query = "SELECT `exception_id` FROM `timeperiod_exceptions`
                WHERE `timeperiod_id` = " . (int)$tpId;
        $res = $this->db->query($query);
        if (!$res->numRows()) {
            return null;
        }

        $row = $res->fetchRow();
        return $row['exception_id'];
    }

    /**
     * Insert in database a command
     *
     * @param array $parameters Values to insert (command_name and command_line is mandatory)
     * @throws Exception
     */
    public function insert($parameters)
    {
        $sQuery = "INSERT INTO `timeperiod` "
            . "(`tp_name`, `tp_alias`, `tp_sunday`, `tp_monday`, `tp_tuesday`, `tp_wednesday`, "
            . "`tp_thursday`, `tp_friday`, `tp_saturday`) "
            . "VALUES ('" . $parameters['name'] . "',"
            . "'" . $parameters['alias'] . "',"
            . "'" . $parameters['sunday'] . "',"
            . "'" . $parameters['monday'] . "',"
            . "'" . $parameters['tuesday'] . "',"
            . "'" . $parameters['wednesday'] . "',"
            . "'" . $parameters['thursday'] . "',"
            . "'" . $parameters['friday'] . "',"
            . "'" . $parameters['saturday'] . "')";

        $res = $this->db->query($sQuery);
        if (\PEAR::isError($res)) {
            throw new \Exception('Error while insert timeperiod ' . $parameters['name']);
        }
    }

    /**
     * Update in database a command
     *
     * @param int $command_id Id of command
     * @param array $command Values to set
     * @throws Exception
     */
    public function update($tp_id, $parameters)
    {

        $sQuery = "UPDATE `timeperiod` SET `tp_alias` = '" . $parameters['alias'] . "', "
            . "`tp_sunday` = '" . $parameters['sunday'] . "',"
            . "`tp_monday` = '" . $parameters['monday'] . "',"
            . "`tp_tuesday` = '" . $parameters['tuesday'] . "',"
            . "`tp_wednesday` = '" . $parameters['wednesday'] . "',"
            . "`tp_thursday` = '" . $parameters['thursday'] . "',"
            . "`tp_friday` = '" . $parameters['friday'] . "',"
            . "`tp_saturday` = '" . $parameters['saturday'] . "'"
            . " WHERE `tp_id` = " . $tp_id;

        $res = $this->db->query($sQuery);

        if (\PEAR::isError($res)) {
            throw new \Exception('Error while update timeperiod ' . $parameters['name']);
        }
    }

    /**
     * Insert in database a timperiod exception
     *
     * @param integer $tpId
     * @param array $parameters Values to insert (days and timerange)
     * @throws Exception
     */
    public function setTimperiodException($tpId, $parameters)
    {
        foreach ($parameters as $exception) {
            $sQuery = "INSERT INTO `timeperiod_exceptions` "
                . "(`timeperiod_id`, `days`, `timerange`) "
                . "VALUES (" . (int)$tpId . ","
                . "'" . $exception['days'] . "',"
                . "'" . $exception['timerange'] . "')";

            $res = $this->db->query($sQuery);

            if (\PEAR::isError($res)) {
                throw new \Exception('Error while insert timeperiod exception' . $tpId);
            }
        }
    }

    /**
     * Insert in database a timperiod dependency
     *
     * @param integer $timeperiodId
     * @param integer $depId
     * @throws Exception
     */
    public function setTimperiodDependency($timeperiodId, $depId)
    {
        $sQuery = "INSERT INTO `timeperiod_include_relations` "
            . "(`timeperiod_id`,`timeperiod_include_id`) "
            . "VALUES (" . (int)$timeperiodId . "," . (int)$depId . ")";

        $res = $this->db->query($sQuery);

        if (\PEAR::isError($res)) {
            throw new \Exception('Error while insert timeperiod dependency' . $timeperiodId);
        }
    }

    /**
     * Delete in database a timperiod exception
     *
     * @param integer $tpId
     * @throws Exception
     */
    public function deleteTimperiodException($tpId)
    {
        $sQuery = "DELETE FROM `timeperiod_exceptions` WHERE `timeperiod_id` = " . (int)$tpId;
        $res = $this->db->query($sQuery);

        if (\PEAR::isError($res)) {
            throw new \Exception('Error while delete timeperiod exception' . $tpId);
        }
    }

    /**
     * Delete in database a timperiod include
     *
     * @param integer $tpId
     * @throws Exception
     */
    public function deleteTimperiodInclude($tpId)
    {
        $sQuery = "DELETE FROM `timeperiod_include_relations` WHERE `timeperiod_id` = " . (int)$tpId;
        $res = $this->db->query($sQuery);

        if (\PEAR::isError($res)) {
            throw new \Exception('Error while delete timeperiod include' . $tpId);
        }
    }

    /**
     * Delete timperiod in database
     *
     * @param string $tp_name timperiod name
     * @throws Exception
     */
    public function deleteTimeperiodByName($tp_name)
    {
        $sQuery = 'DELETE FROM timeperiod '
            . 'WHERE tp_name = "' . $this->db->escape($tp_name) . '"';

        $res = $this->db->query($sQuery);

        if (\PEAR::isError($res)) {
            throw new \Exception('Error while delete timperiod ' . $tp_name);
        }
    }

    /**
     * Returns array of Host linked to the timeperiod
     *
     * @return array
     */
    public function getLinkedHostsByName($timeperiodName, $checkTemplates = true)
    {
        if ($checkTemplates) {
            $register = 0;
        } else {
            $register = 1;
        }

        $linkedCommands = array();
        $query = 'SELECT DISTINCT h.host_name '
            . 'FROM host h, timeperiod t '
            . 'WHERE h.timeperiod_tp_id  = t.tp_id '
            . 'AND h.host_register = "' . $register . '" '
            . 'AND t.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        $result = $this->db->query($query);

        if (PEAR::isError($result)) {
            throw new \Exception('Error while getting linked hosts of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedCommands[] = $row['host_name'];
        }

        return $linkedCommands;
    }

    /**
     * Returns array of Service linked to the timeperiod
     *
     * @return array
     */
    public function getLinkedServicesByName($timeperiodName, $checkTemplates = true)
    {
        if ($checkTemplates) {
            $register = 0;
        } else {
            $register = 1;
        }

        $linkedCommands = array();
        $query = 'SELECT DISTINCT s.service_description '
            . 'FROM service s, timeperiod t '
            . 'WHERE s.timeperiod_tp_id  = t.tp_id '
            . 'AND s.service_register = "' . $register . '" '
            . 'AND t.tp_name = "' . $this->db->escape($timeperiodName) . '" ';

        $result = $this->db->query($query);

        if (PEAR::isError($result)) {
            throw new \Exception('Error while getting linked services of ' . $timeperiodName);
        }

        while ($row = $result->fetchRow()) {
            $linkedCommands[] = $row['service_description'];
        }

        return $linkedCommands;
    }
}
