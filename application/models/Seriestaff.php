<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Seriestaff extends MY_Model {
	protected $table  = 'serie_staff';

	public function generateStaffArray($staffs, $idSerie) {
		$staff = array();
		foreach ($staffs as $key => $staff) {
			$staffObj = new \stdClass;
			$staffObj->id_series = $idSerie;
			$staffObj->id_staff = intval($staff->id_staff);
			$staffObj->id_roles = intval($staff->id_roles);
			array_push($staff, $staffObj);
		}

		return $staff;
	}
}
