<?php
/**
 * @package     Image Uploader Plugin
 *
 * @copyright   Copyright (C) 2016 Rick Spaan. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Image Uploader Plugin
 *
 * By: Rick Spaan
 *
 * http://www.r2h.nl
 *
 */
class PlgContentImguploader extends JPlugin
{
	/**
	 * Plugin that manipulate uploaded images
	 *
	 * @param   string   $context       The context of the content being passed to the plugin.
	 * @param   object   &$object_file  The file object.
	 *
	 * @return  object  The file object.
	 */
	public function onContentBeforeSave($context,  &$object_file)
	{
		// Are we in the right context?
		if ($context != 'com_media.file')
		{
			return;
		}

		// Get the current date
		$date  = JDate::getInstance('now');

		// Respect the timezone
		$config = JFactory::getConfig();
		$date->setTimezone(new DateTimeZone($config->get('offset')));

		// Set current year, month, day
		$year  = $date->year;
		$month = $date->month;
		$day   = $date->day;

		// Set the images subfolder, defaults to images/uploads
		$folder = $this->params->get('folder','');
		if (!empty($folder))
		{
			$folder   = (isset($folder)) ? $folder . '/' : '';
			$basePath = JPATH_ROOT . '/images/' . $folder;
		}
		else
		{
			$basePath = JPATH_ROOT . '/images/';
		}

		if ($this->params->get('datefolders') == true )
		{
			// Make some directories checks
			if (!is_dir(rtrim($basePath, "/")))
			{
				mkdir(rtrim($basePath, "/"));
			}

			if (!is_dir($basePath . $year))
			{
				mkdir($basePath . $year);
			}

			if (!is_dir($basePath . $year . '/' . $month))
			{
				mkdir($basePath . $year . '/' . $month);
			}

			if (!is_dir($basePath . $year . '/' . $month . '/' . $day))
			{
				mkdir($basePath . $year . '/' . $month . '/' . $day);
			}

			// Update the object to the new path
			$object_file->filepath = $basePath . $year . '/' . $month . '/' . $day . '/' . $object_file->name;
		}
		else
		{
			// Always Update the object to the new path
			$object_file->filepath = $basePath . $object_file->name;
		}


		return $object_file;
	}

	/**
	 * Plugin that manipulate uploaded images
	 *
	 * @param   string   $context       The context of the content being passed to the plugin.
	 * @param   object   &$object_file  The file object.
	 *
	 * @return  object  The file object.
	 */
	public function onContentAfterSave($context,  &$object_file)
	{
		// Are we in the right context?
		if ($context != 'com_media.file')
		{
			return;
		}

		$file = pathinfo($object_file->filepath);

		// Skip if the pass through keyword is set
		if (!preg_match('/'. $this->params->get('passthrough') . '_/', $file['filename']))
		{

			// Check if file exist
			/*
			if (file_exists($object_file->filepath)) {
				// Alternatively you may use chaining
				JFactory::getApplication()->enqueueMessage(JText::_('SOME_ERROR_OCCURRED'), 'error');
				return;
			}
			*/

			$image = new JImage;

			// Load the file
			$image->loadFile($object_file->filepath);

			// Get the properties
			$properties = $image->getImageFileProperties($object_file->filepath);

			// Skip if the width is less or equal to the required
			if ($properties->width <= $this->params->get('maxwidth'))
			{
				return;
			}

			// Get the image type
			if (preg_match('/jp(e)g/', mb_strtolower($properties->mime)))
			{
				$imageType = 'IMAGETYPE_JPEG';
			}

			if (preg_match('/gif/', mb_strtolower($properties->mime)))
			{
				$imageType = 'IMAGETYPE_GIF';
			}

			if (preg_match('/png/', mb_strtolower($properties->mime)))
			{
				$imageType = 'IMAGETYPE_PNG';
			}

			// Resize the image
			$image->resize($this->params->get('maxwidth'), '', false);

			// Overwrite the file
			$image->toFile($object_file->filepath, $imageType, array('quality' => $this->params->get('quality')));

			if ($this->params->get('generatethumbs',0) == true )
			{
				// Get the thumbnails
				$thumb1 = str_replace(' ', '', $this->params->get('thumb1','160x120'));
				$thumb2 = str_replace(' ', '', $this->params->get('thumb2',''));
				$thumb3 = str_replace(' ', '', $this->params->get('thumb3',''));
				$thumb4 = str_replace(' ', '', $this->params->get('thumb4',''));

				// Create an array
				$thumbsarray = array();

				// Fill the array
				if (!empty($thumb1)) {$thumbsarray[] = $thumb1;};
				if (!empty($thumb2)) {$thumbsarray[] = $thumb2;};
				if (!empty($thumb3)) {$thumbsarray[] = $thumb3;};
				if (!empty($thumb4)) {$thumbsarray[] = $thumb4;};

				// Get the crop value
				$crop_method = $this->params->get('generatemethod','CROP_RESIZE');

				// Define the crop value
				switch ($crop_method) {
					case "CROP_RESIZE" :
						$crop_method=JImage::CROP_RESIZE;
	                    break;
					case "SCALE_FILL" :
						$crop_method=JImage::SCALE_FILL;
	                    break;
					case "SCALE_INSIDE" :
						$crop_method=JImage::SCALE_INSIDE;
	                    break;
					case "SCALE_OUTSIDE" :
						$crop_method=JImage::SCALE_OUTSIDE;
						break;
					case "SCALE_FIT" :
						$crop_method=JImage::SCALE_FIT;
						break;
					case "CROP" :
						$crop_method=JImage::CROP;
						break;	
					default:
						$crop_method=JImage::CROP_RESIZE;
				}

				// Create thumbs folder or not
				if ($this->params->get('thumbsfolders',0) == false )
				{
					// Set the root folder
					$folder = $this->params->get('folder', '');
					if (!empty($folder))
					{
						$folder   = (isset($folder)) ? $folder . '/' : '';
						$basePath = JPATH_ROOT . '/images/' . $folder;
					}
					else
					{
						$basePath = JPATH_ROOT . '/images/';
					}

					// Create Thumbs
					$image->createThumbs($thumbsarray, $crop_method, $basePath);
				}
				else
				{
					// Create Thumbs
					$image->createThumbs($thumbsarray, $crop_method, null);
				}

			}

		}

		return $object_file;
	}
}
