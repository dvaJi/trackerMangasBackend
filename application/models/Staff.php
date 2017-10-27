<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Staff extends MY_Model {
	protected $table  = 'staff';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'associated_names_mangaka',
			'foreign'   => 'id_staff',
			'variable'  => 'names'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'staff_covers',
			'foreign'   => 'id_staff',
			'variable'  => 'cover'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_staff',
			'foreign'   => 'id_staff',
			'variable'  => 'series'
		));
	}

	/*
	 * Obtiene el nombre por defecto del staff, con el valor 1 de def
	 *
	 * @autor dvaJi
	*/
	public function getNames($names) {
		$namesArray = array();
		foreach ($names as $key => $value) {
			if ($value->def == 0) {
				unset($value->id_staff);
				unset($value->def);
				array_push($namesArray, $value);
			}
		}

		return $namesArray;
	}

	/*
	 * Obtiene los nombres alternativos del staff.
	 *
	 * @autor dvaJi
	*/
	public function getDefaultName($names) {
		foreach ($names as $key => $value) {
			if ($value->def == 1) {
				return $value->name;
			}
		}

		return null;
	}

	/*
	 * Obtiene las series del staff.
	 *
	 * @autor dvaJi
	*/
	public function getSeries($series) {

		if ($series == NULL) {
			return null;
		}

		$seriesArray = array();
		foreach ($series as $key => $value) {

			$serie = $this->series->find($value->id_series);
			$serie->name = $this->seriesaltnames->find(array('id_series' => $value->id_series, 'def' => 1))->name;
			$rol = $this->roles->find($value->id_roles);


			$value->name = $serie->name;
			$value->publicationDate = $serie->publication_date;
			$value->serieId = $serie->id;
			$value->stub = $serie->stub;
			$value->rol = $rol->name;
			unset($value->id_series);
			unset($value->id_roles);
			unset($value->id_staff);
			array_push($seriesArray, $value);
		}

		return $seriesArray;
	}

}
