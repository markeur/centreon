<?php
/*
 * Copyright 2018 Centreon
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
 * Define the period between to update in second for ldap user/group
 */
define('LDAP_UPDATE_PERIOD', 3600);

include_once 'DB.php';

require_once realpath(dirname(__FILE__) . '/../config/centreon.config.php');
include_once _CENTREON_PATH_ . '/cron/centAcl-Func-redis.php';
include_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
include_once _CENTREON_PATH_ . '/www/class/centreonLDAP.class.php';
include_once _CENTREON_PATH_ . '/www/class/centreonMeta.class.php';
include_once _CENTREON_PATH_ . '/www/class/centreonContactgroup.class.php';

$centreonDbName = $conf_centreon['db'];

function programExit($msg, $code = 0)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    exit($code);
}

function info($level, $msg)
{
    global $verbose;
    if ($level <= $verbose) {
        print('INFO: ' . $msg . "\n");
    }
}

if (!$redis) {
  programExit('Redis not configured on this platform', 1);
}

$nbProc = exec('ps -o args -p $(pidof -o $$ -o $PPID -o %PPID -x php || echo 1000000) | grep -c ' . __FILE__);
if ((int) $nbProc > 0) {
    programExit('More than one centAcl-redis.php process currently running. Going to exit...', 2);
}

ini_set('max_execution_time', 0);

/*
 * Init values
 */
$debug = 0;
$verbose = 0;

for ($i = 1; $i < count($argv); ++$i) {
    if ($argv[$i] == '-v') {
        $verbose = 1;
    }
    elseif ($argv[$i] == '-vv') {
        $verbose = 2;
    }
    elseif ($argv[$i] == '-vvv') {
        $verbose = 3;
    }
    elseif ($argv[$i] == '-d') {
        $debug = 1;
    }
}

try {

    /*
     * Init Redis connection
     */

    info(2, "Redis connection...");
    $redis = new Redis();
    $redis->connect($conf_centreon['redisServer'], $conf_centreon['redisPort']);
    $redis->auth($conf_centreon['redisPassword']);

    /*
     * Init DB connections
     */
    $pearDB = new CentreonDB();
    $pearDBO = new CentreonDB('centstorage');

    $metaObj = new CentreonMeta($pearDB);
    $cgObj = new CentreonContactgroup($pearDB);

    /*
     * Detect Which DB layer is used
     */
    $DBRESULT = $pearDB->query("SELECT * FROM options WHERE `key` LIKE 'broker'");
    if (PEAR::isError($DBRESULT)) {
        programExit('Cannot Get Monitoring Engine', 1);
    }
    $row = $DBRESULT->fetchRow();
    $dbLayer = $row['value'];

    /*
     * Lock in MySQL
     */
    $is_running = $redis->hGet('cron_operation:centAcl-redis.php', 'running');

    $beginTime = time();

    if (!$is_running) {
        $redis->hMSet('cron_operation:centAcl-redis.php', array(
            'system' => 1,
            'activate' => 1
        ));
        $is_running = 0;
    }

    if (!$is_running) {
      $is_running = $redis->hIncrBy('cron_operation:centAcl-redis.php', 'running', 1);
      if ($is_running > 1) {
        $redis->hIncrBy('cron_operation:centAcl-redis.php', 'running', -1);
        programExit('According to Redis information, another instance of centAcl-redis.php is already running.', 2);
      }
      $redis->hSet('cron_operation:centAcl-redis.php', 'time_launch', time());
    } else {
        if ($nbProc <= 1) {
            $errorMessage = "According to DB another instance of centAcl-redis.php is already running.\n";
        } else {
            $errorMessage = 'centAcl marked as running. Exiting...';
        }
        programExit($errorMessage, 2);
    }

    /** **********************************************
     * Sync ACL with ldap
     */
    $queryOptions = "SELECT `key`, `value` FROM `options` WHERE `key` IN ('ldap_auth_enable', 'ldap_last_acl_update')";
    $res = $pearDB->query($queryOptions);
    while ($row = $res->fetchRow()) {
        switch ($row['key']) {
            case 'ldap_auth_enable':
                $ldap_enable = $row['value'];
                break;
            case 'ldap_last_acl_update':
                $ldap_last_update = $row['value'];
                break;
        }
    }

    /** ********************************************
     * If the ldap is enable and the last check
     * is more than update period
     */
    if ($ldap_enable == 1 && $ldap_last_update < (time() - LDAP_UPDATE_PERIOD)) {
        $cgObj->syncWithLdap();
    }

    // FIXME DBR: centreon_acl's cleanup should be rewritten...
    /** **********************************************
     * Remove data from old groups (deleted groups)
     */
    //$aclGroupToDelete = "SELECT DISTINCT acl_group_id FROM $centreonDbName.acl_groups WHERE acl_group_activate = '1'";
    //$aclGroupToDelete2 = "SELECT DISTINCT acl_group_id FROM $centreonDbName.acl_res_group_relations";
    //$pearDBO->query("DELETE FROM centreon_acl WHERE group_id NOT IN ($aclGroupToDelete)");
    //$pearDBO->query("DELETE FROM centreon_acl WHERE group_id NOT IN ($aclGroupToDelete2)");

    /** ***********************************************
     * Check if some ACL have global options for
     * all resources are selected
     */
    $query = 'SELECT acl_res_id, all_hosts, all_hostgroups, all_servicegroups ' .
            "FROM acl_resources WHERE acl_res_activate = '1' " .
            'AND (all_hosts IS NOT NULL OR all_hostgroups IS NOT NULL OR all_servicegroups IS NOT NULL)';
    $res = $pearDB->query($query);
    while ($row = $res->fetchRow()) {
        /**
         * Specific counter
         */
        $i = 0;

        /**
         * Add Hosts
         */
        if ($row['all_hosts']) {
            $query = "SELECT host_id FROM host WHERE host_id NOT IN (SELECT DISTINCT host_host_id FROM acl_resources_host_relations WHERE acl_res_id = '" . $row['acl_res_id'] . "') AND host_register = '1'";
            $res1 = $pearDB->query($query);
            for (; $rowData = $res1->fetchRow(); $i++) {
                $insert_query = "INSERT INTO acl_resources_host_relations (host_host_id, acl_res_id) VALUES ('" . $rowData['host_id'] . "', '" . $row['acl_res_id'] . "')";
                $pearDB->query($insert_query);
            }
            $res1->free();
        }

        /**
         * Add Hostgroups
         */
        if ($row['all_hostgroups']) {
            $query = "SELECT hg_id FROM hostgroup WHERE hg_id NOT IN (SELECT DISTINCT hg_hg_id FROM acl_resources_hg_relations WHERE acl_res_id = '" . $row['acl_res_id'] . "')";
            $res1 = $pearDB->query($query);
            for (; $rowData = $res1->fetchRow(); $i++) {
                $insert_query = "INSERT INTO acl_resources_hg_relations (hg_hg_id, acl_res_id) VALUES ('" . $rowData['hg_id'] . "', '" . $row['acl_res_id'] . "')";
                $pearDB->query($insert_query);
            }
            $res1->free();
        }

        /**
         * Add Servicesgroups
         */
        if ($row['all_servicegroups']) {
            $query = "SELECT sg_id FROM servicegroup WHERE sg_id NOT IN (SELECT DISTINCT sg_id FROM acl_resources_sg_relations WHERE acl_res_id = '" . $row['acl_res_id'] . "')";
            $res1 = $pearDB->query($query);
            for (; $rowData = $res1->fetchRow(); $i++) {
                $insert_query = "INSERT INTO acl_resources_sg_relations (sg_id, acl_res_id)"
                                . " VALUES ('" . $rowData['sg_id'] . "', '" . $row['acl_res_id'] . "')";
                $pearDB->query($insert_query);
            }
            $res1->free();
        }

        if ($i != 0) {
            $pearDB->query("UPDATE acl_resources SET changed = '1' WHERE acl_res_id = '" . $row['acl_res_id'] . "'");
        }
    }
    $res->free();

    /*
     * Check that resources ACL have been changed
     *  if no : go away.
     *  if yes : let's go to build cache and update database
     */

    $tabGroups = array();
    $aclKeys = $redis->keys('aclh:*');
        info(1, count($aclKeys) . ' key(s) of the form aclh:* found');
    if (empty($aclKeys)) {
        info(1, 'Attempt to find all resources attached to Acl');
        /* Redis server has surely been restarted and it lost acl */
        $query = 'SELECT DISTINCT acl_groups.acl_group_id ' .
            'FROM acl_res_group_relations, `acl_groups`, `acl_resources` ' .
            'WHERE acl_groups.acl_group_id = acl_res_group_relations.acl_group_id ' .
            'AND acl_res_group_relations.acl_res_id = acl_resources.acl_res_id ' .
            "AND acl_groups.acl_group_activate = '1'";
    }
    else {
        info(1, 'Attempt to find resources attached to Acl that changed');
        $query = 'SELECT DISTINCT acl_groups.acl_group_id ' .
            'FROM acl_res_group_relations, `acl_groups`, `acl_resources` ' .
            'WHERE acl_groups.acl_group_id = acl_res_group_relations.acl_group_id ' .
            'AND acl_res_group_relations.acl_res_id = acl_resources.acl_res_id ' .
            "AND acl_groups.acl_group_activate = '1' " .
            "AND (acl_groups.acl_group_changed = '1' " .
            "OR acl_resources.changed = '1')";
    }

    $DBRESULT1 = $pearDB->query($query);
    while ($result = $DBRESULT1->fetchRow()) {
        $tabGroups[$result['acl_group_id']] = 1;
    }
    $DBRESULT1->free();
    unset($result);

    if (count($tabGroups)) {

        /** ***********************************************
         *  Caching of all Data
         *
         */
        $hostTemplateCache = array();
        $query = 'SELECT host_host_id, host_tpl_id FROM host_template_relation';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            if (!isset($hostTemplateCache[$row['host_tpl_id']])) {
                $hostTemplateCache[$row['host_tpl_id']] = array();
            }
            $hostTemplateCache[$row['host_tpl_id']][$row['host_host_id']] = $row['host_host_id'];
        }
        $res->free();

        $hostCache = array();
        $DBRESULT = $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1'");
        while ($h = $DBRESULT->fetchRow()) {
            $hostCache[$h['host_id']] = $h['host_name'];
        }
        $DBRESULT->free();
        unset($h);

        /** ***********************************************
         * Cache for host poller relation
         */
        $hostPollerCache = array();
        $query = 'SELECT nagios_server_id, host_host_id FROM ns_host_relation';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            if (!isset($hostPollerCache[$row['nagios_server_id']])) {
                $hostPollerCache[$row['nagios_server_id']] = array();
            }
            $hostPollerCache[$row['nagios_server_id']][$row['host_host_id']] = $row['host_host_id'];
        }

        /** ***********************************************
         * Get all included Hosts
         */
        $hostIncCache = array();
        $DBRESULT = $pearDB->query("SELECT host_id, host_name, acl_res_id FROM `host`, `acl_resources_host_relations` WHERE acl_resources_host_relations.host_host_id = host.host_id AND host.host_register = '1'");
        while ($h = $DBRESULT->fetchRow()) {
            if (!isset($hostIncCache[$h['acl_res_id']])) {
                $hostIncCache[$h['acl_res_id']] = array();
            }
            $hostIncCache[$h['acl_res_id']][$h['host_id']] = $h['host_name'];
        }
        $DBRESULT->free();
        info(1, 'Included hosts: ' . count($hostIncCache));

        /** ***********************************************
         * Get all excluded Hosts
         */
        $hostExclCache = array();
        $DBRESULT = $pearDB->query("SELECT host_id, host_name, acl_res_id FROM `host`, `acl_resources_hostex_relations` WHERE acl_resources_hostex_relations.host_host_id = host.host_id AND host.host_register = '1'");
        while ($h = $DBRESULT->fetchRow()) {
            if (!isset($hostExclCache[$h['acl_res_id']])) {
                $hostExclCache[$h['acl_res_id']] = array();
            }
            $hostExclCache[$h['acl_res_id']][$h['host_id']] = $h['host_name'];
        }
        $DBRESULT->free();
        info(1, 'Excluded hosts: ' . count($hostExclCache));

        /** ***********************************************
         * Service Cache
         */
        $svcCache = array();
        $DBRESULT = $pearDB->query("SELECT service_id, service_description FROM `service` WHERE service_register = '1'");
        while ($s = $DBRESULT->fetchRow()) {
            $svcCache[$s['service_id']] = $s['service_description'];
        }
        $DBRESULT->free();

        /** ***********************************************
         * Host Group relation
         */
        $hostHGRelation = array();
        $DBRESULT = $pearDB->query('SELECT * FROM hostgroup_relation');
        while ($hg = $DBRESULT->fetchRow()) {
            if (!isset($hostHGRelation[$hg['hostgroup_hg_id']])) {
                $hostHGRelation[$hg['hostgroup_hg_id']] = array();
            }
            $hostHGRelation[$hg['hostgroup_hg_id']][$hg['host_host_id']] = $hg['host_host_id'];
        }
        $DBRESULT->free();
        unset($hg);

        /** ***********************************************
         * Host Service relation
         */
        $hsRelation = array();
        $hgsRelation = array();
        $DBRESULT = $pearDB->query("SELECT hostgroup_hg_id, host_host_id, service_service_id FROM host_service_relation");
        while ($sr = $DBRESULT->fetchRow()) {
            if (isset($sr['host_host_id']) && $sr['host_host_id']) {
                if (!isset($hsRelation[$sr['host_host_id']])) {
                    $hsRelation[$sr['host_host_id']] = array();
                }
                $hsRelation[$sr['host_host_id']][$sr['service_service_id']] = 1;
            } else {
                if (isset($hostHGRelation[$sr['hostgroup_hg_id']])) {
                    foreach ($hostHGRelation[$sr['hostgroup_hg_id']] as $host_id) {
                        if (!isset($hsRelation[$host_id])) {
                            $hsRelation[$host_id] = array();
                        }
                        $hsRelation[$host_id][$sr['service_service_id']] = 1;
                    }
                }
            }
        }
        $DBRESULT->free();

        /** ***********************************************
         * Create Servive template modele Cache
         */
        $svcTplCache = array();
        $DBRESULT = $pearDB->query('SELECT service_template_model_stm_id, service_id FROM service');
        while ($tpl = $DBRESULT->fetchRow()) {
            $svcTplCache[$tpl['service_id']] = $tpl['service_template_model_stm_id'];
        }
        $DBRESULT->free();
        unset($tpl);

        $svcCatCache = array();
        $DBRESULT = $pearDB->query('SELECT sc_id, service_service_id FROM `service_categories_relation`');
        while ($res = $DBRESULT->fetchRow()) {
            if (!isset($svcCatCache[$res['service_service_id']])) {
                $svcCatCache[$res['service_service_id']] = array();
            }
            $svcCatCache[$res['service_service_id']][$res['sc_id']] = 1;
        }
        $DBRESULT->free();
        unset($res);

        $sgCache = array();
        $query = 'SELECT argr.`acl_res_id`, acl_group_id ' .
            'FROM `acl_res_group_relations` argr, `acl_resources` ar  ' .
            'WHERE argr.acl_res_id = ar.acl_res_id ' .
            "AND ar.acl_res_activate = '1'";
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            $sgCache[$row['acl_group_id']][$row['acl_res_id']] = array();
        }
        $res->free();
        unset($row);

        $query = 'SELECT service_service_id, sgr.host_host_id, acl_res_id ' .
            'FROM servicegroup sg, acl_resources_sg_relations acl, servicegroup_relation sgr ' .
            'WHERE acl.sg_id = sg.sg_id ' .
            'AND sgr.servicegroup_sg_id = sg.sg_id ';
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            foreach ($sgCache as $acl_g_id => $acl_g) {
                if (isset($tabGroups[$acl_g_id])) {
                    foreach ($acl_g as $rId => $value) {
                        if ($rId == $row['acl_res_id']) {
                            if (!isset($sgCache[$acl_g_id][$rId][$row['host_host_id']])) {
                                $sgCache[$acl_g_id][$rId][$row['host_host_id']] = array();
                            }
                            $sgCache[$acl_g_id][$rId][$row['host_host_id']][$svcCache[$row['service_service_id']]] = $row['service_service_id'];
                        }
                    }
                }
            }
        }
        $res->free();
        unset($row);

        $query = "SELECT acl_res_id, hg_id FROM hostgroup, acl_resources_hg_relations
    			  WHERE acl_resources_hg_relations.hg_hg_id = hostgroup.hg_id";
        $res = $pearDB->query($query);
        $hgResCache = array();
        while ($row = $res->fetchRow()) {
            if (!isset($hgResCache[$row['acl_res_id']])) {
                $hgResCache[$row['acl_res_id']] = array();
            }
            $hgResCache[$row['acl_res_id']][] = $row['hg_id'];
        }

        /** ***********************************************
         * Begin to build ACL
         */
        $strBegin = 'INSERT INTO `centreon_acl` (`host_id` , `service_id`,`group_id` ) VALUES ';
        $cpt = 0;
        foreach ($tabGroups as $acl_group_id => $acl_res_id) {
            $tabElem = array();

            /*
             * Delete old data for this group
             */
            info(1, "Delete aclh:$acl_group_id");
            $redis->del('aclh:' . $acl_group_id);
            //$DBRESULT = $pearDBO->query("DELETE FROM `centreon_acl` WHERE `group_id` = '" . $acl_group_id . "'");

            /** ***********************************************
             * Select
             */
            $DBRESULT2 = $pearDB->query("SELECT `acl_resources`.`acl_res_id` FROM `acl_res_group_relations`, `acl_resources` " .
                                        "WHERE `acl_res_group_relations`.`acl_group_id` = '" . $acl_group_id . "' " .
                                        "AND `acl_res_group_relations`.acl_res_id = `acl_resources`.acl_res_id " .
                                        "AND `acl_resources`.acl_res_activate = '1'");
            if ($debug) {
                $time_start = microtime_float2();
            }
            while ($res2 = $DBRESULT2->fetchRow()) {
                $Host = array();
                /* ------------------------------------------------------------------ */

                /*
                 * Get all Hosts
                 */
                if (isset($hostIncCache[$res2['acl_res_id']])) {
                    foreach ($hostIncCache[$res2['acl_res_id']] as $host_id => $host_name) {
                        $Host[$host_id] = $host_name;
                    }
                }

                if (isset($hgResCache[$res2['acl_res_id']])) {
                    foreach ($hgResCache[$res2['acl_res_id']] as $hgId) {
                        if (isset($hostHGRelation[$hgId])) {
                            foreach ($hostHGRelation[$hgId] as $host_id) {
                                if ($hostCache[$host_id]) {
                                    $Host[$host_id] = $hostCache[$host_id];
                                } else {
                                    print "Host $host_id unknown !\n";
                                }
                            }
                        }
                    }
                }

                if (isset($hostExclCache[$res2['acl_res_id']])) {
                    foreach ($hostExclCache[$res2['acl_res_id']] as $host_id => $host_name) {
                        unset($Host[$host_id]);
                    }
                }

                /*
                 * Give Authorized Categories
                 */
                $authorizedCategories = getAuthorizedCategories($acl_group_id, $res2["acl_res_id"]);

                /*
                 * get all Service groups
                 */
                $sgReq = 'SELECT r.acl_res_id, sg.sg_id
                            FROM servicegroup sg LEFT JOIN acl_resources_sg_relations r
                            ON r.sg_id = sg.sg_id';
                $DBRESULT3 = $pearDB->query($sgReq);
                $sgResCache = array();
                while ($row = $DBRESULT3->fetchRow()) {
                    if (!isset($sgResCache[$row['sg_id']])) {
                        $sgResCache[$row['sg_id']] = array();
                    }
                    if ($row['acl_res_id']) {
                        $sgResCache[$row['sg_id']][] = $acl_group_id;
                        info(1, "Completing aclsg:$acl_group_id with service group " . $row['sg_id']);
                        $redis->setbit('aclsg:' . $acl_group_id, $row['sg_id'], 1);
                    }
                    else {
                        info(1, "Removing aclsg:$acl_group_id from service group " . $row['sg_id']);
                        $redis->setbit('aclsg:' . $acl_group_id, $row['sg_id'], 0);
                    }
                }
                $DBRESULT3->free();

                /* key = host id ; value = host name */

                // Filter
                $Host = getFilteredHostCategories($Host, $acl_group_id, $res2['acl_res_id']);
                $Host = getFilteredPollers($Host, $acl_group_id, $res2['acl_res_id']);

                /*
                 * Initialize and first filter
                 */
                foreach ($Host as $key => $value) {
                    info(1, "Completing aclh:$acl_group_id with host " . $key);
                    $redis->setbit('aclh:' . $acl_group_id, $key, 1);
                    $tab = getAuthorizedServicesHost($key, $acl_group_id, $res2['acl_res_id'], $authorizedCategories);
                    unset($tab);
                }
                unset($Host);

                /*
                 * Set meta services
                 */
                info(1, 'Getting meta services concerned by the Acl resource ' . $res2['acl_res_id']);
                $metaSql = 'SELECT meta_id 
                    FROM acl_resources_meta_relations
                    WHERE acl_res_id = ' . $pearDB->escape($res2['acl_res_id']);

                $res = $pearDB->query($metaSql);
                $metaRes = array();
                if ($res->numRows()) {
                    $hostId = $metaObj->getRealHostId();
                    while ($row = $res->fetchRow()) {
                        $svcId = $metaObj->getRealServiceId($row['meta_id']);
                        $k = 's:' . $hostId . ':' . $svcId;
                        if (!isset($metaRes[$k])) {
                            $metaRes[$k] = array();
                        }
                        $metaRes[$k][] = $acl_group_id;
                    }
                }
                info(2, '  ' . count($metaRes) . ' meta service(s) concerned by this Acl resource');

                $str = '';
                /* FIXME DBR: Let's store all the services with their ACL */

                /* ------------------------------------------------------------------
                 * reset Flags
                 */
                $pearDB->query("UPDATE `acl_resources` SET `changed` = '0' WHERE acl_res_id = '" . $res2["acl_res_id"] . "'");
            }
            $DBRESULT2->free();

            if ($debug) {
                $time_end = microtime_float2();
                $now = $time_end - $time_start;
                print round($now, 3) . " " . _("seconds") . "\n";
            }

            $cpt++;
            $pearDB->query("UPDATE acl_groups SET acl_group_changed = '0' WHERE acl_group_id = " . $pearDB->escape($acl_group_id));
        }

        /**
         * Redis specific work
         */

        $aclKeysNew = $redis->keys('aclh:*');
        info(1, "Getting new aclh:* keys: " . count($aclKeysNew));
        if (empty($aclKeysNew) && !empty($aclKeys)) {
            info(1, 'Cleaning acl groups...');
            $it = null;
            do {
                $hostKeys = $redis->scan($it, 'h:*');
                if ($hostKeys !== false) {
                    foreach ($hostKeys as $k) {
                        info(2, "  Cleaning acl groups from $k");
                        $redis->hSet($k, 'acl_groups', '');
                        $redis->rawCommand('FT.ADDHASH', 'hosts', $k, 1, 'REPLACE');
                    }
                }
            } while ($it > 0);
        }
        else {
            $aclh = $redis->mget($aclKeysNew);
            $itH = null;
            do {
                $hostKeys = $redis->scan($itH, 'h:*');
                if ($hostKeys !== false) {
                    foreach ($hostKeys as $k) {
                        info(1, "Working on host $k Acl...");
                        $host_id = explode(':', $k);
                        $host_id = $host_id[1];
                        $acl = array();
                        foreach ($aclh as $k => $ah) {
                            $acl_id = explode(':', $aclKeysNew[$k]);
                            if (bitfieldIsOn($ah, $host_id)) {
                                $acl[] = $acl_id[1];
                            }
                        }
                        if (!empty($acl)) {
                            $aclStr = implode(',', $acl);
                            info(2, "  Host h:$host_id Acl updated with Acl " . $aclStr);
                            $redis->rawCommand('FT.ADD', 'hosts', "h:$host_id", 1, 'REPLACE', 'PARTIAL', 'FIELDS',
                                               'acl_groups', $aclStr);
                        }
                        else {
                            info(2, "  Host h:$host_id has its Acl removed");
                            $redis->hSet("h:$host_id", 'acl_groups', '');
                            $redis->rawCommand('FT.ADDHASH', 'hosts', "h:$host_id", 1, 'REPLACE');
                        }

                        $itS = null;
                        do {
                            $svcKeys = $redis->scan($itS, "s:$host_id:*");
                            if ($svcKeys !== false) {
                                foreach ($svcKeys as $k) {
                                    $aclS = array();
                                    $sg = explode(',', $redis->hget($k, 'service_groups'));
                                    foreach ($sg as $ssg) {
                                        if (isset($sgResCache[$ssg])) {
                                            $aclS = array_merge($aclS, $sgResCache[$ssg]);
                                        }
                                    }
                                    $aclS = array_merge($acl, $aclS);
                                    if (!empty($aclS)) {
                                        $aclSStr = implode(',', array_keys(array_flip($aclS)));
                                        info(2, "  Service $k Acl updated with Acl $aclSStr");
                                        $redis->rawCommand('FT.ADD',
                                                'services', $k,
                                                1, 'REPLACE', 'PARTIAL',
                                                'FIELDS', 'acl_groups', $aclSStr);
                                    }
                                    else {
                                        info(2, "  Service $k Acl clean");
                                        $redis->hSet($k, 'acl_groups', '');
                                        $redis->rawCommand('FT.ADDHASH', 'services', $k, 1, 'REPLACE');
                                    }
                                }
                            }
                        } while ($itS > 0);
                    }
                }
            } while ($itH > 0);

            /* Work on meta services */
            foreach ($metaRes as $k => $acl) {
                $aclStr = implode(',', $acl);
                info(2, "  Meta service $k Acl updated with Acl $aclStr");
                $redis->rawCommand('FT.ADD',
                        'services', $k,
                        1, 'REPLACE', 'PARTIAL',
                        'FIELDS', 'acl_groups', $aclStr);
            }
        }

        /**
         * Include module specific ACL evaluation
         */
        $extensionsPaths = getModulesExtensionsPaths($pearDB);
        foreach ($extensionsPaths as $extensionPath) {
            require_once $extensionPath . 'centAcl-redis.php';
        }
    }

    /*
     * Remove lock
     */
    info(2, '  Closing the cron_operation');
    $redis->hSet('cron_operation:centAcl-redis.php',
          'last_execution_time', time() - $beginTime);
    $redis->hIncrBy('cron_operation:centAcl-redis.php', 'running', -1);

    /*
     * Close connection to databases
     */
    $pearDB->disconnect();
    $pearDBO->disconnect();
    info(2, '  Closing connections');
} catch (Exception $e) {
    programExit($e->getMessage(), 3);
}
