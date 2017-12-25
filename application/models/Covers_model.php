<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Covers_model extends MY_Model {

	/**
	* Se obtienen las portadas según su tipo
	* @example
	* 		[0] = original
	* 		[1] = large
	* 		[2] = medium
	* 		[3] = thumb
	*
	* @author dvaJi
	*/
	public function getCovers($data, $type, $idname, $covers) {
		$coversObj = new \stdClass;

		/**
		*	En caso de que no tenga una portada, se reemplazará por una por defecto
		* la cual se encuentra en su respectivo directorio
		* @example: /content/staff/default.png
		* @author dvaJi
		*/
		if ($covers == NULL) {
			for ($i = 1; $i < 5; $i++) {
				$cover = new \stdClass;
				$cover->path_full = "/api/content/" . $type . "/default.png";
				$cover->id = $data->id;
				$cover->filename = "default.png";
				$cover->type = $this->getFullTypeCovers($i);
				$cover->adult = 0;

				$coversObj->{$this->getFullTypeCovers($i)} = $cover;

			}

			return $coversObj;
		}

		foreach ($covers as $key => $value) {
			$value->path_full = "/api/content/" . $type . "/" . $data->stub . "_" . $data->uniqid . "/" . $value->filename;
			unset($value->{$idname});
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

	/**
	* Método de subida de la portada, en caso de que ya exista una; se elimina
	* busca y crea un directorio en caso de que no exista, copia la portada original
	* a ese directorio, luego crea un 'thumb' de diferentes tamaños [large, medium y thumb]
	*
	* @return array con las 4 portadas.
	* @author dvaJi
	*/
	public function uploadCover($data, $type, $idname, $cover) {

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
			'thumb' => array(240, 300),
			'medium' => array(300, 560),
			'large' => array(600, 800)
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

		//Resize original
		if ($imagedata["1"] > 1200) {
			$config = array(
				'source_image' => $dir . $newFilename,
				'new_image' => $dir . $newFilename,
				'maintain_ration' => true,
				'overwrite' => true,
				'quality' => 100,
				'width' => 884,
				'height' => 1200
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
			$coverObj->{$idname} = $data->id;
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

	/**
	* Método de subida de la portada, en caso de que ya exista una; se elimina
	* busca y crea un directorio en caso de que no exista, copia la portada original
	* a ese directorio, luego crea un 'thumb' de diferentes tamaños [large, medium y thumb]
	*
	* @return array con las 4 portadas.
	* @author dvaJi
	*/
	public function MoveCoverAndCreateThumbs($data, $type, $idname, $cover) {

		$oldDir = "content/pending_". $type . "/" . $data->stub . "_" . $data->uniqid;
		$newDir = "content/". $type . "/" . $data->stub . "_" . $data->uniqid;

		// Mover el directorio de pending a su directorio correspondiente mediante $type
		if (!rename($oldDir, $newDir)) {
        if (copy ($oldDir, $newDir)) {
            unlink($oldDir);
        }
    }

		$dir = $newDir . "/";

		// Revisar si el archivo es en realidad una imagen
		if (!$imagedata = @getimagesize($dir . $cover)) {
			throw new RuntimeException("¡No es una imagen!");
		}

		$this->load->library('image_lib');
		// Array con los distintos tamaños que se requieren
		$image_sizes = array(
			'thumb' => array(240, 300),
			'medium' => array(300, 560),
			'large' => array(600, 800)
		);
		foreach ($image_sizes as $key => $resize) {

			$config = array(
				'source_image' => $dir . $cover,
				'new_image' => $dir . $key . "_" . $cover,
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

		//Resize original
		if ($imagedata["1"] > 1200) {
			$config = array(
				'source_image' => $dir . $cover,
				'new_image' => $dir . $cover,
				'maintain_ration' => true,
				'overwrite' => true,
				'quality' => 100,
				'width' => 884,
				'height' => 1200
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
			$coverObj->{$idname} = $data->id;
			$coverObj->filename = $this->getTypeCovers($i) . (($i != 1)? "_":"") . $cover;
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

	/**
	* Subida de portada original, este redimensiona la imagen en caso de que
	* la imagen sobre pase los 1200px de altura, y se almacena en pending_{$type}
	*
	* @return filename
	* @author dvaJi
	*/
	public function uploadPendingCover($data, $type, $idname, $cover) {

		$dir = "content/pending_". $type . "/" . $data->stub . "_" . $data->uniqid . "/";

		// Copiar la portada original
		if (!file_exists($dir)) {
			mkdir($dir, 0777, true);
		}

		$ext = "." . pathinfo($dir . $cover->filename, PATHINFO_EXTENSION);
		$newFilename = uniqid() . $ext;
		file_put_contents($dir . $newFilename, base64_decode($cover->value));

		// Revisar si el archivo es en realidad una imagen
		if (!$imagedata = @getimagesize($dir . $newFilename)) {
			throw new RuntimeException("¡No es una imagen!");
		}

		$this->load->library('image_lib');

		//Resize original
		if ($imagedata["1"] > 1200) {
			$config = array(
				'source_image' => $dir . $newFilename,
				'new_image' => $dir . $newFilename,
				'maintain_ration' => true,
				'overwrite' => true,
				'quality' => 100,
				'width' => 884,
				'height' => 1200
			);

			$this->image_lib->initialize($config);

			if (!$this->image_lib->resize()) {
				return NULL;
			}

			$this->image_lib->clear();
		}

		return $newFilename;
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

	/*
	* Retorna el tipo de cover según el tipo con todos sus nombres
	*
	* @autor dvaJi
	*/
	private function getFullTypeCovers($type) {
		if ($type == 1) {
			return "original";

		} else if ($type == 2) {
			return "large";

		} else if ($type == 3) {
			return "medium";

		} else if ($type == 4) {
			return "thumb";
		}
	}

}
