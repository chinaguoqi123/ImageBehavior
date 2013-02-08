<?php
/** 
 * ImageBehavior 
 * 
 * @developer Andrew Lechowicz
 * @license MIT 
 * @version 0.1 
 * @modified  3 January 2012
 * 
 */
class ImageBehavior extends ModelBehavior {

	public $name = 'Image';
        
        public $mapMethods = array('/saveAs(\w+)/' => 'saveAsFileType');
        
        public $imageResource = false;
     
        /*
         * @todo Save to databsse as opposed to file system.
         */
        public function setup(Model $Model, $settings = array()) {
            if (!isset($this->settings[$Model->alias])) {
                $this->settings[$Model->alias] = array(
                        'full' => IMAGES,// Set to false to use db for storage
                        'name' => 'uuid',
                        'extention' => 'ext',
                        'width' => 'width',
                        'height' => 'height',
                        'binary' => false,
                );
            }
            $this->settings[$Model->alias] = array_merge(
                $this->settings[$Model->alias], (array)$settings);
            
                $Model->virtualFields = array(
                'path' => "CONCAT('db/', {$Model->alias}.{$this->settings[$Model->alias]['name_field']}, '.', {$Model->alias}.{$this->settings[$Model->alias]['extention_field']})"
                );
           
        }

        public function saveAsFileType(Model &$Model, $type, $name, $subpath = false) {

        }
		
	
        public function afterFind(Model &$Model, mixed $results, boolean $primary) {
            if($primary) {
                $this->_loadImage(&$Model, $results[$Model->alias][$this->settings[$Model->alias][]]);
            }
        }
        
        public function beforeSave($options = array()) {
            if($this->imageResource === false) $this->_loadImage(&$Model, $results[$Model->alias][$this->settings[$Model->alias][]]);
            $this->data[$this->settings['width']] = $this->getWidth();
            $this->data[$this->settings['height']] = $this->getHeight();
            return true;
        }
        
        public function afterSave(Model $Model, boolean $created) {
            
        }
		
		
	///////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

        public function save($data = null, $validate = true, $fieldList = array()) {
            if($this->imageResource === false) {
                if(!$this->loadImage(APP.'webroot'.DS.$this->path)) {
                    if(!$this->loadImage(APP.'webroot'.DS.'db'.DS.$this->uuid.'.'.$this->ext)) {
                        if(!$this->loadImage(APP.'webroot'.DS.'db'.DS.$data['Image']['uuid'].'.'.$data['Image']['ext'])) {
                            // Image doesn't exist so we create a new one.
                            //die('failed to load GD Library image resource');
                            //$this->saveNew($data, $validate, fieldList);
                            //$this->uses[] = 'OrigionalImage';
                            $this->loadImage(APP.'webroot'.DS.'img'.DS.'db'.DS.$this->data['Image']['uuid'].'.'.$this->data['Image']['ext']);
                        }
                    }
                }
            }
            
            $data['Image']['width'] = $this->getWidth();
            $data['Image']['height'] = $this->getHeight();
            
            $this->writeToDisk(APP.'webroot'.DS.'img'.DS.'db'.DS.$this->uuid.'.jpg');
            //pr($data);exit;
            return parent::save($data, $validate, $fieldList);
        }
        
        public function saveNew($data = null, $validate = true, $fieldList = array()) {
                $parentId = $data['Image']['parent_id'];
                $this->recursive = -1;
                $parentData = $this->readDataOnly(null, $parentId);
                unset($parentData[$parentData['Image']['class']]);
                if(!empty($parentData)) {
                    
                    $data = array();
                    
                    $data['Image']['parent_id'] = $parentId;
                    
                    $data['Image']['class'] = $parentData['Image']['class'];
                    $data['Image']['foreign_id'] = $parentData['Image']['foreign_id'];
                    $data['Image']['title'] = $parentData['Image']['title'];
                    $data['Image']['alt'] = $parentData['Image']['alt'];
                    $data['Image']['image_category_id'] = $parentData['Image']['image_category_id'];
                    $data['Image']['image_type_id'] = $parentData['Image']['image_type_id'];
                    
                    $data['Image']['ext'] = 'jpg';
                    
                    $data['Image']['id'] = null;
            
                    $data['Image']['uuid'] = $this->uuid = String::uuid();
                    return $this->save($data, $validate, $fieldList);
                }
                
            return false;
        }
        
        public function saveToDB($data = null, $validate = true, $fieldList = array()) {
            if($this->imageResource !== false) {
                $data['Image']['width'] = $this->getWidth();
                $data['Image']['height'] = $this->getHeight();
            }
            
            return parent::save($data, $validate, $fieldList);
        }
        

        
        
        public function readDataOnly($fields = null, $id = null) {
                return parent::read($fields, $id);
        }

        public function create($data = array(), $filterKey = false) {
                if(parent::create($data, $filterKey)) {
                    $this->uuid = $this->data['Image']['uuid'] = String::uuid();
                    return true;
                } else {
                    return false;
                }
        }
        
        public function delete() {
            unlink(APP.'webroot'.DS.'img'.DS.'db'.DS.$this->data['Image']['uuid'].'.'.$this->data['Image']['ext']);
            return parent::delete();
        }

        private function _loadImage(Model $Model, $fileString) {
                if(!file_exists($fileString)) {
                    throw new NotFoundException('File: "'.$fileString.'" not found');
                    return false;  
                }
                //if(empty($this->ext))
                $this->ext = strtolower(pathinfo($fileString, PATHINFO_EXTENSION));
                // create useing the correct function
                switch($this->ext) {
                    case 'jpg':
                        $Model->imageResource = imagecreatefromjpeg($fileString);
                        break;
                    case 'jpeg':
                        $Model->imageResource = imagecreatefromjpeg($fileString);
                        break;
                    case 'png':
                        $Model->imageResource = imagecreatefrompng($fileString);
                        break;
                    case 'gif';
                        $Model->imageResource = imagecreatefromgif($fileString);
                        break;
                    default:
                        throw new NotFoundException('File "'.$fileString.'" not found');
                        return false; // just in case
                        break; // just in case
                }
                imagealphablending($Model->imageResource, true);
                return true;
        }
        
        public function handelUpload($data = array()) {
            $this->file = $data['Image']['file'];
            
            if ($this->file['error'] == false) {
                
                if(
                        $this->file['type'] == 'image/jpeg' ||
                        $this->file['type'] == 'image/jpg' ||
                        $this->file['type'] == 'image/png' ||
                        $this->file['type'] == 'image/gif'
                   ) {
                    
                    $this->uuid = String::uuid();
                    $this->set('uuid', $this->uuid);
                    $this->ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
                    $this->set('ext', $this->ext);
                    
                    $fileLocation = APP.'webroot'.DS.'img'.DS.'db'.DS.$this->uuid.'.'.$this->ext;
                    if (move_uploaded_file($this->file['tmp_name'], $fileLocation)) {
                        $this->loadImage($fileLocation);
                        $this->set('width', $this->getWidth());
                        $this->set('height', $this->getHeight());
                        return true;
                    }
                    return false;
                }
            }
        }
        
        public function handelReUpload($data = array()) {
            $this->file = $data['Image']['file'];
            
            if ($this->file['error'] == false) {
                
                if(
                        $this->file['type'] == 'image/jpeg' ||
                        $this->file['type'] == 'image/jpg'  ||
                        $this->file['type'] == 'image/png'  ||
                        $this->file['type'] == 'image/gif'
                   ) {
                    
                    $this->uuid = $data['Image']['uuid'];
                    $this->set('uuid', $this->uuid);
                    $this->ext = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
                    $this->set('ext', $this->ext);
                    
                    $fileLocation = APP.'webroot'.DS.'img'.DS.'db'.DS.$this->uuid.'.'.$this->ext;
                    if (move_uploaded_file($this->file['tmp_name'], $fileLocation)) {
                        $this->loadImage($fileLocation);
                        $this->set('width', $this->getWidth());
                        $this->set('height', $this->getHeight());
                        return true;
                    }
                    return false;
                }
            }
        }
	
	private function overlay($image, $overlay, $startX, $startY, $rotateCW = 0, $sizeX = 0, $sizeY = 0) {
            $rotateCW = -1 * $rotateCW;

            if($sizeX != 0 && $sizeY != 0) {
                    $overlay = $this->resizePercent($overlay, $sizeX, $sizeY);
            }		
            imagealphablending($overlay, false); 
            imagesavealpha($overlay, false);
            $overlay = imagerotate($overlay, $rotateCW, -1);
            (int)$overlayMiddleX = imagesx($overlay)/2;
            (int)$overlayMiddleY = imagesy($overlay)/2;
            imagecopy($image, $overlay, (abs($startX-$overlayMiddleX)), (abs($startY-$overlayMiddleY)), 0, 0, imagesx($overlay), imagesy($overlay));
	}
	
	
	public function resizeFixed($newWidth, $newHeight) {
            //if(is_string($filelocation))$image = $this->loadImage($filelocation);
            //$this->stream();exit;
            // Get new dimensions
            $origWidth = $this->getWidth();
            $origHeight = $this->getHeight();

            $origRatio = $origWidth/$origHeight;

            if ($newWidth/$newHeight > $origRatio) {
                $newWidth = $newHeight*$origRatio;
            } else {
                $newHeight = $newWidth/$origRatio;
            }
            // Resample
            $image_p = imagecreatetruecolor($newWidth, $newHeight);
            $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
            imagealphablending($image_p, false);
            imagesavealpha($image_p,true);
            imagefilledrectangle($image_p, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($image_p, $this->imageResource, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            $this->imageResource = $image_p;
            return true;
	}
        
        public function resizeCrop($newWidth, $newHeight) {
            $this->resizeAndCrop($newWidth, $newHeight)
        }
        
        /*
         * Resizes the current resource to fit the dimentions
         */
        public function resizeAndCrop($newWidth, $newHeight) {
            
            /*
            * Crop-to-fit PHP-GD
            * http://911-need-code-help.blogspot.com/2009/04/crop-to-fit-image-using-aspphp.html
            *
            * Resize and center crop an arbitrary size image to fixed width and height
            * e.g. convert a large portrait/landscape image to a small square thumbnail
            */
            $origWidth = $this->getWidth();
            $origHeight = $this->getHeight();

            $origRatio = $origWidth / $origHeight;
            $desired_aspect_ratio = $newWidth / $newHeight;

            if ($origRatio > $desired_aspect_ratio) {
               /*
                * Triggered when source image is wider
                */
               $temp_height = $newHeight;
               $temp_width = (int)($newHeight * $origRatio);
            } else {
               /*
                * Triggered otherwise (i.e. source image is similar or taller)
                */
               $temp_width = $newWidth;
               $temp_height = (int)($newWidth / $origRatio);
            }

            /*
            * Resize the image into a temporary GD image
            */

            $temp_gdim = imagecreatetruecolor($temp_width, $temp_height);
            imagecopyresampled($temp_gdim, $this->imageResource, 0, 0, 0, 0, $temp_width, $temp_height, $origWidth, $origHeight);

            /*
            * Copy cropped region from temporary image into the desired GD image
            */

            $x0 = ($temp_width - $newWidth) / 2;
            $y0 = ($temp_height - $newHeight) / 2;
            $desired_gdim = imagecreatetruecolor($newWidth, $newHeight);
            imagecopy($desired_gdim, $temp_gdim, 0, 0,$x0, $y0,$newWidth, $newHeight);
            $this->imageResource = $desired_gdim;
            return true;
	}
        
        /*
         * Crops the current resource to fit within the 
         */
        public function crop($newWidth, $newHeight) {
            $image_p = imagecreatetruecolor($newWidth, $newHeight);
            $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
            imagealphablending($image_p, false);
            imagesavealpha($image_p,true);
            imagefilledrectangle($image_p, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($image_p, $this->imageResource, 0, 0, 0, 0, $newWidth, $newHeight, $newWidth, $newHeight);
            $this->imageResource = $image_p;
            return true;
	}
	
        /*
         * Resizes the current image resource base on a percent
         */
	public function resizePercent($percent) {
            
            (float)$percent = $percent/100;

            // Get new dimensions
            list($width, $height) = $this->getimageResourcesize($image);
            $new_width = $width * $percent;
            $new_height = $height * $percent;

            // Resample
            $image_p = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($image_p, false);
            imagesavealpha($image_p,true);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $this->imageResource = $image_p;
            return true;
	}
	
	public function stream($quality = 100) {
            switch(strtolower($this->ext)) {
                    case 'jpg':
                        header("Content-Type: image/jpeg");
                        imagejpeg($this->imageResource, null, $quality);
                        break;
                    case 'jpeg':
                        header("Content-Type: image/jpeg");
                        imagejpeg($this->imageResource, null, $quality);
                        break;
                    case 'png':
                        header("Content-Type: image/png");
                        imagepng($this->imageResource, null, $quality);
                        break;
                    case 'gif';
                        header("Content-Type: image/gif");
                        imagegif($this->imageResource, null, $quality);
                        break;
                    default:
                        throw new NotFoundException('File "'.$fileString.'" not found');
                        return false; // just in case
                        break; // just in case
            }
            return true;
	}
	
	private function _writeToDisk($location = null, $quality = 100) {
            if(!isset($this->ext) || empty($this->ext)) $this->ext = 'jpg';
            if($location == null) $location = APP.'webroot'.DS.'img'.DS.'db'.DS.$this->uuid.'.'.$this->ext;
            
            switch(strtolower($this->ext)) {
                    case 'jpg':
                        imagejpeg($this->imageResource, $location, $quality);
                        break;
                    case 'jpeg':
                        imagejpeg($this->imageResource, $location, $quality);
                        break;
                    case 'png':
                        imagepng($this->imageResource, $location, (($quality-100)/10));
                        break;
                    case 'gif';
                        imagegif($this->imageResource, $location, $quality);
                        break;
                    default:
                        //throw new NotFoundException('File "'.$fileString.'" not found');
                        return false; // just in case
                        break; // just in case
            }
            
            return TRUE;
	}
	
	private function _turnAlphaBlendingOFF() { imagealphablending($im, false); }
	
        
        private function _getImageResourceSize() {
            return array($this->getWidth($this->imageResource), $this->getHeight($this->imageResource));
        }
        
        public function getWidth() {
		return imagesx($this->imageResource);
	}
	
	public function getHeight() {
		return imagesy($this->imageResource);
	}
}
