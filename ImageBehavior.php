<?php
/** 
 * OrderableBehavior 
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
     
		function setup(&$Model, $settings = array()) { 
			if (!isset($settings)) { 
				$settings = array(
					'path' => IMAGES,
					'field' => false,
				); 
			} 
			$this->settings = array_merge($this->_defaults, $settings);
		}
		
		public function saveAsFileType(Model $model, $type, $name, $subpath = false) {
		
		}
		
		
		
		
		////////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
        // Define virtual fields at runtime
            public function __construct($id=false,$table=null,$ds=null){
                parent::__construct($id,$table,$ds);
                $this->virtualFields = array(
                    'path' => "CONCAT('db/', {$this->alias}.uuid, '.', {$this->alias}.ext)"
                    );
                $categories = $this->ImageCategory->find('list', array('fields' => array('ImageCategory.id')));
                $this->order = array('FIELD('.$this->alias.'.image_category_id, '.String::toList($categories, ', ').')');
            }

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
        

        public function read($fields = null, $id = null) {
            if(parent::read($fields, $id)) {
                $this->loadImage(APP.'webroot'.DS.'img'.DS.$this->data['Image']['path']);
                return parent::read($fields, $id);
            }
            return false;
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

        public function loadImage($fileString) {
                if(!file_exists($fileString)) {
                    throw new NotFoundException('File: "'.$fileString.'" not found');
                    return false;  
                }
                //if(empty($this->ext))
                $this->ext = strtolower(pathinfo($fileString, PATHINFO_EXTENSION));
                // create useing the correct function
                switch($this->ext) {
                    case 'jpg':
                        $this->imageResource = imagecreatefromjpeg($fileString);
                        break;
                    case 'jpeg':
                        $this->imageResource = imagecreatefromjpeg($fileString);
                        break;
                    case 'png':
                        $this->imageResource = imagecreatefrompng($fileString);
                        break;
                    case 'gif';
                        $this->imageResource = imagecreatefromgif($fileString);
                        break;
                    default:
                        throw new NotFoundException('File "'.$fileString.'" not found');
                        return false; // just in case
                        break; // just in case
                }
                imagealphablending($this->imageResource, true);
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
            
            /*
            * Crop-to-fit PHP-GD
            * http://911-need-code-help.blogspot.com/2009/04/crop-to-fit-image-using-aspphp.html
            *
            * Resize and center crop an arbitrary size image to fixed width and height
            * e.g. convert a large portrait/landscape image to a small square thumbnail
            */
            $origWidth = $this->getWidth();
            $origHeight = $this->getHeight();

            /*
            * Add file validation code here
            */

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
        
        public function crop($startX, $startY, $newWidth, $newHeight) {
            $image_p = imagecreatetruecolor($newWidth, $newHeight);
            $transparent = imagecolorallocatealpha($image_p, 255, 255, 255, 127);
            imagealphablending($image_p, false);
            imagesavealpha($image_p,true);
            imagefilledrectangle($image_p, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($image_p, $this->imageResource, 0, 0, $startX, $startY, $newWidth, $newHeight, $newWidth, $newHeight);
            $this->imageResource = $image_p;
            return true;
	}
	
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
	
	public function writeToDisk($location = null, $quality = 100) {
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
                        throw new NotFoundException('File "'.$fileString.'" not found');
                        return false; // just in case
                        break; // just in case
            }
            
            return TRUE;
	}
	
	private function turnAlphaBlendingOFF() { imagealphablending($im, false); }
	
        
        private function getImageResourceSize() {
            $dim = array();
            $dim[] = $this->getWidth($this->imageResource);
            $dim[] = $this->getHeight($this->imageResource);
            return $dim;
        }
        
        public function getWidth() {
		return imagesx($this->imageResource);
	}
	
	public function getHeight() {
		return imagesy($this->imageResource);
	}
        
/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'original_image_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'uuid' => array(
			'uuid' => array(
				'rule' => array('uuid'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'ext' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'class' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'foreign_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'title' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'image_category_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'image_type_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'width' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'height' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * hasOne associations
 *
 * @var array
 */
        
	public $hasOne = array(
		'OriginalImage' => array(
			'className' => 'Image',
			'foreignKey' => 'id',
                        'conditions' => array('OriginalImage.parent_id' => 'OriginalImage.id', 'OriginalImage.id' => NULL),
		),
                'PrimaryImage' => array(
			'className' => 'Image',
			'foreignKey' => 'id',
                        'conditions' => array('PrimaryImage.primary' => 1),
		)
	);
        

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'ImageCategory' => array(
			'className' => 'ImageCategory',
			'foreignKey' => 'image_category_id'
		),
		'ImageType' => array(
			'className' => 'ImageType',
			'foreignKey' => 'image_type_id'
		)
	);
}
