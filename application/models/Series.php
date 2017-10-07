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
			$value->id = $value->id_staff;
			$elstaff = $this->staff->find($value->id);
			$conditions = array('id_staff' => $value->id, 'def' => 1);
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
	* Método se subida de la portada, en caso de que ya exista una; se elimina
	* busca y crea un directorio en caso de que no exista, copia la portada original
	* a ese directorio, luego crea un 'thumb' de diferentes tamaños [large, medium y thumb]
	*
	* @return array con las 4 portadas.
	*
	* @autor dvaJi
	*/
	public function uploadCover($serie, $cover) {

		/*if (isset($serie->cover) && $serie->cover != NULL) {
		$this->removeCover($serie);
	}*/

	$dir = "content/series/" . $serie->stub . "_" . $serie->uniqid . "/";

	// Copiar la portada original
	if (!file_exists($dir)) {
		mkdir($dir, 0777, true);
	}
	file_put_contents($dir . $cover->filename, base64_decode($cover->value));

	// Revisar si el archivo es en realidad una imagen
	if (!$imagedata = @getimagesize($dir . $cover->filename)) {
		return false;
	}

	$this->load->library('image_lib');
	// Array con los distintos tamaños que se requieren
	$image_sizes = array(
		'thumb' => array(410, 100),
		'medium' => array(560, 300),
		'large' => array(800, 600)
	);
	foreach ($image_sizes as $key => $resize) {

		$config = array(
			'source_image' => $dir . $cover->filename,
			'new_image' => $dir . $key . "_" . $cover->filename,
			'maintain_ration' => true,
			'quality' => 100,
			'width' => $resize[0],
			'height' => $resize[1]
		);

		$this->image_lib->initialize($config);
		if (!$this->image_lib->resize()) {
			return false;
		}
		$this->image_lib->clear();
	}


	// Ahora se crea el array con las portadas.
	$coversArray = array();
	for ($i = 1; $i < 5; $i++) {
		$coverObj = new \stdClass;
		$coverObj->id_staff = $staff->id;
		$coverObj->filename = $this->getTypeCovers($i) . (($i != 1)? "_":"") . $cover->filename;
		$coverObj->type = $i;
		$coverObj->adult = 0;
		$coverObj->height = ($i === 1) ? $imagedata["1"] : $image_sizes[$this->getTypeCovers($i)][0];
		$coverObj->width = ($i === 1) ? $imagedata["0"] : $image_sizes[$this->getTypeCovers($i)][1];
		$coverObj->mime = image_type_to_mime_type($imagedata["2"]);
		$coverObj->size = filesize($dir . $coverObj->filename);
		$coverObj->created = date("Y-m-d H:i:s");
		$coverObj->updated = date("Y-m-d H:i:s");

		array_push($coversArray, $coverObj);
	}

	return $coversArray;
}

/*
* Elimina las portadas de una serie.
* TODO TERMINAR ESTO :(
* @autor dvaJi
*/
public function removeCover($serie, $covers) {
	/*$dir = "content/series/" . $serie->stub . "_" . $serie->uniqid . "/";

	if (!unlink($dir . $serie->cover)) {
	return false;
}

for ($i = 1; $i < 5; $i++) {
if (!unlink($dir . "thumb_" . $serie->thumbnail)) {
return false;
} else {
$this->seriecovers->delete();
}
}

$row = array('cover' => NULL);
$this->update($serie->id, $row);
return true;*/
}

/*
* Retorna el tipo de cover según el tipo
*
* @autor dvaJi
*/
private function getTypeCovers($type) {
	if ($type == 1) {
		return "";

	} else if ($type == 2) {
		return "large";

	} else if ($type == 3) {
		return "medium";

	} else if ($type == 4) {
		return "thumb";
	}
}

}
