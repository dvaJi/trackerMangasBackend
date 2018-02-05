<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Seriesaltnames extends MY_Model {
	protected $table  = 'associated_names_series';

	public function generateNamesArray($name, $idSerie) {
		$nombresAlt = array();
		foreach ($name as $key => $altNames) {
			$nombresAltObj = new \stdClass;
			$nombresAltObj->id_series = $idSerie;
			$nombresAltObj->name = $altNames->name;
			$nombresAltObj->def = intval($altNames->def);
			array_push($nombresAlt, $nombresAltObj);
		}

		return $nombresAlt;
	}
}
