<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Covers_model extends MY_Model {

	/*
	* Método se subida de la portada, en caso de que ya exista una; se elimina
	* busca y crea un directorio en caso de que no exista, copia la portada original
	* a ese directorio, luego crea un 'thumb' de diferentes tamaños [large, medium y thumb]
	*
	* @return array con las 4 portadas.
	*
	* @autor dvaJi
	*/
	public function uploadCover($data, $type, $cover) {

		/*if (isset($data->cover) && $data->cover != NULL) {
		$this->removeCover($data);
	}*/

	$dir = "content/". $type . "/" . $data->stub . "_" . $data->uniqid . "/";

	// Copiar la portada original
	if (!file_exists($dir)) {
		mkdir($dir, 0777, true);
	}
	file_put_contents($dir . $cover->filename, base64_decode($cover->value));

  $ext = "." . pathinfo(parse_url($dir . $cover->filename)['path'], PATHINFO_EXTENSION);
  $newFilename = uniqid() . $ext;
  rename ($dir . $cover->filename, $dir . $newFilename);

	// Revisar si el archivo es en realidad una imagen
	if (!$imagedata = @getimagesize($dir . $newFilename)) {
		throw new RuntimeException("¡No es una imagen!");
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
			'source_image' => $dir . $newFilename,
			'new_image' => $dir . $key . "_" . $newFilename,
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
		$coverObj->id_magazine = $data->id;
		$coverObj->filename = $this->getTypeCovers($i) . (($i != 1)? "_":"") . $newFilename;
    $coverObj->def = 1;
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
public function removeCover($data, $covers) {
	/*$dir = "content/series/" . $data->stub . "_" . $data->uniqid . "/";

	if (!unlink($dir . $data->cover)) {
  	return false;
  }

  for ($i = 1; $i < 5; $i++) {
    if (!unlink($dir . "thumb_" . $data->thumbnail)) {
      return false;
    } else {
      $this->seriecovers->delete();
    }
  }

  $row = array('cover' => NULL);
  $this->update($data->id, $row);
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
