<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Series extends MY_Model {
	protected $table  = 'series';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',      
			'table'     => 'associated_names_series', 
			'foreign'   => 'series_id',
			'variable'  => 'names'
		));

		$this->addRelation(array(
			'primary'   => 'id',      
			'table'     => 'serie_magazines', 
			'foreign'   => 'series_id',
			'variable'  => 'magazines'
		));

		$this->addRelation(array(
			'primary'   => 'id',      
			'table'     => 'serie_staff', 
			'foreign'   => 'series_id',
			'variable'  => 'staff'
		));

		$this->addRelation(array(
			'primary'   => 'id',      
			'table'     => 'genres', 
			'foreign'   => 'series_id',
			'variable'  => 'genres'
		));
	}

	public function getGenres($genres) {
		foreach ($genres as $key => $value) {
            $value->id = $value->typegenre_id;
            $value->name = $this->genres->find($value->id)->name;
            unset($value->typegenre_id);
            unset($value->series_id);
        }

		return $genres;
	}

	public function getStaff($staff) {
		foreach ($staff as $key => $value) {
            $value->id = $value->staff_id;
            $value->name = $this->staff->find($value->id)->name;
			$value->rol = $this->staffRol->find($value->rol)->name;
            unset($value->staff_id);
            unset($value->series_id);
        }

		return $staff;
	}

	public function getMagazines($magazines) {
		foreach ($magazines as $key => $value) {
            $value->id = $value->magazines_id;
            $value->name = $this->magazines->find($value->id)->name;
            unset($value->magazines_id);
            unset($value->series_id);
        }

		return $magazines;
	}

}