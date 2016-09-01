<?php

/**
 * Processwire Image Checker
 * For checking image filesizes
 * 
 * 
 * ProcessWire
 * Copyright (C) 2014 by Ryan Cramer 
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://processwire.com
 *
 */

class ProcessImageChecker extends Process implements Module, ConfigurableModule {

	/**
	 *
	 * @return array
	 *
	 */
	public static function getModuleInfo() {

		return array(
			'title' => 'Image Checker',
			'summary' => 'Check image sizes',
			'version' => '0.0.3',
			'author'    => 'Rupert Dunk',
			'href' => 'https://github.com/rdunk',
			'icon' => 'picture-o', 
			);
	}


	/**
	 * Defaults
	 */

	static public function getDefaults() {
		return array(
			"sizes" => ""
		);
	}


	/**
	 * Initialize the module
	 */

	public function init() {
		parent::init();
	}


	/**
	 * Install
	 * Installs page for UI.
	 */

	public function ___install() {
		$page = $this->getInstalledPage();
	}


	/**
	 * Uninstall
	 * Checks for and removes the installed page.
	 */

	public function ___uninstall() {
		$page = $this->getInstalledPage();
		if($page->id) {
			$this->pages->delete($page);
			$this->message("Removed {$page->path}");
		}
	}


	/**
	 * Return array of file size options
	 */

	private function getSizes() {
		$kilobyte = 1024;
		$megabyte = 1048576;

		$sizeOptions = preg_split("/\r\n|\n|\r/", trim($this->sizes));

		$sizes = [];
		foreach ($sizeOptions as $size) {
			preg_match("/(\d+) ?([A-Z][A-Z])/", $size, $matches);
			if (count($matches)) {
				if ($matches[2] === "MB") {
					$bytes = $matches[1]*$megabyte;
					$sizes[$bytes] = $size;
				} elseif ($matches[2] === "KB") {
					$bytes = $matches[1]*$kilobyte;
					$sizes[$bytes] = $size;
				}
			}
		}
		// add default if none found
		if (count($sizes) === 0) $sizes[$megabyte] = "1 MB";

		return $sizes;
	}


	/**
	 * Render the form
	 */

	public function renderForm($fields, $filesizes) {
		$modules = wire('modules');

		$form = $modules->get("InputfieldForm");
		$form->action = "./";
		$form->method = "post";
		$form->attr("id+name",'image-form');

		$f = $modules->get('InputfieldSelect');
		$f->columnWidth = 50;
		$f->label = "Image Field";
		$f->attr("id+name",'fieldID');
		$all = "";
		foreach($fields as $field) {
			$all .= $field->id.",";
		}
		$all = rtrim($all, ",");
		$f->addOption($all, "All Fields");
		foreach ($fields as $field) {
			$f->addOption($field->id, $field->name);
		}
		$f->attr("value", array_key_exists('fieldID', $_POST) ? $_POST['fieldID'] : $all);
		$form->append($f);

		$f = $modules->get('InputfieldSelect');
		$f->columnWidth = 50;
		$f->label = "Maximum Filesize";
		$f->attr("id+name",'filesize');
		foreach($filesizes as $i => $opt) {
			$f->addOption($i, $opt);
		}
		reset($filesizes);
		$f->attr("value", array_key_exists('filesize', $_POST) ? $_POST['filesize'] : key($filesizes));
		$form->append($f);

		$submit = $modules->get("InputfieldSubmit");
		$submit->attr("value","Check Images");
		$submit->attr("id+name","submit");
		$form->append($submit);

		return $form->render();
	}

	/**
	 * Render the table
	 */

	public function renderTable($fieldname, $filesize, $images) {
		if (count($images)) {
			$adminURL = $this->config->urls->admin;

			$out .= "<h3>"
				.count($images)
				." images found above "
				.$this->getSizes()[$filesize]
				." using <i>{$fieldname}</i>."
				."</h3>";

			$table = $this->modules->get('MarkupAdminDataTable');
			$table->setEncodeEntities(false); 
			$table->headerRow(array(
				$this->_x('Page', 'list-table'), 
				$this->_x('Filename', 'list-table'), 
				$this->_x('Filesize', 'list-table')
			));

			foreach ($images as $i) {
				$pg = $i->page;
				$pagelink = "<a target='_blank' href='".$adminURL."page/edit/?id={$pg->id}'>".$pg->title." (".$pg->id.")</a>";
				$table->row(array(
					$pagelink,
					$i,
					$i->filesizeStr
				)); 
			}

			$out .= $table->render();
		} else {
			$out .= "<h3>"
				."No images found above "
				.$this->getSizes()[$filesize]
				." using <i>{$fieldname}</i>."
				."</h3>";
		}
		return $out;
	}


	/**
	 * Initialize the module
	 */

	public function ___execute() {

		$out = "";
		$fieldIDs = array_key_exists('fieldID', $_POST) ? $_POST['fieldID'] : false;
		$filesize = array_key_exists('fieldID', $_POST) ? $_POST['filesize'] : false;

		if ($fieldIDs && $filesize) {

			$images = new WireArray();
			$fieldIDarray = explode(",",$fieldIDs);

			foreach($fieldIDarray as $fieldID) {
				$field = wire('fields')->get($fieldID);
				$fieldname = $field->name;
				$selector = $fieldname.">0";
				$pagesWithImages = wire('pages')->find($selector);

				foreach($pagesWithImages as $p) {
					$imagedata = $p->getUnformatted($fieldname);
					foreach($imagedata as $image) {
						if ($image->filesize >= $filesize) {
							$images->add($image);
						}
					}
				}
			}
			if (count($fieldIDarray) > 1) {
				$fieldname = "all fields";
			}
			$out .= $this->renderTable($fieldname, $filesize, $images->sort("-filesize"));
		}
		
		$out .= $this->renderForm( wire('fields')->find("type=FieldtypeImage"), $this->getSizes());

		return $out;
	}


	/**
	 * Helper function
	 */

	protected function decodeIfString($string) {
		return is_string($string) ? html_entity_decode($string) : $string;
	}


	/**
	 * Checks for and returns for admin page required for functionality, creates if missing
	 */

	protected function getInstalledPage() {
		$setupPage = $this->pages->get($this->config->adminRootPageID)->child('name=setup');
		$page = $setupPage->child("name=image-checker");

		if(!$page->id) {
			$page = $this->createPage(array(
				'parent' => $setupPage,
				'template' => $this->templates->get('admin'),
				'name' => "image-checker",
				'title' => "Image Checker",
				'process' => $this
				)
			);
		}
		return $page;
	}


	/**
	 * Creates admin page required for functionality
	 */

	protected function createPage($array) {
		$page = new Page();
		foreach ($array as $key => $val) {
			$page->$key = $this->decodeIfString($val);
		}
		$page->save();
		return $page;
	}


	/**
	 * Module configurable inputs
	 */

	static public function getModuleConfigInputfields(array $data) {
		$inputfields = new InputfieldWrapper();
		$modules = wire('modules');
		$data = array_merge(self::getDefaults(), $data);

		$f = $modules->get('InputfieldTextarea');
		$f->name = 'sizes';
		$f->label = 'File Sizes';
		$f->value = $data['sizes'];
		$f->description = "Enter one filesize only per line, you can add as many as required. The module supports filesizes in kilobyte (KB) or megabyte (MB) format. E.g. 256KB, 512 KB, 1MB, 5 MB";
		$f->columnWidth = 100;
		$f->required = 1;
		$inputfields->add($f);

		return $inputfields;
	}
	
}
