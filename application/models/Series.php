<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Series extends MY_Model {
	protected $table  = 'series';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'associated_names_series',
			'foreign'   => 'id_series',
			'variable'  => 'names'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_magazines',
			'foreign'   => 'id_series',
			'variable'  => 'magazines'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_staff',
			'foreign'   => 'id_series',
			'variable'  => 'staff'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'genres',
			'foreign'   => 'id_series',
			'variable'  => 'genres'
		));

		$this->addRelation(array(
			'primary'   => 'id_demographic',
			'table'     => 'demographics',
			'foreign'   => 'id',
			'variable'  => 'demographic',
			'column' => 'name'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_covers',
			'foreign'   => 'id_series',
			'variable'  => 'cover'
		));

		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_ranking',
			'foreign'   => 'id_series',
			'variable'  => 'ranking'
		));
	}

	/*
	* Reemplaza los id por los nombres de cada género y quita propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getGenres($genres) {
		$genresArray = array();
		foreach ($genres as $key => $value) {
			$value->id = $value->id_typegenres;
			$value->name = $this->genres->find($value->id)->name;
			unset($value->typegenre_id);
			unset($value->series_id);
			array_push($genresArray, $value);
		}

		return $genresArray;
	}

	/*
	* Reemplaza los id por los nombres de cada staff, el rol, imagen, y quita propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getStaff($staff) {
		$staffArray = array();
		foreach ($staff as $key => $value) {
			//$value->id = $value->id_staff;
			$elstaff = $this->staff->find($value->id_staff);
			$conditions = array('id_staff' => $value->id_staff, 'def' => 1);
			$value->name = $this->staffaltnames->find($conditions)->name;
			$value->image = ($elstaff->image != NULL || $elstaff->image != '') ? $elstaff->image:'default.png';
			$value->stub = $elstaff->stub;
			$value->rol = $this->roles->find($value->id_roles)->name;
			unset($value->staff_id);
			unset($value->series_id);
			array_push($staffArray, $value);
		}
		return $staffArray;
	}

	public function getStaffFormated($staff, $idSeries) {
		if (empty($staff) || count($staff) === 1) {
			return $staff;
		}


		$this->db->select('staff.stub, staff.uniqid, serie_staff.id, serie_staff.id_series, serie_staff.id_staff, associated_names_mangaka.name, staff_covers.filename AS image, GROUP_CONCAT(roles.name) AS rol');
    $this->db->from('serie_staff');
    $this->db->join('staff', 'staff.id = serie_staff.id_staff');
    $this->db->join('associated_names_mangaka', 'associated_names_mangaka.id_staff = staff.id');
		$this->db->join('staff_covers', 'staff_covers.id_staff = staff.id', 'left');
		$this->db->join('roles', 'roles.id = serie_staff.id_roles');
    $this->db->where('serie_staff.id_series', $idSeries);
    $this->db->where('associated_names_mangaka.def', 1);
		$this->db->group_by("serie_staff.id_staff");
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
	* Reemplaza los id por los nombres de cada revista y quita propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getMagazines($magazines) {
		$magazinesArray = array();
		foreach ($magazines as $key => $value) {
			$value = $this->magazines->find($value->id_magazines);
			array_push($magazinesArray, $value);
		}

		return $magazinesArray;
	}

	/*
	* Obtiene los nombres de los scan de cada release y se quitan propiedades innecesarias.
	*
	* @autor dvaJi
	*/
	public function getReleases($releases) {
		$releasesArray = array();
		foreach($releases as $key => $value) {
			unset($value->serie);
			unset($value->series_id);
			$groupsArray = array();
			if ($value->groups != null) {
				foreach ($value->groups as $key2 => $scan) {
					$scan->name = $this->scans->find($scan->group_id)->name;
					unset($scan->release_id);
					array_push($groupsArray, $scan);
				}
			}
			$value->groups = $groupsArray;
			array_push($releasesArray, $value);
		}
		return $releasesArray;
	}

	/*
	* Se obtienen las portadas según su tipo
	* @example
	* 		1 = original
	* 		2 = large
	* 		3 = medium
	* 		4 = thumb
	*
	* @autor dvaJi
	*/
	public function getCovers($covers, $serie) {
		$coversObj = new \stdClass;
		foreach ($covers as $key => $value) {
			$value->path_full = "/api/content/series/" . $serie->stub . "_" . $serie->uniqid . "/" . $value->filename;
			unset($value->id_series);
			unset($value->created);
			unset($value->updated);
			if ($value->type == 1) {
				$coversObj->original = $value;
				unset($value->type);

			} else if ($value->type == 2) {
				$coversObj->large = $value;
				unset($value->type);

			} else if ($value->type == 3) {
				$coversObj->medium = $value;
				unset($value->type);

			} else if ($value->type == 4) {
				$coversObj->thumb = $value;
				unset($value->type);
			}
		}

		return $coversObj;
	}

	/*
	* Obtiene el nombre por defecto de la serie, con el valor 1 de def
	*
	* @autor dvaJi
	*/
	public function getNames($names) {
		$namesArray = array();
		foreach ($names as $key => $value) {
			if ($value->def == 0) {
				array_push($namesArray, $value->name);
			}
		}

		return $namesArray;
	}

	/*
	* Obtiene los nombres alternativos de la serie.
	*
	* @autor dvaJi
	*/
	public function getDefaultName($names) {
		foreach ($names as $key => $value) {
			if ($value->def == 1) {
				return $value->name;
			}
		}

		return $namesArray;
	}

	/*
	* Validar Order
	*
	* @autor dvaJi
	*/
	private function validateOrder($order) {
		var_dump($order);
		switch ($order) {
			case 'name': return false;
			case 'created': return false;
			case 'updated': return false;
			case 'publicationDate': return false;
			default: return true;
		}
	}

	/*
	* Validar Tipo
	*
	* @autor dvaJi
	*/
	private function validateType($type) {
		var_dump($type);
		switch ($type) {
			case 'Manga': return false;
			case 'Manhwa': return false;
			case 'Manhua': return false;
			case 'Artbook': return false;
			case 'Doujinshi': return false;
			case 'Drama CD': return false;
			case 'Novela Ligera': return false;
			default: return true;
		}
	}

}
