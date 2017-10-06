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
	public function uploadCover($staff, $cover) {

		/*if (isset($staff->cover) && $staff->cover != NULL) {
			$this->removeCover($staff);
		}*/

		$dir = "content/staff/" . $staff->stub . "_" . $staff->uniqid . "/";

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
	 * Retorna el tipo de cover según el valor numerico
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
