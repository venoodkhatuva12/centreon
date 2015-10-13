<?php

require_once "@CENTREON_ETC@/centreon.conf.php";

require_once dirname(__FILE__) . '/backend.class.php';
require_once dirname(__FILE__) . '/abstract/object.class.php';
require_once dirname(__FILE__) . '/abstract/objectXML.class.php';
require_once dirname(__FILE__) . '/hosttemplate.class.php';
require_once dirname(__FILE__) . '/command.class.php';
require_once dirname(__FILE__) . '/timeperiod.class.php';
require_once dirname(__FILE__) . '/hostgroup.class.php';
require_once dirname(__FILE__) . '/servicegroup.class.php';
require_once dirname(__FILE__) . '/contact.class.php';
require_once dirname(__FILE__) . '/contactgroup.class.php';
require_once dirname(__FILE__) . '/servicetemplate.class.php';
require_once dirname(__FILE__) . '/service.class.php';
require_once dirname(__FILE__) . '/media.class.php';
require_once dirname(__FILE__) . '/connector.class.php';
require_once dirname(__FILE__) . '/macro.class.php';
require_once dirname(__FILE__) . '/host.class.php';
require_once dirname(__FILE__) . '/severity.class.php';
require_once dirname(__FILE__) . '/escalation.class.php';
require_once dirname(__FILE__) . '/dependency.class.php';
require_once dirname(__FILE__) . '/meta_timeperiod.class.php';
require_once dirname(__FILE__) . '/meta_command.class.php';
require_once dirname(__FILE__) . '/meta_host.class.php';
require_once dirname(__FILE__) . '/meta_service.class.php';
require_once dirname(__FILE__) . '/resource.class.php';
require_once dirname(__FILE__) . '/engine.class.php';
require_once dirname(__FILE__) . '/broker.class.php';
require_once dirname(__FILE__) . '/correlation.class.php';

class Generate {
    private $generate_index_data = 1;
    private $poller_cache = array();
    private $backend_instance = null;
    private $current_poller = null;
    private $installed_modules = null;
    private $module_objects = null;
    
    public function __construct() {
        $this->backend_instance = Backend::getInstance();
    }
    
    private function generateIndexData($localhost=0) {
        if ($this->generate_index_data == 0) {
            return 0;
        }
        
        $service_instance = Service::getInstance();
        $host_instance = Host::getInstance();
        $services = &$service_instance->getGeneratedServices();
        
        try {
            $stmt = $this->backend_instance->db_cs->prepare("INSERT INTO index_data (host_id, service_id, host_name, service_description) VALUES (:host_id, :service_id, :host_name, :service_description) ON DUPLICATE KEY UPDATE host_name=VALUES(host_name), service_description=VALUES(service_description)");
            $this->backend_instance->db_cs->beginTransaction();
            foreach ($services as $host_id => &$values) {
                foreach ($values as $service_id) {
                    $stmt->bindParam(':host_name', $host_instance->getString($host_id, 'host_name'), PDO::PARAM_STR);
                    $stmt->bindParam(':service_description', $service_instance->getString($service_id, 'service_description'), PDO::PARAM_STR);
                    $stmt->bindParam(':host_id', $host_id, PDO::PARAM_INT);
                    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
            
            # Meta services
            if ($localhost == 1) {
                $meta_services = &MetaService::getInstance()->getGeneratedServices();
                $host_id = MetaHost::getInstance()->getHostIdByHostName('_Module_Meta');
                foreach ($meta_services as $meta_id) {
                    $stmt->bindValue(':host_name', '_Module_Meta', PDO::PARAM_STR);
                    $stmt->bindValue(':service_description', '_meta_' . $meta_id, PDO::PARAM_STR);
                    $stmt->bindParam(':host_id', $host_id, PDO::PARAM_INT);
                    $stmt->bindParam(':service_id', $meta_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
            
            $this->backend_instance->db_cs->commit();
        } catch (Exception $e) {
            $this->backend_instance->db_cs->rollback();
            throw new Exception('Exception received : ' .  $e->getMessage() . "\n");
            throw new Exception($e->getFile() . "\n");
        }
    }

    private function getPollerFromId($poller_id) {
        $stmt = $this->backend_instance->db->prepare("SELECT id, localhost, monitoring_engine, centreonconnector_path FROM nagios_server WHERE id = :poller_id");
        $stmt->bindParam(':poller_id', $poller_id, PDO::PARAM_INT);
        $stmt->execute();
        $this->current_poller = array_pop($stmt->fetchAll(PDO::FETCH_ASSOC));
        if (is_null($this->current_poller)) {
            throw new Exception("Cannot find poller id '" . $poller_id ."'");
        }
    }
    
    private function getPollerFromName($poller_name) {
        $stmt = $this->backend_instance->db->prepare("SELECT id, localhost, monitoring_engine, centreonconnector_path FROM nagios_server WHERE name = :poller_name");
        $stmt->bindParam(':poller_name', $poller_name, PDO::PARAM_STR);
        $stmt->execute();
        $this->current_poller = array_pop($stmt->fetchAll(PDO::FETCH_ASSOC));
        if (is_null($this->current_poller)) {
            throw new Exception("Cannot find poller name '" . $poller_name ."'");
        }
    }
    
    public function resetObjectsEngine() {
        Host::getInstance()->reset();
        HostTemplate::getInstance()->reset();
        Service::getInstance()->reset();
        ServiceTemplate::getInstance()->reset();
        Command::getInstance()->reset();
        Contact::getInstance()->reset();
        Contactgroup::getInstance()->reset();
        Hostgroup::getInstance()->reset();
        Servicegroup::getInstance()->reset();
        Timeperiod::getInstance()->reset();
        Escalation::getInstance()->reset();
        Dependency::getInstance()->reset();
        MetaCommand::getInstance()->reset();
        MetaTimeperiod::getInstance()->reset();
        MetaService::getInstance()->reset();
        MetaHost::getInstance()->reset();
        Connector::getInstance()->reset();
        Resource::getInstance()->reset();
        Correlation::getInstance()->reset();
        $this->resetModuleObjects();
    }
    
    private function configPoller() {
        $this->backend_instance->initPath($this->current_poller['id']);
        $this->backend_instance->setPollerId($this->current_poller['id']);
        $this->resetObjectsEngine();

        Host::getInstance()->generateFromPollerId($this->current_poller['id'], $this->current_poller['localhost']);
        Engine::getInstance()->generateFromPoller($this->current_poller);
        $this->generateModuleObjects();
        $this->backend_instance->movePath($this->current_poller['id']);

        $this->backend_instance->initPath($this->current_poller['id'], 2);
        # Correlation files are always generated on central poller
        if (Correlation::getInstance()->hasCorrelation()) {
            Correlation::getInstance()->generateFromPollerId($this->current_poller['id'], $this->current_poller['localhost']);
        }
        Broker::getInstance()->generateFromPoller($this->current_poller);
        $this->backend_instance->movePath($this->current_poller['id']);
        
        $this->generateIndexData($this->current_poller['localhost']);
    }
    
    public function configPollerFromName($poller_name) {
        try {
            $this->getPollerFromName($poller_name);
            $this->configPoller();
        } catch (Exception $e) {
            throw new Exception('Exception received : ' .  $e->getMessage() . " [file: " . $e->getFile()  . "] [line: " . $e->getLine() . "]\n");
            $this->backend_instance->cleanPath();
        }
    }
    
    public function configPollerFromId($poller_id) {
        try {
            if (is_null($this->current_poller)) {
                $this->getPollerFromId($poller_id);
            }
            $this->configPoller();
        } catch (Exception $e) {
            throw new Exception('Exception received : ' .  $e->getMessage() . " [file: " . $e->getFile()  . "] [line: " . $e->getLine() . "]\n");
            $this->backend_instance->cleanPath();
        }
    }
    
    public function configPollers() {
        $stmt = $this->backend_instance->db->prepare("SELECT id, localhost, monitoring_engine, centreonconnector_path FROM nagios_server WHERE ns_activate = '1'");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $this->current_poller = $value;
            $this->configPollerFromId($this->current_poller['id']);
        }
    }

    public function getInstalledModules() {
        if (!is_null($this->installed_modules)) {
            return $this->installed_modules;
        }
        $this->installed_modules = array();
        $stmt = $this->backend_instance->db->prepare("SELECT name FROM modules_informations");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            $this->installed_modules[] = $value['name'];
        }
    }

    public function getModuleObjects() {
        $this->getInstalledModules();

        global $centreon_path;

        foreach ($this->installed_modules as $module) {
            if ($files = glob($centreon_path . 'www/modules/' . $module . '/generate_files/*.class.php')) {
                foreach ($files as $full_file) {
                    require_once $full_file;
                    $file_name = str_replace('.class.php', '', basename($full_file));
                    if (class_exists(ucfirst($file_name))) {
                        $this->module_objects[] = ucfirst($file_name);
                    }
                }
            }
        }
    }

    public function generateModuleObjects() {
        if (is_null($this->module_objects)) {
            $this->getModuleObjects();
        }
        foreach ($this->module_objects as $module_object) {
            $module_object::getInstance()->generateFromPollerId($this->current_poller['id'], $this->current_poller['localhost']);
        }
    }

    public function resetModuleObjects() {
        if (is_null($this->module_objects)) {
            $this->getModuleObjects();
        }
        foreach ($this->module_objects as $module_object) {
            $module_object::getInstance()->reset();
        }
    }
}

?>