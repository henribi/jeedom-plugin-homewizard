<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

class homewizard extends eqLogic {
	use MipsEqLogicTrait;

	/**
	 * @return cron
	 */
	public static function setDaemon() {
		$cron = cron::byClassAndFunction(__CLASS__, 'daemon');
		if (!is_object($cron)) {
			$cron = new cron();
		}
		$cron->setClass(__CLASS__);
		$cron->setFunction('daemon');
		$cron->setEnable(1);
		$cron->setDeamon(1);
		$cron->setDeamonSleepTime(config::byKey('daemonSleepTime', __CLASS__, 5));
		$cron->setTimeout(1440);
		$cron->setSchedule('* * * * *');
		$cron->save();
		return $cron;
	}

	/**
	 * @return cron
	 */
	private static function getDaemonCron() {
		$cron = cron::byClassAndFunction(__CLASS__, 'daemon');
		if (!is_object($cron)) {
			return self::setDaemon();
		}
		return $cron;
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = '';
		$return['state'] = 'nok';
		$cron = self::getDaemonCron();
		if ($cron->running()) {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		// pour forcer le refreshInfos au démarrage du démon
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$productType = $eqLogic->getCmd('info', 'product_type');
			if (is_object($productType)) {
				$productType->setCache('productType', 0);
			}
		}
		$cron = self::getDaemonCron();
		$cron->run();

	}

	public static function deamon_stop() {
		$cron = self::getDaemonCron();
		$cron->halt();
	}

	public static function deamon_changeAutoMode($_mode) {
		$cron = self::getDaemonCron();
		$cron->setEnable($_mode);
		$cron->save();
	}

	public static function postConfig_daemonSleepTime($value) {
		self::setDaemon();
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			self::deamon_start();
		}
	}

	public static function daemon() {
		/** @var homewizard */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$eqLogic->refreshMeasures();
			// To avoid running refreshInfos each time. (Each: 3600 seconds)
			$productType = $eqLogic->getCmd('info', 'product_type');
			if (is_object($productType)) {
				$tspre = $productType->getCache('productType', 0);
				$tscur = time();
				if ($tscur >= $tspre + 3600) {
					$productType->setCache('productType', $tscur);
					$eqLogic->refreshInfos();
				}
			}

		}
	}

	public static function cron() {
		/** @var homewizard */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$currentImport = $eqLogic->getCmdInfoValue('total_power_import_kwh', 0);   //$eqLogic->getCmdInfoValue('totalImport', 0);

			/** @var homewizardCmd */
			$dayImport = $eqLogic->getCmd('info', 'dayImport');
			if (is_object($dayImport)) {
				$dayIndex = $dayImport->getCache('index', 0);
				if ($dayIndex == 0) {
					$dayImport->setCache('index', $currentImport);
					$dayIndex = $currentImport;
				}
				$dayImport->event(round($currentImport - $dayIndex, 3));
			}
			/** @var homewizardCmd */
			$monthImport = $eqLogic->getCmd('info', 'monthImport');
			if (is_object($monthImport)) {
				$monthIndex = $monthImport->getCache('index', 0);
				if ($monthIndex == 0) {
					$monthImport->setCache('index', $currentImport);
					$monthIndex = $currentImport;
				}
				$monthImport->event(round($currentImport - $monthIndex, 3));
			}

			$currentExport = $eqLogic->getCmdInfoValue('total_power_export_kwh', 0);  // $eqLogic->getCmdInfoValue('totalExport', 0);

			/** @var homewizardCmd */
			$dayExport = $eqLogic->getCmd('info', 'dayExport');
			if (is_object($dayExport)) {
				$dayIndex = $dayExport->getCache('index', 0);
				if ($dayIndex == 0) {
					$dayExport->setCache('index', $currentExport);
					$dayIndex = $currentExport;
				}
				$dayExport->event(round($currentExport - $dayIndex, 3));
			}
			/** @var homewizardCmd */
			$monthExport = $eqLogic->getCmd('info', 'monthExport');
			if (is_object($monthExport)) {
				$monthIndex = $monthExport->getCache('index', 0);
				if ($monthIndex == 0) {
					$monthExport->setCache('index', $currentExport);
					$monthIndex = $currentExport;
				}
				$monthExport->event(round($currentExport - $monthIndex, 3));
			}
		}
	}

	public static function dailyReset() {
		/** @var homewizard */
		foreach (self::byType(__CLASS__, true) as $eqLogic) {
			$currentImport = $eqLogic->getCmdInfoValue('total_power_import_kwh', 0);
			$currentExport = $eqLogic->getCmdInfoValue('total_power_export_kwh', 0);


			/** @var homewizardCmd */
			$dayImport = $eqLogic->getCmd('info', 'dayImport');
			if (is_object($dayImport)) {
				$dayImport->setCache('index', $currentImport);
			}
			/** @var homewizardCmd */
			$dayExport = $eqLogic->getCmd('info', 'dayExport');
			if (is_object($dayExport)) {
				$dayExport->setCache('index', $currentExport);
			}

			$date = new DateTime();
			$lastDay = $date->format('Y-m-t');
			$toDay = $date->format('Y-m-d');
			if ($lastDay === $toDay) {
				/** @var homewizardCmd */
				$monthImport = $eqLogic->getCmd('info', 'monthImport');
				if (is_object($monthImport)) {
					$monthImport->setCache('index', $currentImport);
				}
				/** @var homewizardCmd */
				$monthExport = $eqLogic->getCmd('info', 'monthExport');
				if (is_object($monthExport)) {
					$monthExport->setCache('index', $currentExport);
				}
			}
		}
	}

	private function refreshMeasures() {
		$host = $this->getConfiguration('host');
		if ($host == '') return;

		$port = $this->getConfiguration('port', 80);
		if ($port == '') return;

		$cfgTimeOut = "5";

		try {
			$url = "http://{$host}/api/v1/data";
   			$curl = curl_init($url);
   			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   			$json = curl_exec($curl);
   			curl_close($curl);
 
			if (!$json) {
				log::add(__CLASS__, 'warning', "Cannot connect to {$this->getName()} ({$host}:{$port})");
			} else {
				log::add(__CLASS__, 'info', "Connected to {$this->getName()} ({$host}:{$port})");
				//$results = [];
				$arJson = json_decode($json, true);
				foreach($arJson as $key => $value) {
					//log::add(__CLASS__, 'debug', "key: $key  valeur: $value");
					switch ($key) {
						case 'total_power_import_kwh':
						case 'total_power_import_t1_kwh':
						case 'total_power_import_t2_kwh':
						case 'total_power_export_kwh':
						case 'total_power_export_t1_kwh':
						case 'total_power_export_t2_kwh':
						case 'active_power_w':
						case 'active_power_l1_w':
						case 'active_power_l2_w':
						case 'active_power_l3_w':
						case 'active_voltage_l1_v':
						case 'active_voltage_l2_v':
						case 'active_voltage_l3_v':
						case 'active_current_l1_a':
						case 'active_current_l2_a':
						case 'active_current_l3_a':
						case 'montly_power_peak_w':
							$this->checkAndUpdateCmd($key, $value);
							//$results[$key] = $value;
							break;
						case 'active_tariff':
							if ($value == '1' ) {
                              $value = "HP";
                            } else if ($value == '2') {
                              $value = "HC";
                            } else {
                            	$value = '??';
                            }                            

							$this->checkAndUpdateCmd($key, $value);
							break;
						case 'montly_power_peak_timestamp':
							$current_date = substr($value, 4, 2) . '/' . substr($value, 2, 2) . '/' . substr($value, 0, 2) . '  ' . substr($value, 6, 2) . ':' . substr($value, 8, 2) . ':' . substr($value, 10, 2);
							$this->checkAndUpdateCmd($key, $current_date);
							break;
						default:
							break;
					}
				}
			}
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'error', "Error with {$this->getName()} ({$host}:{$port}): {$th->getMessage()}");
		} finally {
			;
		}

		log::add(__CLASS__, 'info', "Successfuly refreshed values of {$this->getName()} ({$host}:{$port})");

	}

	private function refreshInfos() {
		$host = $this->getConfiguration('host');
		if ($host == '') return;

		$port = $this->getConfiguration('port', 80);
		if ($port == '') return;

		$cfgTimeOut = "5";

		try {
			$url = "http://{$host}/api";
   			$curl = curl_init($url);
   			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   			$json = curl_exec($curl);
   			curl_close($curl);
 
			if (!$json) {
				log::add(__CLASS__, 'warning', "Cannot connect to {$this->getName()} ({$host}:{$port})");
			} else {
				log::add(__CLASS__, 'info', "Connected to {$this->getName()} ({$host}:{$port})");

				$arJson = json_decode($json, true);
				$results = [];

				foreach($arJson as $key => $value) {
					//log::add(__CLASS__, 'debug', "key: $key  valeur: $value");
					switch ($key) {
						case 'product_type':
						case 'product_name':
						case 'serial':
						case 'firmware_version':
							$this->checkAndUpdateCmd($key, $value);
							$results[$key] = $value;
							break;
						default:
							break;
					}
				}
			}
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'error', "Error with {$this->getName()} ({$host}:{$port}): {$th->getMessage()}");
		} finally {
			;
		}

		log::add(__CLASS__, 'info', "Successfuly refreshed infos of {$this->getName()} ({$host}:{$port})");

	}


	private static function getTopicPrefix() {
		return config::byKey('topic_prefix', __CLASS__, 'lowi', true);
	}

	private static function tryPublishToMQTT($topic, $value) {
		try {
			$_MQTT2 = 'mqtt2';
			if (!class_exists($_MQTT2)) {
				log::add(__CLASS__, 'debug', __('Le plugin mqtt2 n\'est pas installé', __FILE__));
				return;
			}
			$topic = self::getTopicPrefix() . '/' . $topic;
			$_MQTT2::publish($topic, $value);
			log::add(__CLASS__, 'debug', "published to mqtt: {$topic}={$value}");
		} catch (\Throwable $th) {
			log::add(__CLASS__, 'warning', __('Une erreur s\'est produite dans le plugin mqtt2:', __FILE__) . $th->getMessage());
		}
	}

	public function createCommands() {
		log::add(__CLASS__, 'debug', "Checking commands of {$this->getName()}");

		$this->createCommandsFromConfigFile(__DIR__ . '/../config/p1.json', 'p1');

		return $this;
	}



	public function postInsert() {
		$this->createCommands();
	}


	public function postSave() {
		// $host = $this->getConfiguration('host');
		// if ($host == '') return;

	}
}

class homewizardCmd extends cmd {

	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
		log::add('homewizard', 'debug', "command: {$this->getLogicalId()} on {$eqLogic->getLogicalId()} : {$eqLogic->getName()}");
	}
}
