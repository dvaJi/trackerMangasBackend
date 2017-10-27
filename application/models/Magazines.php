<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Magazines extends MY_Model {
	protected $table  = 'magazines';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_magazines',
			'foreign'   => 'id_magazines',
			'variable'  => 'series'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'magazines_covers',
			'foreign'   => 'id_magazine',
			'variable'  => 'cover'
		));
	}

	/*
	 * Obtiene las series de la revista
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

			$value->name = $serie->name;
			$value->publicationDate = $serie->publication_date;
			$value->serieId = $serie->id;
			$value->stub = $serie->stub;
			unset($value->id_magazines);
			array_push($seriesArray, $value);
		}

		return $seriesArray;
	}
}
