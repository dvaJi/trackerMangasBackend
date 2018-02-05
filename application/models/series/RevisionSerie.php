<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class RevisionSerie extends MY_Model {
	protected $table  = 'revision_serie';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'revision_serie_names',
			'foreign'   => 'id_revision_serie',
			'variable'  => 'names'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'revision_serie_genres',
			'foreign'   => 'id_revision_serie',
			'variable'  => 'genres'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'revision_serie_staff',
			'foreign'   => 'id_revision_serie',
			'variable'  => 'staff'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'revision_serie_magazine',
			'foreign'   => 'id_revision_serie',
			'variable'  => 'magazines'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));

		$this->addRelation(array(
			'primary'   => 'id_demographic',
			'table'     => 'demographics',
			'foreign'   => 'id',
			'variable'  => 'demographic'
		));
	}

	/*
	* Obtiene los nombres alternativos de la serie.
	*
	* @autor dvaJi
	*/
	public function getDefaultName($names) {
		if ($names == null) return array();
		
		foreach ($names as $key => $value) {
			if ($value->def == 1) {
				return $value->name;
			}
		}

		return $namesArray;
	}

	public function getPendingStaffFormated($staff, $idSeries) {
		if (empty($staff)) {
			return $staff;
		}

		$this->db->select('staff.stub, staff.uniqid, revision_serie_staff.id, revision_serie_staff.id_revision_serie, revision_serie_staff.id_staff, associated_names_mangaka.name, staff_covers.filename AS image, GROUP_CONCAT(roles.name) AS rol');
    $this->db->from('revision_serie_staff');
    $this->db->join('staff', 'staff.id = revision_serie_staff.id_staff');
    $this->db->join('associated_names_mangaka', 'associated_names_mangaka.id_staff = staff.id');
		$this->db->join('staff_covers', 'staff_covers.id_staff = staff.id', 'left');
		$this->db->join('roles', 'roles.id = revision_serie_staff.id_roles');
    $this->db->where('revision_serie_staff.id_revision_serie', $idSeries);
    $this->db->where('associated_names_mangaka.def', 1);
		$this->db->group_by('revision_serie_staff.id_staff');
    $query = $this->db->get();

    if (!empty($query->result())) {
			$staffs = array();
			foreach ($query->result() as $key => $value) {
				$roles = explode(',', $value->rol);
				$value->rol = array_values(array_unique($roles));
				$value->image = ($value->image != NULL || $value->image != '') ? $value->image:'default.png';
				if ($value->image == 'default.png') {
					$value->image_url_full = "/api/content/staff/" . $value->image;
				} else {
					$value->image_url_full = "/api/content/staff/" . $value->stub . "_" . $value->uniqid ."/" . $value->image;
				}
			}
      return $query->result();
    } else {
      return $query->result();
    }
	}

	/*
	* Reemplaza los id por los nombres de cada género y quita propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getGenres($genres) {
		if (empty($genres)) {
			return $genres;
		}
		$genresArray = array();
		foreach ($genres as $key => $value) {
			$value->id = $value->id_genre;
			$value->name = $this->genres->find($value->id)->name;
			unset($value->id_genres);
			unset($value->id_revision_serie);
			array_push($genresArray, $value);
		}

		return $genresArray;
	}

	/*
	* Reemplaza los id por los nombres de cada revista y quita propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getMagazines($magazines) {
		if (empty($magazines)) {
			return $magazines;
		}
		$magazinesArray = array();
		foreach ($magazines as $key => $value) {
			$value = $this->magazines->find($value->id_magazines);
			array_push($magazinesArray, $value);
		}

		return $magazinesArray;
	}

}
