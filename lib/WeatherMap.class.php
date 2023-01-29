<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2023 The Cacti Group, Inc.                           |
 |                                                                         |
 | Based on the Original Plugin developed by Howard Jones                  |
 |                                                                         |
 | Copyright (C) 2005-2022 Howard Jones and contributors                   |
 |                                                                         |
 | Permission is hereby granted, free of charge, to any person obtaining   |
 | a copy of this software and associated documentation files              |
 | (the "Software"), to deal in the Software without restriction,          |
 | including without limitation the rights to use, copy, modify, merge,    |
 | publish, distribute, sublicense, and/or sell copies of the Software,    |
 | and to permit persons to whom the Software is furnished to do so,       |
 | subject to the following conditions:                                    |
 |                                                                         |
 | The above copyright notice and this permission notice shall be          |
 | included in all copies or substantial portions of the Software.         |
 |                                                                         |
 | THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,         |
 | EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES         |
 | OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND                |
 | NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS     |
 | BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN      |
 | ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN       |
 | CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE        |
 | SOFTWARE.                                                               |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | Extensions to Howard Jones' original work are designed, written, and    |
 | maintained by the Cacti Group.                                          |
 |                                                                         |
 | Howard Jones was the original author of Weathermap.  You can reach      |
 | him at: howie@thingy.com                                                |
 +-------------------------------------------------------------------------+
 | http://www.network-weathermap.com/                                      |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once('HTML_ImageMap.class.php');

require_once('WeatherMap.functions.php');
require_once('geometry.php');
require_once('WeatherMapNode.class.php');
require_once('WeatherMapLink.class.php');

$weathermap_debugging  = false;
$weathermap_map        = '';
$weathermap_warncount  = 0;
$weathemap_lazycounter = 0;

// Dummy array for some future code
$WM_config_keywords2 = array ();

// don't produce debug output for these functions
$weathermap_debug_suppress = array (
    'processstring',
    'mysprintf'
);

// don't output warnings/errors for these codes (WMxxx)
$weathermap_error_suppress = array();

// Turn on ALL error reporting for now.
// error_reporting (E_ALL|E_STRICT);
error_reporting (E_ALL);

// parameterise the in/out stuff a bit
define('IN',0);
define('OUT',1);
define('WMCHANNELS',2);

define('CONFIG_TYPE_LITERAL',0);
define('CONFIG_TYPE_COLOR',1);

// some strings that are used in more than one place
define('FMT_BITS_IN',    '{link:this:bandwidth_in:%2k}');
define('FMT_BITS_OUT',   '{link:this:bandwidth_out:%2k}');
define('FMT_UNFORM_IN',  '{link:this:bandwidth_in}');
define('FMT_UNFORM_OUT', '{link:this:bandwidth_out}');
define('FMT_PERC_IN',    '{link:this:inpercent:%.2f}%');
define('FMT_PERC_OUT',   '{link:this:outpercent:%.2f}%');

// the fields within a spine triple
define('X', 0);
define('Y', 1);

define('DISTANCE', 2);

// ***********************************************

// template class for data sources. All data sources extend this class.
// I really wish PHP4 would just die overnight
class WeatherMapDataSource {
	// Cacti Integration
	var $local_data_id;

	// Initialize - called after config has been read (so SETs are processed)
	// but just before ReadData. Used to allow plugins to verify their dependencies
	// (if any) and bow out gracefully. Return false to signal that the plugin is not
	// in a fit state to run at the moment.
	function Init(&$map) {
		return true;
	}

	// called with the TARGET string. Returns true or false, depending on whether it wants to handle this TARGET
	// called by map->ReadData()
	function Recognise($targetstring) {
		return false;
	}

	// the actual ReadData
	//   returns an array of two values (in,out). -1,-1 if it couldn't get valid data
	//   configline is passed in, to allow for better error messages
	//   itemtype and itemname may be used as part of the target (e.g. for TSV source line)
	// function ReadData($targetstring, $configline, $itemtype, $itemname, $map) { return (array(-1,-1)); }
	function ReadData($targetstring, &$map, &$item) {
		return(array(-1,-1));
	}

	// pre-register a target + context, to allow a plugin to batch up queries to a slow database, or snmp for example
	function Register($targetstring, &$map, &$item) {

	}

	// called before ReadData, to allow plugins to DO the prefetch of targets known from Register
	function Prefetch() {

	}
}

// template classes for the pre- and post-processor plugins
class WeatherMapPreProcessor {
	function run(&$map) {
		return false;
	}
}

class WeatherMapPostProcessor {
	function run(&$map) {
		return false;
	}
}

// ***********************************************

// Links, Nodes and the Map object inherit from this class ultimately.
// Just to make some common code common.
class WeatherMapBase {
	var $notes = array();
	var $hints = array();
	var $inherit_fieldlist;

	function add_note($name,$value) {
		wm_debug("Adding note $name='$value' to ".$this->name);

		$this->notes[$name] = $value;
	}

	function get_note($name) {
		if (isset($this->notes[$name])) {
			//	debug("Found note $name in ".$this->name." with value of ".$this->notes[$name].".\n");
			return($this->notes[$name]);
		} else {
			//	debug("Looked for note $name in ".$this->name." which doesn't exist.\n");
			return(null);
		}
	}

	function add_hint($name,$value) {
		wm_debug("Adding hint $name='$value' to ".$this->name);

		$this->hints[$name] = $value;
		# warn("Adding hint $name to ".$this->my_type()."/".$this->name."\n");
	}

	function get_hint($name) {
		if (isset($this->hints[$name])) {
			//	debug("Found hint $name in ".$this->name." with value of ".$this->hints[$name].".\n");
			return($this->hints[$name]);
		} else {
			//	debug("Looked for hint $name in ".$this->name." which doesn't exist.\n");
			return(null);
		}
	}
}

class WeatherMapConfigItem {
	var $defined_in;
	var $name;
	var $value;
	var $type;
}

// The 'things on the map' class. More common code (mainly variables, actually)
class WeatherMapItem extends WeatherMapBase {
	var $owner;

	var $configline;
	var $infourl;
	var $overliburl;
	var $overlibwidth, $overlibheight;
	var $overlibcaption;
	var $my_default;
	var $defined_in;
	var $config_override;	# used by the editor to allow text-editing

	function my_type() {
		return "ITEM";
	}
}

class WeatherMap extends WeatherMapBase {
	var $nodes = array(); // an array of WeatherMapNodes
	var $links = array(); // an array of WeatherMapLinks
	var $texts = array(); // an array containing all the extraneous text bits

	var $used_images  = array(); // an array of image filenames referred to (used by editor)
	var $seen_zlayers = array(0 => array(), 1000 => array()); // 0 is the background, 1000 is the legends, title, etc

	var $config;
	var $next_id;
	var $min_ds_time;
	var $max_ds_time;
	var $background;
	var $htmlstyle;
	var $imap;
	var $colours;
	var $configfile;
	var $imagefile;
	var $imageuri;
	var $rrdtool;
	var $title;
	var $titlefont;
	var $kilo;
	var $sizedebug;
	var $widthmod;
	var $debugging;
	var $linkfont;
	var $nodefont;
	var $keyfont;
	var $timefont;

	// var $bg_r, $bg_g, $bg_b;
	var $timex;
	var $timey;
	var $width;
	var $height;
	var $keyx;
	var $keyy;
	var $keyimage;
	var $titlex;
	var $titley;
	var $keytext;
	var $stamptext;
	var $datestamp;
	var $min_data_time;
	var $max_data_time;
	var $htmloutputfile;
	var $imageoutputfile;
	var $dataoutputfile;
	var $htmlstylesheet;
	var $defaultlink;
	var $defaultnode;
	var $need_size_precalc;
	var $keystyle;
	var $keysize;
	var $rrdtool_check;
	var $inherit_fieldlist;
	var $mintimex;
	var $maxtimex;
	var $mintimey;
	var $maxtimey;
	var $minstamptext;
	var $maxstamptext;
	var $context;
	var $cachefolder;
	var $mapcache;
	var $cachefile_version;
	var $name;
	var $black;
	var $white;
	var $grey;
	var $selected;

	var $datasourceclasses;
	var $preprocessclasses;
	var $postprocessclasses;
	var $activedatasourceclasses;
	var $thumb_width;
	var $thumb_height;
	var $has_includes;
	var $has_overlibs;
	var $node_template_tree;
	var $link_template_tree;
    var $dsinfocache = array();

	var $plugins = array();
	var $included_files = array();
	var $usage_stats = array();
	var $coverage = array();
    var $colourtable = array();
    var $warncount = 0;

	// PHP 8.1 QA
	var $numscales;
	var $dumpconfig;
	var $labelstyle;
	var $fonts;
	var $scales;
	var $image;

	function __construct() {
		$this->inherit_fieldlist = array (
			'width' => 800,
			'height' => 600,
			'kilo' => 1000,
			'numscales' => array('DEFAULT' => 0),
			'datasourceclasses' => array(),
			'preprocessclasses' => array(),
			'postprocessclasses' => array(),
			'included_files' => array(),
			'context' => '',
			'dumpconfig' => false,
			'rrdtool_check' => '',
			'background' => '',
			'imageoutputfile' => '',
			'imageuri' => '',
			'htmloutputfile' => '',
			'dataoutputfile' => '',
			'htmlstylesheet' => '',
			'labelstyle' => 'percent', // redundant?
			'htmlstyle' => 'static',
			'keystyle' => array('DEFAULT' => 'classic'),
			'title' => 'Network Weathermap',
			'keytext' => array('DEFAULT' => 'Traffic Load'),
			'keyx' => array('DEFAULT' => -1),
			'keyy' => array('DEFAULT' => -1),
			'keyimage' => array(),
			'keysize' => array('DEFAULT' => 400),
			'stamptext' => 'Created: %b %d %Y %H:%M:%S',
			'keyfont' => 4,
			'titlefont' => 2,
			'timefont' => 2,
			'timex' => 0,
			'timey' => 0,

			'mintimex' => -10000,
			'mintimey' => -10000,
			'maxtimex' => -10000,
			'maxtimey' => -10000,
			'minstamptext' => 'Oldest Data: %b %d %Y %H:%M:%S',
			'maxstamptext' => 'Newest Data: %b %d %Y %H:%M:%S',

			'thumb_width' => 0,
			'thumb_height' => 0,
			'titlex' => -1,
			'titley' => -1,
			'cachefolder' => 'cached',
			'mapcache' => '',
			'sizedebug' => false,
			'debugging' => false,
			'widthmod' => false,
			'has_includes' => false,
			'has_overlibs' => false,
			'name' => 'MAP'
		);

		$this->Reset();
	}

	function my_type() {
		return "MAP";
	}

	function Reset() {
		$this->next_id = 100;
		foreach (array_keys($this->inherit_fieldlist)as $fld) {
			$this->$fld=$this->inherit_fieldlist[$fld];
		}

		$this->min_ds_time = null;
		$this->max_ds_time = null;

		$this->need_size_precalc=false;

		$this->nodes=array(); // an array of WeatherMapNodes
		$this->links=array(); // an array of WeatherMapLinks

		// these are the default defaults
		// by putting them into a normal object, we can use the
		// same code for writing out LINK DEFAULT as any other link.
		wm_debug("Creating ':: DEFAULT ::' DEFAULT LINK");

		// these two are used for default settings
		$deflink = new WeatherMapLink;

		$deflink->name=":: DEFAULT ::";
		$deflink->template=":: DEFAULT ::";
		$deflink->Reset($this);

		$this->links[':: DEFAULT ::'] = &$deflink;

		wm_debug("Creating ':: DEFAULT ::' DEFAULT NODE");

		$defnode = new WeatherMapNode;

		$defnode->name=":: DEFAULT ::";
		$defnode->template=":: DEFAULT ::";
		$defnode->Reset($this);

		$this->nodes[':: DEFAULT ::'] = &$defnode;

       	$this->node_template_tree = array();
       	$this->link_template_tree = array();

		$this->node_template_tree['DEFAULT'] = array();
		$this->link_template_tree['DEFAULT'] = array();

		// ************************************
		// now create the DEFAULT link and node, based on those.
		// these can be modified by the user, but their template (and therefore comparison in WriteConfig) is ':: DEFAULT ::'
		wm_debug("Creating actual DEFAULT NODE from :: DEFAULT ::");

		$defnode2 = new WeatherMapNode;

		$defnode2->name = "DEFAULT";
		$defnode2->template = ":: DEFAULT ::";
		$defnode2->Reset($this);

		$this->nodes['DEFAULT'] = &$defnode2;

		wm_debug("Creating actual DEFAULT LINK from :: DEFAULT ::");

		$deflink2 = new WeatherMapLink;

		$deflink2->name = "DEFAULT";
		$deflink2->template = ":: DEFAULT ::";
		$deflink2->Reset($this);

		$this->links['DEFAULT'] = &$deflink2;

		// for now, make the old defaultlink and defaultnode work too.
		//                $this->defaultlink = $this->links['DEFAULT'];
		//                $this->defaultnode = $this->nodes['DEFAULT'];
		// assert('is_object($this->nodes[":: DEFAULT ::"])');
		// assert('is_object($this->links[":: DEFAULT ::"])');
		// assert('is_object($this->nodes["DEFAULT"])');
		// assert('is_object($this->links["DEFAULT"])');
		// ************************************

		$this->imap = new HTML_ImageMap('weathermap');
		$this->colours = array();

		wm_debug("Adding default map colour set.");

		$defaults = array (
			'KEYTEXT' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 0,
				'green1'  => 0,
				'blue1'   => 0,
				'special' => 1
			),
			'KEYOUTLINE' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 0,
				'green1'  => 0,
				'blue1'   => 0,
				'special' => 1
				),
			'KEYBG' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 255,
				'green1'  => 255,
				'blue1'   => 255,
				'special' => 1
				),
			'BG' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 255,
				'green1'  => 255,
				'blue1'   => 255,
				'special' => 1
				),
			'TITLE' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 0,
				'green1'  => 0,
				'blue1'   => 0,
				'special' => 1
				),
			'TIME' => array(
				'bottom'  => -2,
				'top'     => -1,
				'red1'    => 0,
				'green1'  => 0,
				'blue1'   => 0,
				'special' => 1
			)
		);

		foreach ($defaults as $key => $def) {
			$this->colours['DEFAULT'][$key] = $def;
		}

		$this->configfile = '';
		$this->imagefile  = '';
		$this->imageuri   = '';

		$this->fonts=array();

		// Adding these makes the editor's job a little easier, mainly
		for($i=1; $i<=5; $i++) {
			$this->fonts[$i] = new WMFont();
			$this->fonts[$i]->type="GD builtin";
			$this->fonts[$i]->file='';
			$this->fonts[$i]->size=0;
		}

		$this->LoadPlugins('data', 'lib/datasources');
		$this->LoadPlugins('pre',  'lib/pre');
		$this->LoadPlugins('post', 'lib/post');

		wm_debug("WeatherMap class Reset() complete");
	}

    /**
     * Create an array of all the nodes and links, mixed together.
     * readData() makes several passes through this list.
     *
     * @return MapDataItem[]
     */
    public function buildAllItemsList() {
        // TODO - this should probably be a static, or otherwise cached
        $allItems = array();

        foreach (array(&$this->nodes, &$this->links) as $innerList) {
            foreach ($innerList as $item) {
                $allItems[] = $item;
            }
        }

        return $allItems;
    }

	function myimagestring($image, $fontnumber, $x, $y, $string, $colour, $angle=0) {
		// if it's supposed to be a special font, and it hasn't been defined, then fall through
		if ($fontnumber > 5 && !isset($this->fonts[$fontnumber])) {
			wm_warn("Using a non-existent special font ($fontnumber) - falling back to internal GD fonts [WMWARN03]");

			if ($angle != 0) {
				wm_warn('Angled text doesn\'t work with non-FreeType fonts [WMWARN02]');
			}

			$fontnumber = 5;
		}

		$x = floor($x);

		if (($fontnumber > 0) && ($fontnumber < 6)) {
			imagestring($image, $fontnumber, $x, floor($y - imagefontheight($fontnumber)), $string, $colour);

			if ($angle != 0) {
				wm_warn("Angled text doesn't work with non-FreeType fonts [WMWARN02]");
			}
		} else {
			// look up what font is defined for this slot number
			if ($this->fonts[$fontnumber]->type == 'truetype') {
				wimagettftext($image, $this->fonts[$fontnumber]->size, $angle, $x, $y,
					$colour, $this->fonts[$fontnumber]->file, $string);
			}

			if ($this->fonts[$fontnumber]->type == 'gd') {
				imagestring($image, $this->fonts[$fontnumber]->gdnumber,
					$x, floor($y - imagefontheight($this->fonts[$fontnumber]->gdnumber)),
					$string, $colour
				);

				if ($angle != 0) {
					wm_warn('Angled text doesn\'t work with non-FreeType fonts [WMWARN04]');
				}
			}
		}
	}

	function myimagestringsize($fontnumber, $string) {
		$linecount = 1;

		$lines         = explode("\n",$string);
		$linecount     = sizeof($lines);
		$maxlinelength = 0;

		foreach($lines as $line) {
			$l = strlen($line);

			if ($l > $maxlinelength) {
				$maxlinelength = $l;
			}
		}

		if (($fontnumber > 0) && ($fontnumber < 6)) {
			return array(imagefontwidth($fontnumber) * $maxlinelength, $linecount * imagefontheight($fontnumber));
		} else {
			// look up what font is defined for this slot number
			if (!isset($this->fonts[$fontnumber])) {
				wm_warn("Using a non-existent special font ($fontnumber) - falling back to internal GD fonts [WMWARN36]");
				$fontnumber=5;
				return array(imagefontwidth($fontnumber) * $maxlinelength, $linecount * imagefontheight($fontnumber));
			} else {
				if ($this->fonts[$fontnumber]->type == 'truetype') {
					$ysize = 0;
					$xsize = 0;

					foreach($lines as $line) {
						$bounds=imagettfbbox($this->fonts[$fontnumber]->size, 0, $this->fonts[$fontnumber]->file, $line);
						$cx = $bounds[4] - $bounds[0];
						$cy = $bounds[1] - $bounds[5];
						if ($cx > $xsize) $xsize = $cx;
						$ysize += ($cy*1.2);
						# warn("Adding $cy (x was $cx)\n");
					}

					#$bounds=imagettfbbox($this->fonts[$fontnumber]->size, 0, $this->fonts[$fontnumber]->file,
					#	$string);
					# return (array($bounds[4] - $bounds[0], $bounds[1] - $bounds[5]));
					# warn("Size of $string is $xsize x $ysize over $linecount lines\n");

					return(array($xsize,$ysize));
				}

				if ($this->fonts[$fontnumber]->type == 'gd') {
					return array(imagefontwidth($this->fonts[$fontnumber]->gdnumber) * $maxlinelength,
						$linecount * imagefontheight($this->fonts[$fontnumber]->gdnumber)
					);
				}
			}
		}
	}

	function ProcessString($input, &$context, $include_notes = true, $multiline = false) {
		global $config;

		// Fix relative URL's
		if (strpos($input, 'graph_image.php') !== false ||
			strpos($input, 'graph.php') !== false ||
			strpos($input, 'graph_view.php') !== false) {
			if (strpos($input, $config['url_path']) === false) {
				$input = $config['url_path'] . $input;
			}

			if (strpos($input, 'graph_image.php') !== false) {
				if (strpos($input, 'graph_height') === false) {
					$input .= '&graph_height=' . read_config_option('weathermap_height');
				}

				if (strpos($input, 'graph_width') === false) {
					$input .= '&graph_width=' . read_config_option('weathermap_width');
				}

				if (strpos($input, 'graph_nolegend') === false) {
					if (read_config_option('weathermap_nolegend') == 'thumb') {
						$input .= '&graph_nolegend=true';
					}
				}

				$input .= '&random=' . rand(0, 65535);
			}
		}

		$context_description = strtolower($context->my_type());

		if ($context_description != 'map') {
			$context_description .= ':' . $context->name;
		}

		wm_debug("Trace: ProcessString($input, $context_description)");

		if ($multiline == true) {
			$i     = $input;
			$input = str_replace("\\n", "\n", $i);
		}

		$output = $input;

		while (preg_match('/(\{(?:node|map|link)[^}]+\})/', $input, $matches)) {
			$value  = '[UNKNOWN]';
			$format = '';
			$key    = $matches[1];

			wm_debug('ProcessString: working on ' . $key);

			if (preg_match('/\{(node|map|link):([^}]+)\}/', $key, $matches)) {
				$type = $matches[1];
				$args = $matches[2];

				if ($type == 'map') {
					$the_item = $this;

					if (preg_match('/map:([^:]+):*([^:]*)/', $args, $matches)) {
						$args   = $matches[1];
						$format = $matches[2];
					}
				}

				if (($type == 'link') || ($type == 'node')) {
					if (preg_match('/([^:]+):([^:]+):*([^:]*)/', $args, $matches)) {
						$itemname = $matches[1];
						$args     = $matches[2];
						$format   = $matches[3];
						$the_item = null;

						if (($itemname == 'this') && ($type == strtolower($context->my_type()))) {
							$the_item = $context;
						} elseif (strtolower($context->my_type()) == 'link' && $type == 'node' && ($itemname == '_linkstart_' || $itemname == '_linkend_')) {
							// this refers to the two nodes at either end of this link
							if ($itemname == '_linkstart_') {
								$the_item = $context->a;
							}

							if ($itemname == '_linkend_') {
								$the_item = $context->b;
							}
						} elseif (($itemname == 'parent') && ($type == strtolower($context->my_type())) && ($type=='node') && ($context->relative_to != '') ) {
							$the_item = $this->nodes[$context->relative_to];
						} else {
							if (($type == 'link') && isset($this->links[$itemname])) {
								$the_item = $this->links[$itemname];
							}

							if (($type == 'node') && isset($this->nodes[$itemname]) ) {
								$the_item = $this->nodes[$itemname];
							}
						}
					}
				}

				if (is_null($the_item)) {
					wm_warn("ProcessString: $key refers to unknown item (context is $context_description) [WMWARN05]");
				} else {
					wm_debug('ProcessString: Found appropriate item: ' . get_class($the_item) . ' ' . $the_item->name);

					// SET and notes have precedent over internal properties
					// this is my laziness - it saves me having a list of reserved words
					// which are currently used for internal props. You can just 'overwrite' any of them.
					if (isset($the_item->hints[$args])) {
						$value = $the_item->hints[$args];
						wm_debug('ProcessString: used hint');
					} elseif ($include_notes && isset($the_item->notes[$args])) {
						// for some things, we don't want to allow notes to be considered.
						// mainly - TARGET (which can define command-lines), shouldn't be
						// able to get data from uncontrolled sources (i.e. data sources rather than SET in config files).
						$value = $the_item->notes[$args];

						wm_debug('ProcessString: used note');
					} elseif (isset($the_item->$args)) {
						$value = $the_item->$args;
						wm_debug('ProcessString: used internal property');
					}
				}
			}

			if ($value === null) {
				$value = 'NULL';
			}

			wm_debug('ProcessString: replacing ' . $key . ' with ' . $value);

			if ($format != '') {
				$value = mysprintf($format, $value, $this->kilo);
			}

			$input  = str_replace($key, '', $input);
			$output = str_replace($key, $value, $output);
		}

		return ($output);
	}

	function RandomData() {
		foreach ($this->links as $link) {
			$this->links[$link->name]->bandwidth_in=rand(0, $link->max_bandwidth_in);
			$this->links[$link->name]->bandwidth_out=rand(0, $link->max_bandwidth_out);
		}
	}

	function LoadPlugins($type = 'data', $dir = 'lib/datasources') {
		wm_debug("Beginning to load $type plugins from $dir");

		if (!file_exists($dir)) {
			$dir = __DIR__ . '/' . $dir;

			wm_debug("Relative path didn't exist. Trying $dir");
		}

		# $this->datasourceclasses = array();
		$dh = @opendir($dir);

		if (!$dh) {
			// try to find it with the script, if the relative path fails
			$srcdir = substr($_SERVER['argv'][0], 0, strrpos($_SERVER['argv'][0], '/'));

			$dh = opendir($srcdir . '/' . $dir);
			if ($dh) {
				$dir = $srcdir . '/' . $dir;
			}
		}

		if ($dh) {
			while ($file = readdir($dh)) {
				$realfile = $dir . '/' . $file;

				if (is_file($realfile) && preg_match( '/\.php$/', $realfile)) {
					if (strpos($realfile, 'index.php') !== false) {
						continue;
					}

					wm_debug("Loading $type Plugin class from $file");

					include_once($realfile);

					$class = preg_replace("/\.php$/", "", $file);

					if ($type == 'data') {
						$this->datasourceclasses [$class]= $class;
						$this->activedatasourceclasses[$class]=1;
					}

					if ($type == 'pre') {
						$this->preprocessclasses [$class]= $class;
					}

					if ($type == 'post') {
						$this->postprocessclasses [$class]= $class;
					}

					wm_debug("Loaded $type Plugin class $class from $file");

					$this->plugins[$type][$class] = new $class;

					if (! isset($this->plugins[$type][$class])) {
						wm_debug("** Failed to create an object for plugin $type/$class");
					} else {
						wm_debug("Instantiated $class.");
					}
				} else {
					wm_debug("Skipping $file");
				}
			}
		} else {
			wm_warn("Couldn't open $type Plugin directory ($dir). Things will probably go wrong. [WMWARN06]");
		}
	}

	function DatasourceInit() {
		wm_debug("Running Init() for Data Source Plugins...");

		foreach ($this->datasourceclasses as $ds_class) {
			// make an instance of the class
			$dsplugins[$ds_class] = new $ds_class;

			wm_debug("Running $ds_class"."->Init()");

			# $ret = call_user_func(array($ds_class, 'Init'), $this);
			// assert('isset($this->plugins["data"][$ds_class])');

			$ret = $this->plugins['data'][$ds_class]->Init($this);

			if (! $ret) {
				wm_debug("Removing $ds_class from Data Source list, since Init() failed");

				$this->activedatasourceclasses[$ds_class]=0;
				# unset($this->datasourceclasses[$ds_class]);
			}
		}

		wm_debug("Finished Initialising Plugins...");
	}

	function ProcessTargets() {
		wm_debug("Preprocessing targets");

		$allitems = $this->buildAllItemsList();

		foreach ($allitems as $myobj) {
			$type = $myobj->my_type();
			$name = $myobj->name;

			if (($type=='LINK' && isset($myobj->a)) || ($type=='NODE' && !is_null($myobj->x))) {
				if (count($myobj->targets)>0) {
					$tindex = 0;

					foreach ($myobj->targets as $target) {
						wm_debug("ProcessTargets: New Target: $target[4]");
						// processstring won't use notes (only hints) for this string

						$targetstring = $this->ProcessString($target[4], $myobj, false, false);

						if ($target[4] != $targetstring) {
							wm_debug("Targetstring is now $targetstring");
						}

						// if the targetstring starts with a -, then we're taking this value OFF the aggregate
						$multiply = 1;
						if (preg_match("/^-(.*)/",$targetstring,$matches)) {
							$targetstring = $matches[1];
							$multiply = -1 * $multiply;
						}

						// if the remaining targetstring starts with a number and a *-, then this is a scale factor
						if (preg_match("/^(\d+\.?\d*)\*(.*)/",$targetstring,$matches)) {
							$targetstring = $matches[2];
							$multiply = $multiply * floatval($matches[1]);
						}

						$matched    = false;
						$matched_by = '';

						foreach ($this->datasourceclasses as $ds_class) {
							if (!$matched) {
								// $recognised = call_user_func(array($ds_class, 'Recognise'), $targetstring);
								$recognised = $this->plugins['data'][$ds_class]->Recognise($targetstring);

								if ($recognised) {
									$matched = true;
									$matched_by = $ds_class;

									if ($this->activedatasourceclasses[$ds_class]) {
										$this->plugins['data'][$ds_class]->Register($targetstring, $this, $myobj);

										if ($type == 'NODE') {
											$this->nodes[$name]->targets[$tindex][1] = $multiply;
											$this->nodes[$name]->targets[$tindex][0] = $targetstring;
											$this->nodes[$name]->targets[$tindex][5] = $matched_by;
										}

										if ($type == 'LINK') {
											$this->links[$name]->targets[$tindex][1] = $multiply;
											$this->links[$name]->targets[$tindex][0] = $targetstring;
											$this->links[$name]->targets[$tindex][5] = $matched_by;
										}
									} else {
										wm_warn("ProcessTargets: $type $name, target: $targetstring on config line $target[3] of $target[2] was recognised as a valid TARGET by a plugin that is unable to run ($ds_class) [WMWARN07]");
									}
								}
							}
						}

						if (! $matched) {
							wm_warn("ProcessTargets: $type $name, target: $target[4] on config line $target[3] of $target[2] was not recognised as a valid TARGET [WMWARN08]");
						}

						$tindex++;
					}
				}
			}
		}
	}

	function ReadData() {
		$this->DatasourceInit();

		wm_debug("======================================");
		wm_debug("ReadData: Updating link data for all links and nodes");

		// we skip readdata completely in sizedebug mode
		if ($this->sizedebug == 0) {
			$this->ProcessTargets();

			wm_debug("======================================");
			wm_debug("Starting prefetch");

			foreach ($this->datasourceclasses as $ds_class) {
				$this->plugins['data'][$ds_class]->Prefetch();
			}

			wm_debug("======================================");
			wm_debug("Starting main collection loop");

			$allitems = $this->buildAllItemsList();

			foreach ($allitems as $myobj) {
				$type = $myobj->my_type();

				$total_in=0;
				$total_out=0;
				$name=$myobj->name;

				wm_debug("");
				wm_debug("ReadData for $type $name: ");

				if (($type=='LINK' && isset($myobj->a)) || ($type=='NODE' && !is_null($myobj->x) ) ) {
					if (count($myobj->targets)>0) {
						$tindex = 0;

						foreach ($myobj->targets as $target) {
							wm_debug("ReadData: New Target: $target[4]");

							$targetstring = $target[0];
							$multiply = $target[1];

							$in = 0;
							$out = 0;
							$datatime = 0;
							if ($target[4] != '') {
								// processstring won't use notes (only hints) for this string
								$targetstring = $this->ProcessString($target[0], $myobj, false, false);

								if ($target[0] != $targetstring) {
									wm_debug("Targetstring is now $targetstring");
								}

								if ($multiply != 1) {
									wm_debug("Will multiply result by $multiply");
								}

								if ($target[0] != "") {
									$matched_by = $target[5];

									list($in,$out,$datatime) =  $this->plugins['data'][ $target[5] ]->ReadData($targetstring, $this, $myobj);
								}

								if (($in === null) && ($out === null)) {
									$in  = 0;
									$out = 0;

									wm_warn("ReadData: $type $name, target: $targetstring on config line $target[3] of $target[2] had no valid data, according to $matched_by");
								} else {
									if ($in === null) {
										$in = 0;
									}

									if ($out === null) {
										$out = 0;
									}
								}

								if ($multiply != 1) {
									wm_debug("Pre-multiply: $in $out");

									$in  = $multiply*$in;
									$out = $multiply*$out;

									wm_debug("Post-multiply: $in $out");
								}

								$total_in=$total_in + $in;
								$total_out=$total_out + $out;

								wm_debug("Aggregate so far: $total_in $total_out");

								# keep a track of the range of dates for data sources (mainly for MRTG/textfile based DS)
								if ($datatime > 0) {
									if ($this->max_data_time == null || $datatime > $this->max_data_time) {
										$this->max_data_time = $datatime;
									}

									if ($this->min_data_time == null || $datatime < $this->min_data_time) {
										$this->min_data_time = $datatime;
									}

									wm_debug("DataTime MINMAX: ".$this->min_data_time." -> ".$this->max_data_time);
								}

							}

							$tindex++;
						}

						wm_debug("ReadData complete for $type $name: $total_in $total_out");
					} else {
						wm_debug("ReadData: No targets for $type $name");
					}
				} else {
					wm_debug("ReadData: Skipping $type $name that looks like a template.");
				}

				# $this->links[$name]->bandwidth_in=$total_in;
				# $this->links[$name]->bandwidth_out=$total_out;
				$myobj->bandwidth_in = $total_in;
				$myobj->bandwidth_out = $total_out;

				if ($type == 'LINK' && $myobj->duplex=='half') {
					// in a half duplex link, in and out share a common bandwidth pool, so percentages need to include both
					wm_debug("Calculating percentage using half-duplex");

					$myobj->outpercent = (($total_in + $total_out) / ($myobj->max_bandwidth_out)) * 100;
					$myobj->inpercent = (($total_out + $total_in) / ($myobj->max_bandwidth_in)) * 100;

					if ($myobj->max_bandwidth_out != $myobj->max_bandwidth_in) {
						wm_warn("ReadData: $type $name: You're using asymmetric bandwidth AND half-duplex in the same link. That makes no sense. [WMWARN44]");
					}
				} else {
					$myobj->outpercent = (($total_out) / ($myobj->max_bandwidth_out)) * 100;
					$myobj->inpercent = (($total_in) / ($myobj->max_bandwidth_in)) * 100;
				}

				# print $myobj->name."=>".$myobj->inpercent."%/".$myobj->outpercent."\n";

				$warn_in  = true;
				$warn_out = true;

				if ($type=='NODE' && $myobj->scalevar =='in') {
					$warn_out = false;
				}

				if ($type=='NODE' && $myobj->scalevar =='out') {
					$warn_in = false;
				}

				if ($myobj->scaletype == 'percent') {
					list($incol,$inscalekey,$inscaletag) = $this->NewColourFromPercent($myobj->inpercent,$myobj->usescale,$myobj->name, true, $warn_in);
					list($outcol,$outscalekey, $outscaletag) = $this->NewColourFromPercent($myobj->outpercent,$myobj->usescale,$myobj->name, true, $warn_out);
				} else {
					// use absolute values, if that's what is requested
					list($incol,$inscalekey,$inscaletag) = $this->NewColourFromPercent($myobj->bandwidth_in,$myobj->usescale,$myobj->name, false, $warn_in);
					list($outcol,$outscalekey, $outscaletag) = $this->NewColourFromPercent($myobj->bandwidth_out,$myobj->usescale,$myobj->name, false, $warn_out);
				}

				$myobj->add_note("inscalekey",$inscalekey);
				$myobj->add_note("outscalekey",$outscalekey);

				$myobj->add_note("inscaletag",$inscaletag);
				$myobj->add_note("outscaletag",$outscaletag);

				$myobj->add_note("inscalecolor",$incol->as_html());
				$myobj->add_note("outscalecolor",$outcol->as_html());

				$myobj->colours[IN] = $incol;
				$myobj->colours[OUT] = $outcol;

				### warn("TAGS (setting) |$inscaletag| |$outscaletag| \n");

				wm_debug("ReadData: Setting $total_in,$total_out");
			}

			wm_debug("ReadData Completed.");
			wm_debug("------------------------------");
		}
	}

	// nodename is a vestigal parameter, from the days when nodes were just big labels
	function DrawLabelRotated($im, $x, $y, $angle, $text, $font, $padding, $linkname, $textcolour, $bgcolour, $outlinecolour, &$map, $direction) {
		list($strwidth, $strheight) = $this->myimagestringsize($font, $text);

		if (abs($angle) > 90){
			$angle -= 180;
		}

		if ($angle < -180) {
			$angle += 360;
		}

		$rangle = -deg2rad($angle);

		if ($padding == 0) {
			$padding = 4;
		}

		$x1 = $x - ($strwidth / 2)  - $padding;
		$x2 = $x + ($strwidth / 2)  + $padding;
		$y1 = $y - ($strheight / 2) - $padding;
		$y2 = $y + ($strheight / 2) + $padding;

		// a box. the last point is the start point for the text.
		$apoints  = array($x1, $y1, $x1, $y2, $x2, $y2, $x2, $y1, $x - round($strwidth / 2), $y + round($strheight / 2) - round($padding/2) + 1);
		$ppoints  = array($x1, $y1, $x1, $y2, $x2, $y2, $x2, $y1, $x1, $y1);

		foreach($ppoints as $index => $point) {
			$ppoints[$index] = round($point);
		}

		foreach($apoints as $index => $point) {
			$apoints[$index] = round($point);
		}

		$npoints = count($ppoints) / 2;

		rotateAboutPoint($ppoints, $x, $y, $rangle);

		if ($bgcolour != array(-1, -1, -1)) {
			$bgcol = myimagecolorallocate($im, $bgcolour[0], $bgcolour[1], $bgcolour[2]);
			//imagefilledrectangle($im, $x1, $y1, $x2, $y2, $bgcol);
			wimagefilledpolygon($im, $ppoints, 4, $bgcol);
		}

		if ($outlinecolour != array(-1, -1, -1)) {
			$outlinecol = myimagecolorallocate($im, $outlinecolour[0], $outlinecolour[1], $outlinecolour[2]);
			//imagerectangle($im, $x1, $y1, $x2, $y2, $outlinecol);
			wimagepolygon($im, $ppoints, 4, $outlinecol);
		}

		$textcol = myimagecolorallocate($im, $textcolour[0], $textcolour[1], $textcolour[2]);
		$this->myimagestring($im, $font, $apoints[8], $apoints[9], $text, $textcol, $angle);

		$areaname = 'LINK:L' . $map->links[$linkname]->id . ':' . ($direction + 2);

		// the rectangle is about half the size in the HTML, and easier to optimise/detect in the browser
		if ($angle == 0) {
			$map->imap->addArea('Rectangle', $areaname, '', array($x1, $y1, $x2, $y2));
			wm_debug("Adding Rectangle imagemap for $areaname");
		} else {
			$map->imap->addArea('Polygon', $areaname, '', $apoints);
			wm_debug("Adding Poly imagemap for $areaname");
		}
	}

	function ColourFromPercent($image, $percent, $scalename = 'DEFAULT', $name = '') {
		$col = null;
		$tag = '';

		$nowarn_clipping = intval($this->get_hint('nowarn_clipping'));
		$nowarn_scalemisses = intval($this->get_hint('nowarn_scalemisses'));

		$bt = debug_backtrace();
		$function = (isset($bt[1]['function']) ? $bt[1]['function'] : '');

		print "$function calls ColourFromPercent\n";

		exit();

		if (isset($this->colours[$scalename])) {
			$colours = $this->colours[$scalename];

			if ($percent > 100) {
				if ($nowarn_clipping == 0) {
					wm_warn("ColourFromPercent: Clipped $name $percent% to 100% [WMWARN33]");
				}

				$percent = 100;
			}

			foreach ($colours as $key => $colour) {
				if ($percent >= $colour['bottom'] && $percent <= $colour['top']) {
					if (isset($colour['tag'])) {
						$tag = $colour['tag'];
					}

					// we get called early now, so might not need to actually allocate a colour
					if (isset($image)) {
						if (isset($colour['red2'])) {
							if ($colour['bottom'] == $colour['top']) {
								$ratio = 0;
							} else {
								$ratio = ($percent - $colour['bottom']) / ($colour['top'] - $colour['bottom']);
							}

							$r = $colour['red1'] + ($colour['red2'] - $colour['red1']) * $ratio;
							$g = $colour['green1'] + ($colour['green2'] - $colour['green1']) * $ratio;
							$b = $colour['blue1'] + ($colour['blue2'] - $colour['blue1']) * $ratio;

							$col = myimagecolorallocate($image, $r, $g, $b);
						} else {
							$r = $colour['red1'];
							$g = $colour['green1'];
							$b = $colour['blue1'];

							$col = myimagecolorallocate($image, $r, $g, $b);
							# $col = $colour['gdref1'];
						}

						wm_debug("CFPC $name $tag $key $r $g $b");
					}

					### warn(">>TAGS CFPC $tag\n");

					return(array($col, $key, $tag));
				}
			}
		} else {
			if ($scalename != 'none') {
				wm_warn("ColourFromPercent: Attempted to use non-existent scale: $scalename for $name [WMWARN09]");
			} else {
				return array($this->white, '', '');
			}
		}

		// you'll only get grey for a COMPLETELY quiet link if there's no 0 in the SCALE lines
		if ($percent == 0) {
			return array($this->grey, '', '');
		}

		// and you'll only get white for a link with no colour assigned
		if ($nowarn_scalemisses == 0) {
			wm_warn("ColourFromPercent: Scale $scalename doesn't cover $percent% for $name [WMWARN29]");
		}

		return array($this->white, '', '');
	}

	function NewColourFromPercent($value, $scalename = 'DEFAULT', $name = '', $is_percent = true, $scale_warning = true) {
		$col = new Colour(0, 0, 0);
		$tag = '';

		$matchsize = null;

		$nowarn_clipping    = intval($this->get_hint('nowarn_clipping'));
		$nowarn_scalemisses = (!$scale_warning) || intval($this->get_hint('nowarn_scalemisses'));

		if (isset($this->colours[$scalename])) {
			$colours=$this->colours[$scalename];

			if ($is_percent && $value > 100) {
				if ($nowarn_clipping == 0) {
					wm_warn("NewColourFromPercent: Clipped $value% to 100% for item $name [WMWARN33]");
				}

				$value = 100;
			}

			if ($is_percent && $value < 0) {
				if ($nowarn_clipping == 0) {
					wm_warn("NewColourFromPercent: Clipped $value% to 0% for item $name [WMWARN34]");
				}

				$value = 0;
			}

			foreach ($colours as $key => $colour) {
				if ( (!isset($colour['special']) || $colour['special'] == 0) and ($value >= $colour['bottom']) and ($value <= $colour['top'])) {
					$range = $colour['top'] - $colour['bottom'];

					if (isset($colour['red2'])) {
						if ($colour['bottom'] == $colour['top']) {
							$ratio = 0;
						} else {
							$ratio = ($value - $colour['bottom']) / ($colour['top'] - $colour['bottom']);
						}

						$r = $colour['red1'] + ($colour['red2'] - $colour['red1']) * $ratio;
						$g = $colour['green1'] + ($colour['green2'] - $colour['green1']) * $ratio;
						$b = $colour['blue1'] + ($colour['blue2'] - $colour['blue1']) * $ratio;
					} else {
						$r = $colour['red1'];
						$g = $colour['green1'];
						$b = $colour['blue1'];

						# $col = new Colour($r, $g, $b);
						# $col = $colour['gdref1'];
					}

					// change in behaviour - with multiple matching ranges for a value, the smallest range wins
					if ( is_null($matchsize) || ($range < $matchsize) ) {
						$col = new Colour($r, $g, $b);
						$matchsize = $range;
					}

					if (isset($colour['tag'])) {
						$tag = $colour['tag'];
					}

					#### warn(">>NCFPC TAGS $tag\n");
					wm_debug("NCFPC $name $scalename $value '$tag' $key $r $g $b");

					return(array($col,$key,$tag));
				}
			}
		} else {
			if ($scalename != 'none') {
				wm_warn("ColourFromPercent: Attempted to use non-existent scale: $scalename for item $name [WMWARN09]");
			} else {
				return array(new Colour(255,255,255),'','');
			}
		}

		// shouldn't really get down to here if there's a complete SCALE

		// you'll only get grey for a COMPLETELY quiet link if there's no 0 in the SCALE lines
		if ($value == 0) {
			return array(new Colour(192,192,192),'','');
		}

		if ($nowarn_scalemisses == 0) {
			wm_warn("NewColourFromPercent: Scale $scalename doesn't include a line for $value" . ($is_percent ? '%' : '') . " while drawing item $name [WMWARN29]");
		}

		// and you'll only get white for a link with no colour assigned
		return array(new Colour(255, 255, 255), '', '');
	}

	function coloursort($a, $b) {
		if ($a['bottom'] == $b['bottom']) {
			if ($a['top'] < $b['top']) {
				return -1;
			}

			if ($a['top'] > $b['top']) {
				return 1;
			}

			return 0;
		}

		if ($a['bottom'] < $b['bottom']) {
			return -1;
		}

		return 1;
	}

	function FindScaleExtent($scalename = 'DEFAULT') {
		$max = -999999999999999999999;
		$min = - $max;

		if (isset($this->colours[$scalename])) {
			$colours = $this->colours[$scalename];

			foreach ($colours as $key => $colour) {
				if (!$colour['special']) {
					$min = min($colour['bottom'], $min);
					$max = max($colour['top'],  $max);
				}
			}
		} else {
			wm_warn("FindScaleExtent: non-existent SCALE $scalename [WMWARN43]");
		}

		return array($min, $max);
	}

	function DrawLegend_Horizontal($im, $scalename = 'DEFAULT', $width = 400) {
		$title=$this->keytext[$scalename];

		$colours = $this->colours[$scalename];
		$nscales = $this->numscales[$scalename];

		wm_debug("Drawing $nscales colours into SCALE");

		$font=$this->keyfont;

		# $x=$this->keyx[$scalename];
		# $y=$this->keyy[$scalename];
		$x = 0;
		$y = 0;

		# $width = 400;
		$scalefactor = $width/100;

		list($tilewidth, $tileheight) = $this->myimagestringsize($font, '100%');
		$box_left = $x;
		# $box_left = 0;
		$scale_left = $box_left + 4 + $scalefactor/2;
		$box_right = $scale_left + $width + $tilewidth + 4 + $scalefactor/2;
		$scale_right = $scale_left + $width;

		$box_top = $y;
		# $box_top = 0;
		$scale_top = $box_top + $tileheight + 6;
		$scale_bottom = $scale_top + $tileheight * 1.5;
		$box_bottom = $scale_bottom + $tileheight * 2 + 6;

		$scale_im = imagecreatetruecolor($box_right+1, $box_bottom+1);
		$scale_ref = 'gdref_legend_'.$scalename;

		// Start with a transparent box, in case the fill or outline colour is 'none'
		imageSaveAlpha($scale_im, true);
		$nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);
		imagefill($scale_im, 0, 0, $nothing);

		$this->AllocateScaleColours($scale_im,$scale_ref);

		if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
			wimagefilledrectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
				$this->colours['DEFAULT']['KEYBG'][$scale_ref]);
		}

		if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
			wimagerectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
				$this->colours['DEFAULT']['KEYOUTLINE'][$scale_ref]);
		}

		$this->myimagestring($scale_im, $font, $scale_left, $scale_bottom + $tileheight * 2 + 2 , $title,
			$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);

		for($p=0;$p<=100;$p++) {
			$dx = $p*$scalefactor;

			if (($p % 25) == 0) {
				imageline($scale_im, $scale_left + $dx, $scale_top - $tileheight,
					$scale_left + $dx, $scale_bottom + $tileheight,
					$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]
				);

				$labelstring=sprintf('%d%%', $p);

				$this->myimagestring($scale_im, $font, $scale_left + $dx + 2, $scale_top - 2, $labelstring,
					$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]);
			}

			list($col,$junk) = $this->NewColourFromPercent($p,$scalename);

			if ($col->is_real()) {
				$cc = $col->gdallocate($scale_im);

				wimagefilledrectangle($scale_im, $scale_left + $dx - $scalefactor/2, $scale_top,
					$scale_left + $dx + $scalefactor/2, $scale_bottom,
					$cc
				);
			}
		}

		imagecopy($im,$scale_im,$this->keyx[$scalename],$this->keyy[$scalename],0,0,imagesx($scale_im),imagesy($scale_im));

		$this->keyimage[$scalename] = $scale_im;

		$rx = $this->keyx[$scalename];
		$ry = $this->keyy[$scalename];

		$this->imap->addArea('Rectangle', "LEGEND:$scalename", '',
			array($rx+$box_left, $ry+$box_top, $rx+$box_right, $ry+$box_bottom)
		);
	}

	function DrawLegend_Vertical($im, $scalename = 'DEFAULT', $height = 400, $inverted = false) {
		$title   = $this->keytext[$scalename];

		$colours = $this->colours[$scalename];
		$nscales = $this->numscales[$scalename];

		wm_debug("Drawing $nscales colours into SCALE");

		$font = $this->keyfont;

		$x = $this->keyx[$scalename];
		$y = $this->keyy[$scalename];

		# $height = 400;
		$scalefactor = $height/100;

		list($tilewidth, $tileheight) = $this->myimagestringsize($font, '100%');

		# $box_left = $x;
		# $box_top = $y;
		$box_left = 0;
		$box_top  = 0;

		$scale_left  = $box_left+$scalefactor*2 +4 ;
		$scale_right = $scale_left + $tileheight*2;
		$box_right   = $scale_right + $tilewidth + $scalefactor*2 + 4;

		list($titlewidth,$titleheight) = $this->myimagestringsize($font,$title);

		if (($box_left + $titlewidth + $scalefactor*3) > $box_right) {
			$box_right = $box_left + $scalefactor*4 + $titlewidth;
		}

		$scale_top = $box_top + 4 + $scalefactor + $tileheight*2;
		$scale_bottom = $scale_top + $height;
		$box_bottom = $scale_bottom + $scalefactor + $tileheight/2 + 4;

		$scale_im  = imagecreatetruecolor($box_right+1, $box_bottom+1);
		$scale_ref = 'gdref_legend_'.$scalename;

		// Start with a transparent box, in case the fill or outline colour is 'none'
		imageSaveAlpha($scale_im, true);

		$nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);

		imagefill($scale_im, 0, 0, $nothing);

		$this->AllocateScaleColours($scale_im,$scale_ref);

		if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
			wimagefilledrectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
				$this->colours['DEFAULT']['KEYBG']['gdref1']);
		}

		if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
			wimagerectangle($scale_im, $box_left, $box_top, $box_right, $box_bottom,
				$this->colours['DEFAULT']['KEYOUTLINE']['gdref1']);
		}

		$this->myimagestring($scale_im, $font, $scale_left-$scalefactor, $scale_top - $tileheight , $title,
			$this->colours['DEFAULT']['KEYTEXT']['gdref1']
		);

		$updown = 1;

		if ($inverted) {
			$updown = -1;
		}

		for($p=0; $p<=100; $p++) {
			if ($inverted) {
				$dy = (100 - $p) * $scalefactor;
			} else {
				$dy = $p * $scalefactor;
			}

			if (($p % 25) == 0) {
				imageline($scale_im, $scale_left - $scalefactor, $scale_top + $dy,
					$scale_right + $scalefactor, $scale_top + $dy,
					$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]
				);

				$labelstring = sprintf('%d%%', $p);

				$this->myimagestring($scale_im, $font, $scale_right + $scalefactor*2, $scale_top + $dy + $tileheight/2,
					$labelstring,  $this->colours['DEFAULT']['KEYTEXT'][$scale_ref]
				);
			}

			list($col, $junk) = $this->NewColourFromPercent($p, $scalename);

			if ($col->is_real()) {
				$cc = $col->gdallocate($scale_im);

				wimagefilledrectangle($scale_im, $scale_left, $scale_top + $dy - $scalefactor/2,
					$scale_right, $scale_top + $dy + $scalefactor/2,
					$cc
				);
			}
		}

		imagecopy($im, $scale_im, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0, imagesx($scale_im), imagesy($scale_im));
		$this->keyimage[$scalename] = $scale_im;

		$rx = $this->keyx[$scalename];
		$ry = $this->keyy[$scalename];

		$this->imap->addArea('Rectangle', "LEGEND:$scalename", '',
			array($rx+$box_left, $ry+$box_top, $rx+$box_right, $ry+$box_bottom)
		);
	}

	function DrawLegend_Classic($im, $scalename = 'DEFAULT', $use_tags = false) {
		$title = $this->keytext[$scalename];

		$colours = $this->colours[$scalename];
		usort($colours, array('Weathermap', 'coloursort'));

		$nscales = $this->numscales[$scalename];

		wm_debug("Drawing $nscales colours into SCALE");

		$hide_zero    = intval($this->get_hint('key_hidezero_' . $scalename));
		$hide_percent = intval($this->get_hint('key_hidepercent_' . $scalename));

		// did we actually hide anything?
		$hid_zero = false;

		if (($hide_zero == 1) && isset($colours['0_0'])) {
			$nscales--;
			$hid_zero = true;
		}

		$font = $this->keyfont;

		$x = $this->keyx[$scalename];
		$y = $this->keyy[$scalename];

		list($tilewidth, $tileheight) = $this->myimagestringsize($font, 'MMMM');

		$tileheight  = $tileheight * 1.1;
		$tilespacing = $tileheight + 2;

		if (($this->keyx[$scalename] >= 0) && ($this->keyy[$scalename] >= 0)) {
			# $minwidth = imagefontwidth($font) * strlen('XX 100%-100%')+10;
			# $boxwidth = imagefontwidth($font) * strlen($title) + 10;

			list($minwidth, $junk)    = $this->myimagestringsize($font, 'MMMM 100%-100%');
			list($minminwidth, $junk) = $this->myimagestringsize($font, 'MMMM ');
			list($boxwidth, $junk)    = $this->myimagestringsize($font, $title);

			if ($use_tags) {
				$max_tag = 0;

				foreach ($colours as $colour) {
					if ( isset($colour['tag']) ) {
						list($w, $junk) = $this->myimagestringsize($font, $colour['tag']);

						# print $colour['tag']." $w \n";
						if ($w > $max_tag) $max_tag = $w;
					}
				}

				// now we can tweak the widths, appropriately to allow for the tag strings
				# print "$max_tag > $minwidth?\n";
				if (($max_tag + $minminwidth) > $minwidth) {
					$minwidth = $minminwidth + $max_tag;
				}

				# print "minwidth is now $minwidth\n";
			}

			$minwidth += 10;
			$boxwidth += 10;

			if ($boxwidth < $minwidth) {
				$boxwidth = $minwidth;
			}

			$boxheight = $tilespacing * ($nscales + 1) + 10;

			$boxx = $x;
			$boxy = $y;
			$boxx = 0;
			$boxy = 0;

			// allow for X11-style negative positioning
			if ($boxx < 0) {
				$boxx += $this->width;
			}

			if ($boxy < 0) {
				$boxy += $this->height;
			}

			$scale_im  = imagecreatetruecolor((int) $boxwidth+1, (int) $boxheight+1);
			$scale_ref = 'gdref_legend_' . $scalename;

			// Start with a transparent box, in case the fill or outline colour is 'none'
			imageSaveAlpha($scale_im, true);

			$nothing = imagecolorallocatealpha($scale_im, 128, 0, 0, 127);
			imagefill($scale_im, 0, 0, $nothing);

			$this->AllocateScaleColours($scale_im,$scale_ref);

			if (!is_none($this->colours['DEFAULT']['KEYBG'])) {
				wimagefilledrectangle($scale_im, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight,
					$this->colours['DEFAULT']['KEYBG'][$scale_ref]
				);
			}

			if (!is_none($this->colours['DEFAULT']['KEYOUTLINE'])) {
				wimagerectangle($scale_im, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight,
					$this->colours['DEFAULT']['KEYOUTLINE'][$scale_ref]
				);
			}

			$this->myimagestring($scale_im, $font, $boxx + 4, $boxy + 4 + $tileheight, $title,
				$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]
			);

			$i = 1;

			foreach ($colours as $colour) {
				if (!isset($colour['special']) || $colour['special'] == 0) {
					// pick a value in the middle...
					$value = ($colour['bottom'] + $colour['top']) / 2;

					wm_debug(sprintf('%f-%f (%f)  %d %d %d', $colour['bottom'], $colour['top'], $value, $colour['red1'], $colour['green1'], $colour['blue1']));

					#  debug("$i: drawing\n");
					if (($hide_zero == 0) || $colour['key'] != '0_0') {
						$y = $boxy + $tilespacing * $i + 8;
						$x = $boxx + 6;

						$fudgefactor = 0;

						if ($hid_zero && $colour['bottom'] == 0) {
							// calculate a small offset that can be added, which will hide the zero-value in a
							// gradient, but not make the scale incorrect. A quarter of a pixel should do it.
							$fudgefactor = ($colour['top'] - $colour['bottom']) / ($tilewidth * 4);
							# warn("FUDGING $fudgefactor\n");
						}

						// if it's a gradient, red2 is defined, and we need to sweep the values
						if (isset($colour['red2'])) {
							for ($n=0; $n <= $tilewidth; $n++) {
								$value = $fudgefactor + $colour['bottom'] + ($n / $tilewidth) * ($colour['top'] - $colour['bottom']);

								list($ccol, $junk) = $this->NewColourFromPercent($value, $scalename, '', false);

								$col = $ccol->gdallocate($scale_im);

								wimagefilledrectangle($scale_im, $x + $n, $y, $x + $n, $y + $tileheight, $col);
							}
						} else {
							// pick a value in the middle...
							//$value = ($colour['bottom'] + $colour['top']) / 2;
							list($ccol,$junk) = $this->NewColourFromPercent($value, $scalename, '', false);

							$col = $ccol->gdallocate($scale_im);

							wimagefilledrectangle($scale_im, $x, $y, $x + $tilewidth, $y + $tileheight, $col);
						}

						if ($use_tags) {
							$labelstring = '';

							if (isset($colour['tag'])) {
								$labelstring = $colour['tag'];
							}
						} else {
							$labelstring = sprintf('%s-%s', $colour['bottom'], $colour['top']);

							if ($hide_percent == 0) {
								$labelstring .= '%';
							}
						}

						$this->myimagestring($scale_im, $font, $x + 4 + $tilewidth, $y + $tileheight, $labelstring,
							$this->colours['DEFAULT']['KEYTEXT'][$scale_ref]
						);

						$i++;
					}

					imagecopy($im, $scale_im, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0, imagesx($scale_im), imagesy($scale_im));

					$this->keyimage[$scalename] = $scale_im;
				}
			}

			$this->imap->addArea('Rectangle', "LEGEND:$scalename", '',
				array($this->keyx[$scalename], $this->keyy[$scalename], $this->keyx[$scalename] + $boxwidth, $this->keyy[$scalename] + $boxheight)
			);

			# $this->imap->setProp("href","#","LEGEND");
			# $this->imap->setProp("extrahtml","onclick=\"position_legend();\"","LEGEND");
		}
	}

	/**
	 * Locale-formatted strftime using \IntlDateFormatter (PHP 8.1 compatible)
	 * This provides a cross-platform alternative to strftime() for when it will be removed from PHP.
	 * Note that output can be slightly different between libc sprintf and this function as it is using ICU.
	 *
	 * Usage:
	 * use function \PHP81_BC\strftime;
	 * print strftime('%A %e %B %Y %X', new \DateTime('2021-09-28 00:00:00'), 'fr_FR');
	 *
	 * Original use:
	 * \setlocale('fr_FR.UTF-8', LC_TIME);
	 * print \strftime('%A %e %B %Y %X', strtotime('2021-09-28 00:00:00'));
	 *
	 * @param  string $format Date format
	 * @param  integer|string|DateTime $timestamp Timestamp
	 * @return string
	 * @author BohwaZ <https://bohwaz.net/>
	 */
	function strftime(string $format, $timestamp = null, ?string $locale = null): string {
		if (null === $timestamp) {
			$timestamp = new \DateTime;
		} elseif (is_numeric($timestamp)) {
			$timestamp = date_create('@' . $timestamp);

			if ($timestamp) {
				$timestamp->setTimezone(new \DateTimezone(date_default_timezone_get()));
			}
		} elseif (is_string($timestamp)) {
			$timestamp = date_create($timestamp);
		}

		if (!($timestamp instanceof \DateTimeInterface)) {
			throw new \InvalidArgumentException('$timestamp argument is neither a valid UNIX timestamp, a valid date-time string or a DateTime object.');
		}

		$locale = substr((string) $locale, 0, 5);

		$intl_formats = [
			'%a' => 'EEE',	// An abbreviated textual representation of the day	Sun through Sat
			'%A' => 'EEEE',	// A full textual representation of the day	Sunday through Saturday
			'%b' => 'MMM',	// Abbreviated month name, based on the locale	Jan through Dec
			'%B' => 'MMMM',	// Full month name, based on the locale	January through December
			'%h' => 'MMM',	// Abbreviated month name, based on the locale (an alias of %b)	Jan through Dec
		];

		$intl_formatter = function (\DateTimeInterface $timestamp, string $format) use ($intl_formats, $locale) {
			$tz = $timestamp->getTimezone();
			$date_type = \IntlDateFormatter::FULL;
			$time_type = \IntlDateFormatter::FULL;
			$pattern = '';

			// %c = Preferred date and time stamp based on locale
			// Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
			if ($format == '%c') {
				$date_type = \IntlDateFormatter::LONG;
				$time_type = \IntlDateFormatter::SHORT;
			} elseif ($format == '%x') {
				// %x = Preferred date representation based on locale, without the time
				// Example: 02/05/09 for February 5, 2009
				$date_type = \IntlDateFormatter::SHORT;
				$time_type = \IntlDateFormatter::NONE;
			} elseif ($format == '%X') {
				// Localized time format
				$date_type = \IntlDateFormatter::NONE;
				$time_type = \IntlDateFormatter::MEDIUM;
			} else {
				$pattern = $intl_formats[$format];
			}

			return (new \IntlDateFormatter($locale, $date_type, $time_type, $tz, null, $pattern))->format($timestamp);
		};

		// Same order as https://www.php.net/manual/en/function.strftime.php
		$translation_table = [
			// Day
			'%a' => $intl_formatter,
			'%A' => $intl_formatter,
			'%d' => 'd',
			'%e' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('j'));
			},
			'%j' => function ($timestamp) {
				// Day number in year, 001 to 366
				return sprintf('%03d', $timestamp->format('z')+1);
			},
			'%u' => 'N',
			'%w' => 'w',

			// Week
			'%U' => function ($timestamp) {
				// Number of weeks between date and first Sunday of year
				$day = new \DateTime(sprintf('%d-01 Sunday', $timestamp->format('Y')));
				return sprintf('%02u', 1 + ($timestamp->format('z') - $day->format('z')) / 7);
			},
			'%V' => 'W',
			'%W' => function ($timestamp) {
				// Number of weeks between date and first Monday of year
				$day = new \DateTime(sprintf('%d-01 Monday', $timestamp->format('Y')));
				return sprintf('%02u', 1 + ($timestamp->format('z') - $day->format('z')) / 7);
			},

			// Month
			'%b' => $intl_formatter,
			'%B' => $intl_formatter,
			'%h' => $intl_formatter,
			'%m' => 'm',

			// Year
			'%C' => function ($timestamp) {
				// Century (-1): 19 for 20th century
				return floor($timestamp->format('Y') / 100);
			},
			'%g' => function ($timestamp) {
				return substr($timestamp->format('o'), -2);
			},
			'%G' => 'o',
			'%y' => 'y',
			'%Y' => 'Y',

			// Time
			'%H' => 'H',
			'%k' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('G'));
			},
			'%I' => 'h',
			'%l' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('g'));
			},
			'%M' => 'i',
			'%p' => 'A', // AM PM (this is reversed on purpose!)
			'%P' => 'a', // am pm
			'%r' => 'h:i:s A', // %I:%M:%S %p
			'%R' => 'H:i', // %H:%M
			'%S' => 's',
			'%T' => 'H:i:s', // %H:%M:%S
			'%X' => $intl_formatter, // Preferred time representation based on locale, without the date

			// Timezone
			'%z' => 'O',
			'%Z' => 'T',

			// Time and Date Stamps
			'%c' => $intl_formatter,
			'%D' => 'm/d/Y',
			'%F' => 'Y-m-d',
			'%s' => 'U',
			'%x' => $intl_formatter,
		];

		$out = preg_replace_callback('/(?<!%)(%[a-zA-Z])/', function ($match) use ($translation_table, $timestamp) {
			if ($match[1] == '%n') {
				return "\n";
			} elseif ($match[1] == '%t') {
				return "\t";
			}

			if (!isset($translation_table[$match[1]])) {
				throw new \InvalidArgumentException(sprintf('Format "%s" is unknown in time format', $match[1]));
			}

			$replace = $translation_table[$match[1]];

			if (is_string($replace)) {
				return $timestamp->format($replace);
			} else {
				return $replace($timestamp, $match[1]);
			}
		}, $format);

		$out = str_replace('%%', '%', $out);

		return $out;
	}

	function DrawTimestamp($im, $font, $colour, $which = '') {
		$this->datestamp = $this->strftime($this->stamptext, time());

		switch($which) {
			case 'MIN':
				$stamp = $this->strftime($this->minstamptext, $this->min_data_time);
				$pos_x = $this->mintimex;
				$pos_y = $this->mintimey;

				break;
			case 'MAX':
				$stamp = $this->strftime($this->maxstamptext, $this->max_data_time);
				$pos_x = $this->maxtimex;
				$pos_y = $this->maxtimey;

				break;
			default:
				$stamp = $this->datestamp;
				$pos_x = $this->timex;
				$pos_y = $this->timey;

				break;
		}

		list($boxwidth, $boxheight)=$this->myimagestringsize($font, $stamp);

		$x = $this->width - $boxwidth;
		$y = $boxheight;

		if (($pos_x != 0) && ($pos_y != 0)) {
			$x = $pos_x;
			$y = $pos_y;
		}

		$this->myimagestring($im, $font, $x, $y, $stamp, $colour);
		$this->imap->addArea('Rectangle', $which . 'TIMESTAMP', '', array($x, $y, $x + $boxwidth, $y - $boxheight));
	}

	function DrawTitle($im, $font, $colour) {
		$string = $this->ProcessString($this->title, $this);

		if ($this->get_hint('screenshot_mode')==1) {
			$string = screenshotify($string);
		}

		list($boxwidth, $boxheight) = $this->myimagestringsize($font, $string);

		$x = 10;
		$y = $this->titley - $boxheight;

		if (($this->titlex >= 0) && ($this->titley >= 0)) {
			$x = $this->titlex;
			$y = $this->titley;
		}

		$this->myimagestring($im, $font, $x, $y, $string, $colour);

		$this->imap->addArea('Rectangle', 'TITLE', '', array($x, $y, $x + $boxwidth, $y - $boxheight));
	}

	function ReadConfig($input, $is_include = false) {
		global $config, $weathermap_error_suppress;

		$curnode    = null;
		$curlink    = null;
		$matches    = 0;
		$nodesseen  = 0;
		$linksseen  = 0;
		$scalesseen = 0;
		$last_seen  = 'GLOBAL';
		$filename   = '';

		$objectlinecount = 0;

		// check if $input is more than one line. if it is, it's a text of a config file
		// if it isn't, it's the filename
		$lines = array();

		if (strchr($input, "\n") != false || strchr($input, "\r") != false) {
			wm_debug('ReadConfig Detected that this is a config fragment.');

			// strip out any Windows line-endings that have gotten in here
			$input    = str_replace("\r", '', $input);
			$lines    = explode("\n", $input);
			$filename = '{text insert}';
		} else {
			wm_debug('ReadConfig Detected that this is a config filename.');

			$filename = $input;

			if ($is_include){
				wm_debug('ReadConfig Detected that this is an INCLUDED config filename.');

				if ($is_include && in_array($filename, $this->included_files)) {
					wm_warn("Attempt to include '$filename' twice! Skipping it.");

					return(false);
				} else {
					$this->included_files[] = $filename;
					$this->has_includes = true;
				}
			}

			$fd = fopen($filename, 'r');

			if ($fd) {
				while (!feof($fd)) {
					$buffer = fgets($fd, 4096);

					// strip out any Windows line-endings that have gotten in here
					$buffer  = str_replace("\r", '', $buffer);
					$lines[] = $buffer;
				}

				fclose($fd);
			}
		}

		$linecount       = 0;
		$objectlinecount = 0;

		foreach($lines as $buffer) {
			$linematched = 0;

			$linecount++;

			if (preg_match('/^\s*#/', $buffer)) {
				// this is a comment line
			} else {
				$buffer = trim($buffer);

				// for any other config elements that are shared between nodes and links, they can use this
				unset($curobj);

				$curobj = null;

				if ($last_seen == 'LINK') {
					$curobj = &$curlink;
				}

				if ($last_seen == 'NODE') {
					$curobj = &$curnode;
				}

				if ($last_seen == 'GLOBAL') {
					$curobj = &$this;
				}

				$objectlinecount++;

				#if (preg_match("/^\s*(LINK|NODE)\s+([A-Za-z][A-Za-z0-9_\.\-\:]*)\s*$/i", $buffer, $matches))
				if (preg_match('/^\s*(LINK|NODE)\s+(\S+)\s*$/i', $buffer, $matches)) {
					$objectlinecount = 0;

					if (1 == 1) {
						$this->ReadConfig_Commit($curobj);
					} else {
						// first, save the previous item, before starting work on the new one
						if ($last_seen == 'NODE') {
							$this->nodes[$curnode->name] = $curnode;

							if ($curnode->template == 'DEFAULT') {
								$this->node_template_tree['DEFAULT'][] = $curnode->name;
							}

							wm_debug('Saving Node: ' . $curnode->name);
						}

						if ($last_seen == 'LINK') {
							if (isset($curlink->a) && isset($curlink->b)) {
								$this->links[$curlink->name] = $curlink;

								wm_debug('Saving Link: ' . $curlink->name);
							} else {
								$this->links[$curlink->name] = $curlink;

								wm_debug('Saving Template-Only Link: ' . $curlink->name);
							}

							if ($curlink->template == 'DEFAULT') {
								$this->link_template_tree['DEFAULT'][] = $curlink->name;
							}
						}
					}

					if ($matches[1] == 'LINK') {
						if ($matches[2] == 'DEFAULT') {
							if ($linksseen > 0) {
								wm_warn('LINK DEFAULT is not the first LINK. Defaults will not apply to earlier LINKs. [WMWARN26]');
							}

							unset($curlink);

							wm_debug('Loaded LINK DEFAULT');

							$curlink = $this->links['DEFAULT'];
						} else {
							unset($curlink);

							if (isset($this->links[$matches[2]])) {
								wm_warn('Duplicate link name ' . $matches[2] . " at line $linecount - only the last one defined is used. [WMWARN25]");
							}

							wm_debug('New LINK ' . $matches[2]);

							$curlink = new WeatherMapLink;

							$curlink->name = $matches[2];
							$curlink->Reset($this);

							$linksseen++;
						}

						$last_seen = 'LINK';

						$curlink->configline = $linecount;

						$linematched++;

						$curobj = &$curlink;
					}

					if ($matches[1] == 'NODE') {
						if ($matches[2] == 'DEFAULT') {
							if ($nodesseen > 0) {
								wm_warn('NODE DEFAULT is not the first NODE. Defaults will not apply to earlier NODEs. [WMWARN27]');
							}

							unset($curnode);

							wm_debug('Loaded NODE DEFAULT');

							$curnode = $this->nodes['DEFAULT'];
						} else {
							unset($curnode);

							if (isset($this->nodes[$matches[2]])) {
								wm_warn('Duplicate node name ' . $matches[2] . " at line $linecount - only the last one defined is used. [WMWARN24]");
							}

							$curnode = new WeatherMapNode;

							$curnode->name = $matches[2];

							$curnode->Reset($this);

							$nodesseen++;
						}

						$curnode->configline = $linecount;

						$last_seen = 'NODE';

						$linematched++;

						$curobj = &$curnode;
					}

					# record where we first heard about this object
					$curobj->defined_in = $filename;
				}

				// most of the config keywords just copy stuff into object properties.
				// these are all dealt with from this one array. The special-cases
				// follow on from that
				$config_keywords = array(
					array('LINK','/^\s*(MAXVALUE|BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',array('max_bandwidth_in_cfg'=>2,'max_bandwidth_out_cfg'=>3)),
					array('LINK','/^\s*(MAXVALUE|BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s*$/i',array('max_bandwidth_in_cfg'=>2,'max_bandwidth_out_cfg'=>2)),
					array('NODE','/^\s*(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',array('max_bandwidth_in_cfg'=>2,'max_bandwidth_out_cfg'=>3)),
					array('NODE','/^\s*(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s*$/i',array('max_bandwidth_in_cfg'=>2,'max_bandwidth_out_cfg'=>2)),
					array('GLOBAL','/^\s*BACKGROUND\s+(.*)\s*$/i',array('background'=>1)),
					array('GLOBAL','/^\s*HTMLOUTPUTFILE\s+(.*)\s*$/i',array('htmloutputfile'=>1)),
					array('GLOBAL','/^\s*HTMLSTYLESHEET\s+(.*)\s*$/i',array('htmlstylesheet'=>1)),
					array('GLOBAL','/^\s*IMAGEOUTPUTFILE\s+(.*)\s*$/i',array('imageoutputfile'=>1)),
					array('GLOBAL','/^\s*DATAOUTPUTFILE\s+(.*)\s*$/i',array('dataoutputfile'=>1)),
					array('GLOBAL','/^\s*IMAGEURI\s+(.*)\s*$/i',array('imageuri'=>1)),
					array('GLOBAL','/^\s*TITLE\s+(.*)\s*$/i',array('title'=>1)),
					array('GLOBAL','/^\s*HTMLSTYLE\s+(static|overlib)\s*$/i',array('htmlstyle'=>1)),
					array('GLOBAL','/^\s*KEYFONT\s+(\d+)\s*$/i',array('keyfont'=>1)),
					array('GLOBAL','/^\s*TITLEFONT\s+(\d+)\s*$/i',array('titlefont'=>1)),
					array('GLOBAL','/^\s*TIMEFONT\s+(\d+)\s*$/i',array('timefont'=>1)),
					array('GLOBAL','/^\s*TITLEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',array('titlex'=>1, 'titley'=>2)),
					array('GLOBAL','/^\s*TITLEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',array('titlex'=>1, 'titley'=>2, 'title'=>3)),
					array('GLOBAL','/^\s*TIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',array('timex'=>1, 'timey'=>2)),
					array('GLOBAL','/^\s*TIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',array('timex'=>1, 'timey'=>2, 'stamptext'=>3)),
					array('GLOBAL','/^\s*MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',array('mintimex'=>1, 'mintimey'=>2)),
					array('GLOBAL','/^\s*MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',array('mintimex'=>1, 'mintimey'=>2, 'minstamptext'=>3)),
					array('GLOBAL','/^\s*MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',array('maxtimex'=>1, 'maxtimey'=>2)),
					array('GLOBAL','/^\s*MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',array('maxtimex'=>1, 'maxtimey'=>2, 'maxstamptext'=>3)),
					array('NODE', "/^\s*LABEL\s*$/i", array('label'=>'')),	# special case for blank labels
					array('NODE', "/^\s*LABEL\s+(.*)\s*$/i", array('label'=>1)),
					array('(LINK|GLOBAL)', "/^\s*WIDTH\s+(\d+)\s*$/i", array('width'=>1)),
					array('(LINK|GLOBAL)', "/^\s*HEIGHT\s+(\d+)\s*$/i", array('height'=>1)),
					array('LINK', "/^\s*WIDTH\s+(\d+\.\d+)\s*$/i", array('width'=>1)),
					array('LINK', '/^\s*ARROWSTYLE\s+(classic|compact)\s*$/i', array('arrowstyle'=>1)),
					array('LINK', '/^\s*VIASTYLE\s+(curved|angled)\s*$/i', array('viastyle'=>1)),
					array('LINK', '/^\s*INCOMMENT\s+(.*)\s*$/i', array('comments[IN]'=>1)),
					array('LINK', '/^\s*OUTCOMMENT\s+(.*)\s*$/i', array('comments[OUT]'=>1)),
					array('LINK', '/^\s*BWFONT\s+(\d+)\s*$/i', array('bwfont'=>1)),
					array('LINK', '/^\s*COMMENTFONT\s+(\d+)\s*$/i', array('commentfont'=>1)),
					array('LINK', '/^\s*COMMENTSTYLE\s+(edge|center)\s*$/i', array('commentstyle'=>1)),
					array('LINK', '/^\s*DUPLEX\s+(full|half)\s*$/i', array('duplex'=>1)),
					array('LINK', '/^\s*BWSTYLE\s+(classic|angled)\s*$/i', array('labelboxstyle'=>1)),
					array('LINK', '/^\s*LINKSTYLE\s+(twoway|oneway)\s*$/i', array('linkstyle'=>1)),
					array('LINK', '/^\s*BWLABELPOS\s+(\d+)\s(\d+)\s*$/i', array('labeloffset_in'=>1,'labeloffset_out'=>2)),
					array('LINK', '/^\s*COMMENTPOS\s+(\d+)\s(\d+)\s*$/i', array('commentoffset_in'=>1, 'commentoffset_out'=>2)),
					array('LINK', '/^\s*USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s*$/i', array('usescale'=>1)),
					array('LINK', '/^\s*USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s+(absolute|percent)\s*$/i', array('usescale'=>1,'scaletype'=>2)),

					array('LINK', '/^\s*SPLITPOS\s+(\d+)\s*$/i', array('splitpos'=>1)),

					array('NODE', '/^\s*LABELOFFSET\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i', array('labeloffsetx'=>1,'labeloffsety'=>2)),
					array('NODE', '/^\s*LABELOFFSET\s+(C|NE|SE|NW|SW|N|S|E|W)\s*$/i', array('labeloffset'=>1)),
					array('NODE', '/^\s*LABELOFFSET\s+((C|NE|SE|NW|SW|N|S|E|W)\d+)\s*$/i', array('labeloffset'=>1)),
					array('NODE', '/^\s*LABELOFFSET\s+(-?\d+r\d+)\s*$/i', array('labeloffset'=>1)),

					array('NODE', '/^\s*LABELFONT\s+(\d+)\s*$/i', array('labelfont'=>1)),
					array('NODE', '/^\s*LABELANGLE\s+(0|90|180|270)\s*$/i', array('labelangle'=>1)),
					# array('(NODE|LINK)', '/^\s*TEMPLATE\s+(\S+)\s*$/i', array('template'=>1)),

					array('LINK', '/^\s*OUTBWFORMAT\s+(.*)\s*$/i', array('bwlabelformats[OUT]'=>1,'labelstyle'=>'--')),
					array('LINK', '/^\s*INBWFORMAT\s+(.*)\s*$/i', array('bwlabelformats[IN]'=>1,'labelstyle'=>'--')),
					# array('NODE','/^\s*ICON\s+none\s*$/i',array('iconfile'=>'')),
					array('NODE','/^\s*ICON\s+(\S+)\s*$/i', array('iconfile'=>1, 'iconscalew'=>'#0', 'iconscaleh'=>'#0')),
					array('NODE','/^\s*ICON\s+(\S+)\s*$/i', array('iconfile'=>1)),
					array('NODE','/^\s*ICON\s+(\d+)\s+(\d+)\s+(inpie|outpie|box|rbox|round|gauge|nink)\s*$/i', array('iconfile'=>3, 'iconscalew'=>1, 'iconscaleh'=>2)),
					array('NODE','/^\s*ICON\s+(\d+)\s+(\d+)\s+(\S+)\s*$/i', array('iconfile'=>3, 'iconscalew'=>1, 'iconscaleh'=>2)),

					array('NODE','/^\s*NOTES\s+(.*)\s*$/i', array('notestext[IN]'=>1,'notestext[OUT]'=>1)),
					array('LINK','/^\s*NOTES\s+(.*)\s*$/i', array('notestext[IN]'=>1,'notestext[OUT]'=>1)),
					array('LINK','/^\s*INNOTES\s+(.*)\s*$/i', array('notestext[IN]'=>1)),
					array('LINK','/^\s*OUTNOTES\s+(.*)\s*$/i', array('notestext[OUT]'=>1)),

					array('NODE','/^\s*INFOURL\s+(.*)\s*$/i', array('infourl[IN]'=>1,'infourl[OUT]'=>1)),
					array('LINK','/^\s*INFOURL\s+(.*)\s*$/i', array('infourl[IN]'=>1,'infourl[OUT]'=>1)),
					array('LINK','/^\s*ININFOURL\s+(.*)\s*$/i', array('infourl[IN]'=>1)),
					array('LINK','/^\s*OUTINFOURL\s+(.*)\s*$/i', array('infourl[OUT]'=>1)),

					array('NODE','/^\s*OVERLIBCAPTION\s+(.*)\s*$/i', array('overlibcaption[IN]'=>1,'overlibcaption[OUT]'=>1)),
					array('LINK','/^\s*OVERLIBCAPTION\s+(.*)\s*$/i', array('overlibcaption[IN]'=>1,'overlibcaption[OUT]'=>1)),
					array('LINK','/^\s*INOVERLIBCAPTION\s+(.*)\s*$/i', array('overlibcaption[IN]'=>1)),
					array('LINK','/^\s*OUTOVERLIBCAPTION\s+(.*)\s*$/i', array('overlibcaption[OUT]'=>1)),

					array('(NODE|LINK)', "/^\s*ZORDER\s+([-+]?\d+)\s*$/i", array('zorder'=>1)),
					array('(NODE|LINK)', "/^\s*OVERLIBWIDTH\s+(\d+)\s*$/i", array('overlibwidth'=>1)),
					array('(NODE|LINK)', "/^\s*OVERLIBHEIGHT\s+(\d+)\s*$/i", array('overlibheight'=>1)),
					array('NODE', "/^\s*POSITION\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i", array('x'=>1,'y'=>2)),
					array('NODE', "/^\s*POSITION\s+(\S+)\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i", array('x'=>2,'y'=>3,'original_x'=>2,'original_y'=>3,'relative_to'=>1,'relative_resolved'=>false)),
					array('NODE', "/^\s*POSITION\s+(\S+)\s+([-+]?\d+)r(\d+)\s*$/i", array('x'=>2,'y'=>3,'original_x'=>2,'original_y'=>3,'relative_to'=>1,'polar'=>true,'relative_resolved'=>false))
				);

				// alternative for use later where quoted strings are more useful
				$args = wm_parse_string($buffer);

				// this loop replaces a whole pile of duplicated ifs with something with consistent handling
				foreach ($config_keywords as $keyword) {
					if (preg_match('/' . $keyword[0] . '/', $last_seen)) {
						$statskey = $last_seen . '-' . $keyword[1];
						$statskey = str_replace(array('/^\s*', '\s*$/i'), array('', ''), $statskey);

						if (!isset($this->usage_stats[$statskey])) {
							$this->usage_stats[$statskey] = 0;
						}

						if (preg_match($keyword[1],$buffer,$matches)) {
							# print "CONFIG MATCHED: ".$keyword[1]."\n";

							$this->usage_stats[$statskey]++;

							foreach ($keyword[2] as $key=>$val) {
								// so we can poke in numbers too, if the value starts with #
								// then take the # off, and treat the rest as a number literal
								if (preg_match('/^#(.*)/', $val, $m)) {
									$val = $m[1];
								} elseif (is_numeric($val)) {
									// if it's a number, then it;s a match number,
									// otherwise it's a literal to be put into a variable
									$val = $matches[$val];
								}

								// assert('is_object($curobj)');

								if (preg_match('/^(.*)\[([^\]]+)\]$/',$key,$m)) {
									$index = constant($m[2]);
									$key = $m[1];
									$curobj->{$key}[$index] = $val;
								} else {
									$curobj->$key = $val;
								}
							}

							$linematched++;
							# print "\n\n";

							break;
						}
					}
				}

				if (preg_match('/^\s*NODES\s+(\S+)\s+(\S+)\s*$/i', $buffer, $matches)) {
					if ($last_seen == 'LINK') {
						$valid_nodes = 2;

						foreach (array(1, 2) as $i) {
							$endoffset[$i] ='C';
							$nodenames[$i] = $matches[$i];

							// percentage of compass - must be first
							if (preg_match('/:(NE|SE|NW|SW|N|S|E|W|C)(\d+)$/i', $matches[$i], $submatches)) {
								$endoffset[$i] = $submatches[1].$submatches[2];
								$nodenames[$i] = preg_replace('/:(NE|SE|NW|SW|N|S|E|W|C)\d+$/i', '', $matches[$i]);

								$this->need_size_precalc = true;
							}

							if (preg_match('/:(NE|SE|NW|SW|N|S|E|W|C)$/i', $matches[$i], $submatches)) {
								$endoffset[$i] = $submatches[1];
								$nodenames[$i] = preg_replace('/:(NE|SE|NW|SW|N|S|E|W|C)$/i', '', $matches[$i]);

								$this->need_size_precalc = true;
							}

							if (preg_match('/:(-?\d+r\d+)$/i', $matches[$i], $submatches)) {
								$endoffset[$i] = $submatches[1];
								$nodenames[$i] = preg_replace('/:(-?\d+r\d+)$/i', '', $matches[$i]);

								$this->need_size_precalc = true;
							}

							if (preg_match('/:([-+]?\d+):([-+]?\d+)$/i', $matches[$i], $submatches)) {
								$xoff = $submatches[1];
								$yoff = $submatches[2];

								$endoffset[$i] = $xoff . ':' . $yoff;
								$nodenames[$i] = preg_replace("/:$xoff:$yoff$/i", '', $matches[$i]);

								$this->need_size_precalc = true;
							}

							if (!array_key_exists($nodenames[$i], $this->nodes)) {
								wm_warn("Unknown node '" . $nodenames[$i] . "' on line $linecount of config");

								$valid_nodes--;
							}
						}

						// TODO - really, this should kill the whole link, and reset for the next one
						if ($valid_nodes == 2) {
							$curlink->a = $this->nodes[$nodenames[1]];
							$curlink->b = $this->nodes[$nodenames[2]];

							$curlink->a_offset = $endoffset[1];
							$curlink->b_offset = $endoffset[2];
						} else {
							// this'll stop the current link being added
							$last_seen = 'broken';
						}

						$linematched++;
					}
				}

				if ($last_seen == 'GLOBAL' && preg_match('/^\s*INCLUDE\s+(.*)\s*$/i', $buffer, $matches)) {
					if (file_exists($matches[1])){
						wm_debug("Including '{$matches[1]}'");

						$this->ReadConfig($matches[1], true);
						$last_seen = 'GLOBAL';
					} else {
						wm_warn("INCLUDE File '{$matches[1]}' not found!");
					}

					$linematched++;
				}

				if (($last_seen == 'NODE' || $last_seen == 'LINK' ) && preg_match('/^\s*TARGET\s+(.*)\s*$/i', $buffer, $matches)) {
					$linematched++;
					# $targets=preg_split('/\s+/', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
					$rawtargetlist = $matches[1] . ' ';

					if ($args[0]=='TARGET') {
						// wipe any existing targets, otherwise things in the DEFAULT accumulate with the new ones
						$curobj->targets = array();
						array_shift($args); // take off the actual TARGET keyword

						foreach($args as $arg) {
							// we store the original TARGET string, and line number, along with the breakdown, to make nicer error messages later
							// array of 7 things:
							// - only 0,1,2,3,4 are used at the moment (more used to be before DS plugins)
							// 0 => final target string (filled in by ReadData)
							// 1 => multiplier (filled in by ReadData)
							// 2 => config filename where this line appears
							// 3 => linenumber in that file
							// 4 => the original target string
							// 5 => the plugin to use to pull data
							$newtarget=array('', '', $filename, $linecount, $arg, '', '');

							if ($curobj) {
								wm_debug("  TARGET: $arg");

								$curobj->targets[] = $newtarget;
							}
						}
					}
				}

				if ($last_seen == 'LINK' && preg_match('/^\s*BWLABEL\s+(bits|percent|unformatted|none)\s*$/i', $buffer, $matches)) {
					$format_in  = '';
					$format_out = '';

					$style = strtolower($matches[1]);

					if ($style == 'percent') {
						$format_in  = FMT_PERC_IN;
						$format_out = FMT_PERC_OUT;
					}

					if ($style == 'bits') {
						$format_in  = FMT_BITS_IN;
						$format_out = FMT_BITS_OUT;
					}

					if ($style == 'unformatted') {
						$format_in  = FMT_UNFORM_IN;
						$format_out = FMT_UNFORM_OUT;
					}

					$curobj->labelstyle = $style;

					$curobj->bwlabelformats[IN]  = $format_in;
					$curobj->bwlabelformats[OUT] = $format_out;

					$linematched++;
				}

				if (preg_match('/^\s*SET\s+(\S+)\s+(.*)\s*$/i', $buffer, $matches)) {
					$curobj->add_hint($matches[1], trim($matches[2]));

					if ($curobj->my_type() == 'map' && substr($matches[1], 0, 7) == 'nowarn_') {
						$weathermap_error_suppress[$matches[1]] = 1;
					}

					$linematched++;
				}

				// allow setting a variable to ''
				if (preg_match('/^\s*SET\s+(\S+)\s*$/i', $buffer, $matches)) {
					$curobj->add_hint($matches[1],'');

					if ($curobj->my_type() == 'map' && substr($matches[1], 0, 7) == 'nowarn_') {
						$weathermap_error_suppress[$matches[1]] = 1;
					}

					$linematched++;
				}

				if (preg_match('/^\s*(IN|OUT)?OVERLIBGRAPH\s+(.+)$/i', $buffer, $matches)) {
					$this->has_overlibs = true;

					if ($last_seen == 'NODE' && $matches[1] != '') {
						wm_warn('IN/OUTOVERLIBGRAPH make no sense for a NODE! [WMWARN42]');
					} elseif ($last_seen == 'LINK' || $last_seen=='NODE' ) {
						$urls = preg_split('/\s+/', $matches[2], -1, PREG_SPLIT_NO_EMPTY);

						if ($matches[1] == 'IN') {
							$index = IN;
						}

						if ($matches[1] == 'OUT') {
							$index = OUT;
						}

						if ($matches[1] == '') {
							$curobj->overliburl[IN]  = $urls;
							$curobj->overliburl[OUT] = $urls;
						} else {
							$curobj->overliburl[$index] = $urls;
						}

						$linematched++;
					}
				}

				// array('(NODE|LINK)', '/^\s*TEMPLATE\s+(\S+)\s*$/i', array('template'=>1)),

				if (($last_seen == 'NODE' || $last_seen == 'LINK' ) && preg_match('/^\s*TEMPLATE\s+(\S+)\s*$/i', $buffer, $matches)) {
					$tname = $matches[1];

					if (($last_seen == 'NODE' && isset($this->nodes[$tname])) || ($last_seen == 'LINK' && isset($this->links[$tname]))) {
						$curobj->template = $matches[1];

						wm_debug("Resetting to template $last_seen " . $curobj->template);

						$curobj->Reset($this);

						if ($objectlinecount > 1) {
							wm_warn("line $linecount: TEMPLATE is not first line of object. Some data may be lost. [WMWARN39]");
						}

						// build up a list of templates - this will be useful later for the tree view

						if ($last_seen == 'NODE') {
							$this->node_template_tree[$tname][] = $curobj->name;
						}

						if ($last_seen == 'LINK') {
							$this->link_template_tree[$tname][] = $curobj->name;
						}
					} else {
						wm_warn("line $linecount: $last_seen TEMPLATE '$tname' doesn't exist! (if it does exist, check it's defined first) [WMWARN40]");
					}

					$linematched++;
				}

				if ($last_seen == 'LINK' && preg_match('/^\s*VIA\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i', $buffer, $matches)) {
					$curlink->vialist[] = array(
						$matches[1],
						$matches[2]
					);

					$linematched++;
				}

				if ($last_seen == 'LINK' && preg_match('/^\s*VIA\s+(\S+)\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i', $buffer, $matches)) {
					$curlink->vialist[] = array(
						$matches[2],
						$matches[3],
						$matches[1]
					);

					$linematched++;
				}

				if (($last_seen == 'NODE') && preg_match('/^\s*USE(ICON)?SCALE\s+([A-Za-z][A-Za-z0-9_]*)(\s+(in|out))?(\s+(absolute|percent))?\s*$/i', $buffer, $matches)) {
					$svar  = '';
					$stype = 'percent';

					if (isset($matches[3])) {
						$svar = trim($matches[3]);
					}

					if (isset($matches[6])) {
						$stype = strtolower(trim($matches[6]));
					}

					// opens the door for other scaley things...
					switch($matches[1]) {
						case 'ICON':
							$varname  = 'iconscalevar';
							$uvarname = 'useiconscale';
							$tvarname = 'iconscaletype';

							// if (!function_exists("imagefilter"))
							// {
							// 	warn("ICON SCALEs require imagefilter, which is not present in your PHP [WMWARN040]\n");
							// }

							break;
						default:
							$varname  = 'scalevar';
							$uvarname = 'usescale';
							$tvarname = 'scaletype';

							break;
					}

					if ($svar != '') {
						$curnode->$varname = $svar;
					}

					$curnode->$tvarname = $stype;
					$curnode->$uvarname = $matches[2];

					// warn("Set $varname and $uvarname\n");

					// print ">> $stype $svar ".$matches[2]." ".$curnode->name." \n";

					$linematched++;
				}

				// one REGEXP to rule them all:
//					if (preg_match("/^\s*SCALE\s+([A-Za-z][A-Za-z0-9_]*\s+)?(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+)\s+(\d+)\s+(\d+)(?:\s+(\d+)\s+(\d+)\s+(\d+))?\s*$/i",
//		0.95b		if (preg_match("/^\s*SCALE\s+([A-Za-z][A-Za-z0-9_]*\s+)?(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+)\s+(\d+)\s+(\d+)(?:\s+(\d+)\s+(\d+)\s+(\d+))?\s*(.*)$/i",
				if (preg_match('/^\s*SCALE\s+([A-Za-z][A-Za-z0-9_]*\s+)?(\-?\d+\.?\d*[munKMGT]?)\s+(\-?\d+\.?\d*[munKMGT]?)\s+(?:(\d+)\s+(\d+)\s+(\d+)(?:\s+(\d+)\s+(\d+)\s+(\d+))?|(none))\s*(.*)$/i', $buffer, $matches)) {

					// The default scale name is DEFAULT
					if ($matches[1]=='') {
						$matches[1] = 'DEFAULT';
					} else {
						$matches[1] = trim($matches[1]);
					}

					$key=$matches[2] . '_' . $matches[3];

					$this->colours[$matches[1]][$key]['key'] = $key;

					$tag = $matches[11];

					$this->colours[$matches[1]][$key]['tag'] = $tag;

					$this->colours[$matches[1]][$key]['bottom']  = unformat_number($matches[2], $this->kilo);
					$this->colours[$matches[1]][$key]['top']     = unformat_number($matches[3], $this->kilo);
					$this->colours[$matches[1]][$key]['special'] = 0;

					if (isset($matches[10]) && $matches[10] == 'none') {
						$this->colours[$matches[1]][$key]['red1']   = -1;
						$this->colours[$matches[1]][$key]['green1'] = -1;
						$this->colours[$matches[1]][$key]['blue1']  = -1;
					} else {
						$this->colours[$matches[1]][$key]['red1']   = (int)($matches[4]);
						$this->colours[$matches[1]][$key]['green1'] = (int)($matches[5]);
						$this->colours[$matches[1]][$key]['blue1']  = (int)($matches[6]);
					}

					// this is the second colour, if there is one
					if (isset($matches[7]) && $matches[7] != '') {
						$this->colours[$matches[1]][$key]['red2']   = (int) ($matches[7]);
						$this->colours[$matches[1]][$key]['green2'] = (int) ($matches[8]);
						$this->colours[$matches[1]][$key]['blue2']  = (int) ($matches[9]);
					}

					if (! isset($this->numscales[$matches[1]])) {
						$this->numscales[$matches[1]] = 1;
					} else {
						$this->numscales[$matches[1]]++;
					}

					// we count if we've seen any default scale, otherwise, we have to add
					// one at the end.
					if ($matches[1] == 'DEFAULT') {
						$scalesseen++;
					}

					$linematched++;
				}

				if (preg_match('/^\s*KEYPOS\s+([A-Za-z][A-Za-z0-9_]*\s+)?(-?\d+)\s+(-?\d+)(.*)/i', $buffer, $matches)) {
					$whichkey = trim($matches[1]);

					if ($whichkey == '') {
						$whichkey = 'DEFAULT';
					}

					$this->keyx[$whichkey] = $matches[2];
					$this->keyy[$whichkey] = $matches[3];

					$extra = trim($matches[4]);

					if ($extra != '') {
						$this->keytext[$whichkey] = $extra;
					}

					if (!isset($this->keytext[$whichkey])) {
						$this->keytext[$whichkey] = 'DEFAULT TITLE';
					}

					if (!isset($this->keystyle[$whichkey])) {
						$this->keystyle[$whichkey] = 'classic';
					}

					$linematched++;
				}

				// truetype font definition (actually, we don't really check if it's truetype) - filename + size
				if (preg_match('/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s+(\d+)\s*$/i', $buffer, $matches)) {
					if (function_exists('imagettfbbox')) {
						// test if this font is valid, before adding it to the font table...
						$bounds = imagettfbbox($matches[3], 0, $matches[2], 'Ignore me');

						if (isset($bounds[0])) {
							$this->fonts[$matches[1]] = new WMFont();

							$this->fonts[$matches[1]]->type = 'truetype';
							$this->fonts[$matches[1]]->file = $matches[2];
							$this->fonts[$matches[1]]->size = $matches[3];
						} else {
							wm_warn('Failed to load ttf font ' . $matches[2] . " - at config line $linecount [WMWARN30]");
						}
					} else {
						wm_warn("imagettfbbox() is not a defined function. You don't seem to have FreeType compiled into your gd module. [WMWARN31]");
					}

					$linematched++;
				}

				// GD font definition (no size here)
				if (preg_match('/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s*$/i', $buffer, $matches)) {
					$newfont=imageloadfont($matches[2]);

					if ($newfont) {
						$this->fonts[$matches[1]] = new WMFont();

						$this->fonts[$matches[1]]->type     = 'gd';
						$this->fonts[$matches[1]]->file     = $matches[2];
						$this->fonts[$matches[1]]->gdnumber = $newfont;
					} else {
						wm_warn('Failed to load GD font: ' . $matches[2] . " ($newfont) at config line $linecount [WMWARN32]");
					}

					$linematched++;
				}

				if (preg_match('/^\s*KEYSTYLE\s+([A-Za-z][A-Za-z0-9_]+\s+)?(classic|horizontal|vertical|inverted|tags)\s?(\d+)?\s*$/i', $buffer, $matches)) {
					$whichkey = trim($matches[1]);

					if ($whichkey == '') {
						$whichkey = 'DEFAULT';
					}

					$this->keystyle[$whichkey] = strtolower($matches[2]);

					if (isset($matches[3]) && $matches[3] != '') {
						$this->keysize[$whichkey] = $matches[3];
					} else {
						$this->keysize[$whichkey] = $this->keysize['DEFAULT'];
					}

					$linematched++;
				}

				if (preg_match('/^\s*KILO\s+(\d+)\s*$/i', $buffer, $matches)) {
					$this->kilo=$matches[1];

					# $this->defaultlink->owner->kilo=$matches[1];
					# $this->links['DEFAULT']=$matches[1];
					$linematched++;
				}

				if (preg_match('/^\s*(TIME|TITLE|KEYBG|KEYTEXT|KEYOUTLINE|BG)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none)\s*$/i', $buffer, $matches)) {
					$key = $matches[1];
					$val = strtolower($matches[2]);

					# "Found colour line for $key\n";
					if (isset($matches[3]))	{
						// this is a regular colour setting thing
						$this->colours['DEFAULT'][$key]['red1']    = $matches[3];
						$this->colours['DEFAULT'][$key]['green1']  = $matches[4];
						$this->colours['DEFAULT'][$key]['blue1']   = $matches[5];
						$this->colours['DEFAULT'][$key]['bottom']  = -2;
						$this->colours['DEFAULT'][$key]['top']     = -1;
						$this->colours['DEFAULT'][$key]['special'] = 1;

						$linematched++;
					}

					if ($val == 'none' && ($matches[1]=='KEYBG' || $matches[1]=='KEYOUTLINE')) {
						$this->colours['DEFAULT'][$key]['red1']    = -1;
						$this->colours['DEFAULT'][$key]['green1']  = -1;
						$this->colours['DEFAULT'][$key]['blue1']   = -1;
						$this->colours['DEFAULT'][$key]['bottom']  = -2;
						$this->colours['DEFAULT'][$key]['top']     = -1;
						$this->colours['DEFAULT'][$key]['special'] = 1;

						$linematched++;
					}
				}

				if (($last_seen == 'NODE') && (preg_match('/^\s*(AICONOUTLINE|AICONFILL|LABELFONT|LABELFONTSHADOW|LABELBG|LABELOUTLINE)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none|contrast|copy)\s*$/i', $buffer, $matches))) {

					$key   = $matches[1];
					$field = strtolower($matches[1]) . 'colour';
					$val   = strtolower($matches[2]);

					if (isset($matches[3]))	{
						// this is a regular colour setting thing
						$curnode->$field = array($matches[3], $matches[4], $matches[5]);

						$linematched++;
					}

					if ($val == 'none' && ($matches[1] == 'LABELFONTSHADOW' || $matches[1] == 'LABELBG' || $matches[1] == 'LABELOUTLINE' || $matches[1] == 'AICONFILL' || $matches[1] == 'AICONOUTLINE')) {
						$curnode->$field = array(-1, -1, -1);

						$linematched++;
					}

					if ($val == 'contrast' && $matches[1] == 'LABELFONT') {
						$curnode->$field = array(-3, -3, -3);

						$linematched++;
					}

					if ($matches[2] == 'copy' && $matches[1] == 'AICONFILL') {
						$curnode->$field=array(-2, -2, -2);

						$linematched++;
					}
				}

				if (($last_seen == 'LINK') && (preg_match('/^\s*(COMMENTFONT|BWBOX|BWFONT|BWOUTLINE|OUTLINE)COLOR\s+((\d+)\s+(\d+)\s+(\d+)|none|contrast|copy)\s*$/i', $buffer, $matches))) {

					$key   = $matches[1];
					$field = strtolower($matches[1]) . 'colour';
					$val   = strtolower($matches[2]);

					if (isset($matches[3]))	{
						$curlink->$field = array($matches[3], $matches[4], $matches[5]);

						$linematched++;
					}

					if ($val == 'none' && ($key == 'BWBOX' || $key == 'BWOUTLINE' || $key == 'OUTLINE' || $key == 'KEYOUTLINE' || $key == 'KEYBG')) {
						$curlink->$field = array(-1, -1, -1);

						$linematched++;
					}

					if ($val == 'contrast' && $key == 'COMMENTFONT') {
						$curlink->$field = array(-3, -3, -3);

						$linematched++;
					}
				}

				if ($last_seen == 'LINK' && preg_match('/^\s*ARROWSTYLE\s+(\d+)\s+(\d+)\s*$/i', $buffer, $matches)) {
					$curlink->arrowstyle = $matches[1] . ' ' . $matches[2];

					$linematched++;
				}


				if ($linematched == 0 && trim($buffer) != '') {
					wm_warn("Unrecognised config on line $linecount: $buffer");
				}

				if ($linematched > 1) {
					wm_warn("Same line ($linecount) interpreted twice. This is a program error. Please report to Howie with your config!");
					wm_warn("The line was: $buffer");
				}
			} // if blankline
		} // while

		if (1 == 1) {
			$this->ReadConfig_Commit($curobj);
		} else {
			if ($last_seen == 'NODE') {
				$this->nodes[$curnode->name] = $curnode;

				wm_debug('Saving Node: ' . $curnode->name);

				if ($curnode->template == 'DEFAULT') {
					$this->node_template_tree['DEFAULT'][] = $curnode->name;
				}
			}

			if ($last_seen == 'LINK') {
				if (isset($curlink->a) && isset($curlink->b)) {
					$this->links[$curlink->name] = $curlink;

					wm_debug('Saving Link: ' . $curlink->name);

					if ($curlink->template == 'DEFAULT') {
						$this->link_template_tree['DEFAULT'][] = $curlink->name;
					}
				} else {
					wm_warn('Dropping LINK ' . $curlink->name . ' - it hasn\'t got 2 NODES!');
				}
			}
		}

		wm_debug("ReadConfig has finished reading the config ($linecount lines)");
		wm_debug('------------------------------------------');

		// load some default colouring, otherwise it all goes wrong
		if ($scalesseen == 0) {
			wm_debug('Adding default SCALE colour set (no SCALE lines seen).');

			$defaults = array(
				'0_0'    => array('bottom' => 0, 'top' => 0, 'red1' => 192, 'green1' => 192, 'blue1' => 192, 'special'=>0),
				'0_1'    => array('bottom' => 0, 'top' => 1, 'red1' => 255, 'green1' => 255, 'blue1' => 255, 'special'=>0),
				'1_10'   => array('bottom' => 1, 'top' => 10, 'red1' => 140, 'green1' => 0, 'blue1' => 255, 'special'=>0),
				'10_25'  => array('bottom' => 10, 'top' => 25, 'red1' => 32, 'green1' => 32, 'blue1' => 255, 'special'=>0),
				'25_40'  => array('bottom' => 25, 'top' => 40, 'red1' => 0, 'green1' => 192, 'blue1' => 255, 'special'=>0),
				'40_55'  => array('bottom' => 40, 'top' => 55, 'red1' => 0, 'green1' => 240, 'blue1' => 0, 'special'=>0),
				'55_70'  => array('bottom' => 55, 'top' => 70, 'red1' => 240, 'green1' => 240, 'blue1' => 0, 'special'=>0),
				'70_85'  => array('bottom' => 70, 'top' => 85, 'red1' => 255, 'green1' => 192, 'blue1' => 0, 'special'=>0),
				'85_100' => array('bottom' => 85, 'top' => 100, 'red1' => 255, 'green1' => 0, 'blue1' => 0, 'special'=>0)
			);

			foreach ($defaults as $key => $def) {
				$this->colours['DEFAULT'][$key]        = $def;
				$this->colours['DEFAULT'][$key]['key'] = $key;
				$scalesseen++;
			}

			// we have a 0-0 line now, so we need to hide that.
			$this->add_hint('key_hidezero_DEFAULT', 1);
		} else {
			wm_debug("Already have $scalesseen scales, no defaults added.");
		}

		$this->numscales['DEFAULT'] = $scalesseen;
		$this->configfile = $filename;

		if ($this->has_overlibs && $this->htmlstyle == 'static') {
			wm_warn('OVERLIBGRAPH is used, but HTMLSTYLE is static. This is probably wrong. [WMWARN41]');
		}

		wm_debug('Building cache of z-layers and finalising bandwidth.');

// 		$allitems = array_merge($this->links, $this->nodes);

		$allitems = array();
		foreach ($this->nodes as $node) {
			$allitems[] = $node;
		}

		foreach ($this->links as $link) {
			$allitems[] = $link;
		}

		# foreach ($allitems as &$item)
		foreach ($allitems as $ky => $vl) {
			$item = &$allitems[$ky];
			$z    = $item->zorder;

			if (!isset($this->seen_zlayers[$z]) || !is_array($this->seen_zlayers[$z])) {
				$this->seen_zlayers[$z] = array();
			}

			array_push($this->seen_zlayers[$z], $item);

			// while we're looping through, let's set the real bandwidths
			if ($item->my_type() == 'LINK') {
				$this->links[$item->name]->max_bandwidth_in  = unformat_number($item->max_bandwidth_in_cfg, $this->kilo);
				$this->links[$item->name]->max_bandwidth_out = unformat_number($item->max_bandwidth_out_cfg, $this->kilo);
			} elseif ($item->my_type() == 'NODE') {
				$this->nodes[$item->name]->max_bandwidth_in  = unformat_number($item->max_bandwidth_in_cfg, $this->kilo);
				$this->nodes[$item->name]->max_bandwidth_out = unformat_number($item->max_bandwidth_out_cfg, $this->kilo);
			} else {
				wm_warn('Internal bug - found an item of type: ' . $item->my_type());
			}

			// $item->max_bandwidth_in=unformat_number($item->max_bandwidth_in_cfg, $this->kilo);
			// $item->max_bandwidth_out=unformat_number($item->max_bandwidth_out_cfg, $this->kilo);

			wm_debug(sprintf('   Setting bandwidth on ' . $item->my_type() . " $item->name (%s -> %d bps, %s -> %d bps, KILO = %d)",
				$item->max_bandwidth_in_cfg, $item->max_bandwidth_in, $item->max_bandwidth_out_cfg, $item->max_bandwidth_out, $this->kilo)
			);
		}

		wm_debug('Found ' . sizeof($this->seen_zlayers) . ' z-layers including builtins (0,100).');

		// calculate any relative positions here - that way, nothing else
		// really needs to know about them

		wm_debug('Resolving relative positions for NODEs...');

		// safety net for cyclic dependencies
		$i = 100;

		do {
			$skipped = 0; $set=0;

			foreach ($this->nodes as $node) {
				if ($node->relative_to != '' && !$node->relative_resolved) {
					wm_debug('Resolving relative position for NODE ' . $node->name . ' to ' . $node->relative_to);

					if (array_key_exists($node->relative_to,$this->nodes)) {
						// check if we are relative to another node which is in turn relative to something
						// we need to resolve that one before we can resolve this one!
						if ($this->nodes[$node->relative_to]->relative_to != '' && !$this->nodes[$node->relative_to]->relative_resolved) {
							wm_debug('Skipping unresolved relative_to. Let\'s hope it\'s not a circular one');

							$skipped++;
						} else {
							$rx = $this->nodes[$node->relative_to]->x;
							$ry = $this->nodes[$node->relative_to]->y;

							if ($node->polar) {
								// treat this one as a POLAR relative coordinate.
								// - draw rings around a node!
								$angle    = $node->x;
								$distance = $node->y;
								$newpos_x = $rx + $distance * sin(deg2rad($angle));
								$newpos_y = $ry - $distance * cos(deg2rad($angle));

								wm_debug("->$newpos_x,$newpos_y");

								$this->nodes[$node->name]->x = $newpos_x;
								$this->nodes[$node->name]->y = $newpos_y;

								$this->nodes[$node->name]->relative_resolved = true;

								$set++;
							} else {
								// save the relative coords, so that WriteConfig can work
								// resolve the relative stuff

								$newpos_x = $rx + $this->nodes[$node->name]->x;
								$newpos_y = $ry + $this->nodes[$node->name]->y;

								wm_debug("->$newpos_x,$newpos_y");

								$this->nodes[$node->name]->x = $newpos_x;
								$this->nodes[$node->name]->y = $newpos_y;

								$this->nodes[$node->name]->relative_resolved = true;

								$set++;
							}
						}
					} else {
						wm_warn('NODE ' . $node->name . ' has a relative position to an unknown node! [WMWARN10]');
					}
				}
			}

			wm_debug("Relative Positions Cycle $i - set $set and Skipped $skipped for unresolved dependencies");

			$i--;
		} while($set > 0 && $i != 0);

		if ($skipped > 0) {
			wm_warn("There are Circular dependencies in relative POSITION lines for $skipped nodes. [WMWARN11]");
		}

		wm_debug('-----------------------------------');

		wm_debug('Running Pre-Processing Plugins...');

		foreach ($this->preprocessclasses as $pre_class) {
			wm_debug("Running $pre_class" . '->run()');

			$this->plugins['pre'][$pre_class]->run($this);
		}

		wm_debug('Finished Pre-Processing Plugins...');

		return (true);
	}

	function ReadConfig_Commit(&$curobj) {
		if (is_null($curobj)) {
			return;
		}

		$last_seen = $curobj->my_type();

		// first, save the previous item, before starting work on the new one
		if ($last_seen == 'NODE') {
			$this->nodes[$curobj->name] = $curobj;

			wm_debug('Saving Node: ' . $curobj->name);

			if ($curobj->template == 'DEFAULT') {
				$this->node_template_tree['DEFAULT'][] = $curobj->name;
			}
		}

		if ($last_seen == 'LINK') {
			if (isset($curobj->a) && isset($curobj->b)) {
				$this->links[$curobj->name] = $curobj;

				wm_debug('Saving Link: ' . $curobj->name);
			} else {
				$this->links[$curobj->name]=$curobj;

				wm_debug('Saving Template-Only Link: ' . $curobj->name);
			}

			if ($curobj->template == 'DEFAULT') {
				$this->link_template_tree['DEFAULT'][] = $curobj->name;
			}
		}
	}

	function WriteDataFile($filename) {
		if ($filename != '') {
			$fd = fopen($filename, 'w');
			# $output = '';

			if ($fd) {
				foreach ($this->nodes as $node) {
					if (!preg_match('/^::\s/', $node->name) && sizeof($node->targets)>0 )  {
						fputs($fd, sprintf("N_%s\t%f\t%f\r\n", $node->name, $node->bandwidth_in, $node->bandwidth_out));
					}
				}

				foreach ($this->links as $link) {
					if (!preg_match('/^::\s/', $link->name) && sizeof($link->targets)>0) {
						fputs($fd, sprintf("L_%s\t%f\t%f\r\n", $link->name, $link->bandwidth_in, $link->bandwidth_out));
					}
				}

				fclose($fd);
			}
		}
	}

	function WriteConfig($filename) {
		$fd     = fopen($filename, 'w');
		$output = '';

		$weathermap_version = plugin_weathermap_numeric_version();

		if ($fd) {
			$output .= "# Automatically generated by php-weathermap v$weathermap_version\n\n";

			if (count($this->fonts) > 0) {
				foreach ($this->fonts as $fontnumber => $font) {
					if ($font->type == 'truetype') {
						$output .= sprintf("FONTDEFINE %d %s %d\n", $fontnumber, $font->file, $font->size);
					}

					if ($font->type == 'gd') {
						$output .= sprintf("FONTDEFINE %d %s\n", $fontnumber, $font->file);
					}
				}

				$output .= PHP_EOL;
			}

			$basic_params = array(
				array('background', 'BACKGROUND', CONFIG_TYPE_LITERAL),
				array('width', 'WIDTH', CONFIG_TYPE_LITERAL),
				array('height', 'HEIGHT', CONFIG_TYPE_LITERAL),
				array('htmlstyle', 'HTMLSTYLE', CONFIG_TYPE_LITERAL),
				array('kilo', 'KILO', CONFIG_TYPE_LITERAL),
				array('keyfont', 'KEYFONT', CONFIG_TYPE_LITERAL),
				array('timefont', 'TIMEFONT', CONFIG_TYPE_LITERAL),
				array('titlefont', 'TITLEFONT', CONFIG_TYPE_LITERAL),
				array('title', 'TITLE', CONFIG_TYPE_LITERAL),
				array('htmloutputfile', 'HTMLOUTPUTFILE', CONFIG_TYPE_LITERAL),
				array('dataoutputfile', 'DATAOUTPUTFILE', CONFIG_TYPE_LITERAL),
				array('htmlstylesheet', 'HTMLSTYLESHEET', CONFIG_TYPE_LITERAL),
				array('imageuri', 'IMAGEURI', CONFIG_TYPE_LITERAL),
				array('imageoutputfile', 'IMAGEOUTPUTFILE', CONFIG_TYPE_LITERAL)
			);

			foreach ($basic_params as $param) {
				$field = $param[0];
				$keyword = $param[1];

				if ($this->$field != $this->inherit_fieldlist[$field]) {
					if ($param[2] == CONFIG_TYPE_COLOR) {
						$output .= "$keyword " . render_colour($this->$field) . PHP_EOL;
					}

					if ($param[2] == CONFIG_TYPE_LITERAL) {
						$output .= "$keyword " . $this->$field . PHP_EOL;
					}
				}
			}

			if (($this->timex != $this->inherit_fieldlist['timex'])
				|| ($this->timey != $this->inherit_fieldlist['timey'])
				|| ($this->stamptext != $this->inherit_fieldlist['stamptext'])
			) {
				$output .= 'TIMEPOS ' . $this->timex . ' ' . $this->timey . ' ' . $this->stamptext . PHP_EOL;
			}

			if (($this->mintimex != $this->inherit_fieldlist['mintimex'])
				|| ($this->mintimey != $this->inherit_fieldlist['mintimey'])
				|| ($this->minstamptext != $this->inherit_fieldlist['minstamptext'])
			) {
				$output .= 'MINTIMEPOS ' . $this->mintimex . ' ' . $this->mintimey . ' ' . $this->minstamptext . PHP_EOL;
			}

			if (($this->maxtimex != $this->inherit_fieldlist['maxtimex'])
				|| ($this->maxtimey != $this->inherit_fieldlist['maxtimey'])
				|| ($this->maxstamptext != $this->inherit_fieldlist['maxstamptext'])
			) {
				$output .= 'MAXTIMEPOS ' . $this->maxtimex . ' ' . $this->maxtimey . ' ' . $this->maxstamptext . PHP_EOL;
			}

			if (($this->titlex != $this->inherit_fieldlist['titlex'])
				|| ($this->titley != $this->inherit_fieldlist['titley'])
			) {
				$output .= 'TITLEPOS ' . $this->titlex . ' ' . $this->titley . PHP_EOL;
			}

			$output .= PHP_EOL;

			foreach ($this->colours as $scalename => $colours) {
				// not all keys will have keypos but if they do, then all three vars should be defined
				if ((isset($this->keyx[$scalename])) && (isset($this->keyy[$scalename])) && (isset($this->keytext[$scalename]))
					&& (($this->keytext[$scalename] != $this->inherit_fieldlist['keytext'])
						|| ($this->keyx[$scalename] != $this->inherit_fieldlist['keyx'])
						|| ($this->keyy[$scalename] != $this->inherit_fieldlist['keyy']))
				) {
					// sometimes a scale exists but without defaults. A proper scale object would sort this out...
					if ($this->keyx[$scalename] == '') {
						$this->keyx[$scalename] = -1;
					}

					if ($this->keyy[$scalename] == '') {
						$this->keyy[$scalename] = -1;
					}

					$output .= 'KEYPOS ' . $scalename . ' ' . $this->keyx[$scalename] . ' ' . $this->keyy[$scalename] . ' ' . $this->keytext[$scalename] . PHP_EOL;
				}

				if ((isset($this->keystyle[$scalename])) && ($this->keystyle[$scalename] != $this->inherit_fieldlist['keystyle']['DEFAULT'])) {
					$extra = '';

					if ((isset($this->keysize[$scalename])) && ($this->keysize[$scalename] != $this->inherit_fieldlist['keysize']['DEFAULT'])) {
						$extra = ' ' . $this->keysize[$scalename];
					}

					$output .= 'KEYSTYLE  ' . $scalename . ' ' . $this->keystyle[$scalename] . $extra . PHP_EOL;
				}

				$locale = localeconv();

				$decimal_point = $locale['decimal_point'];

				foreach ($colours as $k => $colour) {
					if (!isset($colour['special']) || !$colour['special']) {
						$top    = rtrim(rtrim(sprintf('%f', $colour['top']), '0'), $decimal_point);
						$bottom = rtrim(rtrim(sprintf('%f', $colour['bottom']), '0'), $decimal_point);

						if ($bottom > 1000) {
							$bottom = nice_bandwidth($colour['bottom'], $this->kilo);
						}

						if ($top > 1000) {
							$top = nice_bandwidth($colour['top'], $this->kilo);
						}

						$tag = (isset($colour['tag']) ? $colour['tag'] : '');

						if (($colour['red1'] == -1) && ($colour['green1'] == -1) && ($colour['blue1'] == -1)) {
							$output .= sprintf("SCALE %s %-4s %-4s   none   %s\n", $scalename, $bottom, $top, $tag);
						} elseif (!isset($colour['red2'])) {
							$output .= sprintf("SCALE %s %-4s %-4s %3d %3d %3d  %s\n", $scalename, $bottom, $top,
								$colour['red1'], $colour['green1'], $colour['blue1'], $tag
							);
						} else {
							$output .= sprintf("SCALE %s %-4s %-4s %3d %3d %3d   %3d %3d %3d    %s\n", $scalename,
								$bottom, $top,
								$colour['red1'],
								$colour['green1'], $colour['blue1'],
								$colour['red2'], $colour['green2'],
								$colour['blue2'], $tag
							);
						}
					} else {
						if (($colour['red1'] == -1) && ($colour['green1'] == -1) && ($colour['blue1'] == -1)) {
							$output .= sprintf("%sCOLOR none\n", $k);
						} else {
							$output .= sprintf("%sCOLOR %d %d %d\n", $k, $colour['red1'], $colour['green1'], $colour['blue1']);
						}
					}
				}

				$output .= PHP_EOL;
			}

			foreach ($this->hints as $hintname => $hint) {
				$output .= "SET $hintname $hint\n";
			}

			// this doesn't really work right, but let's try anyway
			if ($this->has_includes) {
				$output .= "\n# Included files\n";

				foreach ($this->included_files as $ifile) {
					$output .= "INCLUDE $ifile\n";
				}
			}

			$output .= "\n# End of global section\n\n";

			fwrite($fd, $output);

			## fwrite($fd,$this->nodes['DEFAULT']->WriteConfig());
			## fwrite($fd,$this->links['DEFAULT']->WriteConfig());

			# fwrite($fd, "\n\n# Node definitions:\n");

			foreach (array('template', 'normal') as $which) {
				if ($which == 'template') {
					fwrite($fd, "\n# TEMPLATE-only NODEs:\n");
				}

				if ($which == 'normal') {
					fwrite($fd, "\n# regular NODEs:\n");
				}

				foreach ($this->nodes as $node) {
					if (!preg_match('/^::\s/', $node->name)) {
						if ($node->defined_in == $this->configfile) {
							if ($which == 'template' && $node->x === null) {
								wm_debug("TEMPLATE\n");

								fwrite($fd, $node->WriteConfig());
							}

							if ($which == 'normal' && $node->x !== null) {
								fwrite($fd, $node->WriteConfig());
							}
						}
					}
				}

				if ($which == 'template') {
					fwrite($fd, "\n# TEMPLATE-only LINKs:\n");
				}

				if ($which == 'normal') {
					fwrite($fd, "\n# regular LINKs:\n");
				}

				foreach ($this->links as $link) {
					if (!preg_match('/^::\s/', $link->name)) {
						if ($link->defined_in == $this->configfile) {
							if ($which == 'template' && $link->a === null) {
								fwrite($fd, $link->WriteConfig());
							}

							if ($which == 'normal' && $link->a !== null) {
								fwrite($fd, $link->WriteConfig());
							}
						}
					}
				}
			}

			fwrite($fd, "\n\n# That's All Folks!\n");

			fclose($fd);
		} else {
			wm_warn("Couldn't open config file $filename for writing");
			return (false);
		}

		return (true);
	}

	// pre-allocate colour slots for the colours used by the arrows
	// this way, it's the pretty icons that suffer if there aren't enough colours, and
	// not the actual useful data
	// we skip any gradient scales
	function AllocateScaleColours($im, $refname = 'gdref1') {
		foreach ($this->colours as $scalename => $colours) {
			foreach ($colours as $key => $colour) {
				if ((!isset($this->colours[$scalename][$key]['red2'])) && (!isset($this->colours[$scalename][$key][$refname]))) {
					$r = $colour['red1'];
					$g = $colour['green1'];
					$b = $colour['blue1'];

					wm_debug("AllocateScaleColours: $scalename/$refname $key ($r,$g,$b)");

					$this->colours[$scalename][$key][$refname] = myimagecolorallocate($im, $r, $g, $b);
				}
			}
		}
	}

	function DrawMap($filename = '', $thumbnailfile = '', $thumbnailmax = 250, $withnodes = true, $use_via_overlay = false, $use_rel_overlay=false) {
		wm_debug('Trace: DrawMap()');

		metadump('# start',true);

		$bgimage = null;

		if ($this->configfile != '') {
			$this->cachefile_version = crc32(file_get_contents($this->configfile));
		} else {
			$this->cachefile_version = crc32('........');
		}

		wm_debug('Running Post-Processing Plugins...');

		foreach ($this->postprocessclasses as $post_class) {
			wm_debug("Running $post_class" . '->run()');

			//call_user_func_array(array($post_class, 'run'), array(&$this));

			$this->plugins['post'][$post_class]->run($this);
		}

		wm_debug('Finished Post-Processing Plugins...');

		wm_debug('=====================================');
		wm_debug('Start of Map Drawing');

		// if we're running tests, we force the time to a particular value,
		// so the output can be compared to a reference image more easily
		$testmode = intval($this->get_hint('testmode'));

		if ($testmode == 1) {
			$maptime = 1270813792;
			date_default_timezone_set('UTC');
		} else {
			$maptime = time();
		}

		//$this->datestamp = strftime($this->stamptext, $maptime);
		$this->datestamp = date_format(date_create(date('Y-m-d H:i:s', $maptime)), $this->stamptext);

		// do the basic prep work
		if ($this->background != '') {
			if (is_readable($this->background)) {
				$bgimage=imagecreatefromfile($this->background);

				if (!$bgimage) {
					wm_warn('Failed to open background image.  One possible reason: Is your BACKGROUND really a PNG?');
				} else {
					$this->width=imagesx($bgimage);
					$this->height=imagesy($bgimage);
				}
			} else {
				wm_warn('Your background image file could not be read. Check the filename, and permissions, for ' . $this->background);
			}
		}

		$image=wimagecreatetruecolor($this->width, $this->height);

		# $image = imagecreate($this->width, $this->height);
		if (!$image) {
			wm_warn('Couldn\'t create output image in memory (' . $this->width . 'x' . $this->height . ').');
		} else {
			ImageAlphaBlending($image, true);

			if ($this->get_hint('antialias') == 1) {
				// Turn on anti-aliasing if it exists and it was requested
				if (function_exists('imageantialias')) {
					imageantialias($image,true);
				}
			}

			// by here, we should have a valid image handle

			// save this away, now
			$this->image=$image;

			$this->white=myimagecolorallocate($image, 255, 255, 255);
			$this->black=myimagecolorallocate($image, 0, 0, 0);
			$this->grey=myimagecolorallocate($image, 192, 192, 192);
			$this->selected=myimagecolorallocate($image, 255, 0, 0); // for selections in the editor

			$this->AllocateScaleColours($image);

			// fill with background colour anyway, in case the background image failed to load
			wimagefilledrectangle($image, 0, 0, $this->width, $this->height, $this->colours['DEFAULT']['BG']['gdref1']);

			if ($bgimage) {
				imagecopy($image, $bgimage, 0, 0, 0, 0, $this->width, $this->height);
				imagedestroy ($bgimage);
			}

			// Now it's time to draw a map

			// do the node rendering stuff first, regardless of where they are actually drawn.
			// this is so we can get the size of the nodes, which links will need if they use offsets
			foreach ($this->nodes as $node) {
				// don't try and draw template nodes
				wm_debug('Pre-rendering ' . $node->name . ' to get bounding boxes.');

				if (!is_null($node->x)) {
					$this->nodes[$node->name]->pre_render($image, $this);
				}
			}

			$all_layers = array_keys($this->seen_zlayers);
			sort($all_layers);

			foreach ($all_layers as $z) {
				$z_items = $this->seen_zlayers[$z];

				wm_debug("Drawing layer $z");

				// all the map 'furniture' is fixed at z=1000
				if ($z==1000) {
					foreach ($this->colours as $scalename => $colours) {
						wm_debug("Drawing KEY for $scalename if necessary.");

						if ((isset($this->numscales[$scalename])) && (isset($this->keyx[$scalename])) && ($this->keyx[$scalename] >= 0) && ($this->keyy[$scalename] >= 0)) {
							if ($this->keystyle[$scalename] == 'classic') {
								$this->DrawLegend_Classic($image, $scalename, false);
							}

							if ($this->keystyle[$scalename] == 'horizontal') {
								$this->DrawLegend_Horizontal($image, $scalename, $this->keysize[$scalename]);
							}

							if ($this->keystyle[$scalename] == 'vertical') {
								$this->DrawLegend_Vertical($image, $scalename, $this->keysize[$scalename]);
							}

							if ($this->keystyle[$scalename] == 'inverted') {
								$this->DrawLegend_Vertical($image, $scalename, $this->keysize[$scalename], true);
							}

							if ($this->keystyle[$scalename] == 'tags') {
								$this->DrawLegend_Classic($image, $scalename, true);
							}
						}
					}

					$this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1']);

					if (! is_null($this->min_data_time)) {
						$this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1'], 'MIN');
						$this->DrawTimestamp($image, $this->timefont, $this->colours['DEFAULT']['TIME']['gdref1'], 'MAX');
					}

					$this->DrawTitle($image, $this->titlefont, $this->colours['DEFAULT']['TITLE']['gdref1']);
				}

				if (is_array($z_items)) {
					foreach($z_items as $it) {
						if (strtolower(get_class($it))=='weathermaplink') {
							// only draw LINKs if they have NODES defined (not templates)
							// (also, check if the link still exists - if this is in the editor, it may have been deleted by now)
							if (isset($this->links[$it->name]) && isset($it->a) && isset($it->b)) {
								wm_debug("Drawing LINK ".$it->name);

								$this->links[$it->name]->Draw($image, $this);
							}
						}

						if (strtolower(get_class($it)) == 'weathermapnode') {
							// if (!is_null($it->x)) $it->pre_render($image, $this);
							if ($withnodes) {
								// don't try and draw template nodes
								if ( isset($this->nodes[$it->name]) && !is_null($it->x)) {
									wm_debug('Drawing NODE ' . $it->name);

									$this->nodes[$it->name]->NewDraw($image, $this);

									$ii=0;

									foreach($this->nodes[$it->name]->boundingboxes as $bbox) {
										$areaname = 'NODE:N'. $it->id . ':' . $ii;
										$this->imap->addArea('Rectangle', $areaname, '', $bbox);

										wm_debug('Adding imagemap area');

										$ii++;
									}

									wm_debug("Added $ii bounding boxes too");
								}
							}
						}
					}
				}
			}

			$overlay = myimagecolorallocate($image, 200, 0, 0);

			// for the editor, we can optionally overlay some other stuff
			if ($this->context == 'editor') {
				if ($use_rel_overlay) {
			#		$overlay = myimagecolorallocate($image, 200, 0, 0);

					// first, we can show relatively positioned NODEs
					foreach ($this->nodes as $node) {
						if ($node->relative_to != '') {
							$rel_x = $this->nodes[$node->relative_to]->x;
							$rel_y = $this->nodes[$node->relative_to]->y;

							imagearc($image, $node->x, $node->y, 15, 15, 0, 360, $overlay);
							imagearc($image, $node->x, $node->y, 16, 16, 0, 360, $overlay);
							imageline($image, $node->x, $node->y, $rel_x, $rel_y, $overlay);
						}
					}
				}

				if ($use_via_overlay) {
					// then overlay VIAs, so they can be seen
					foreach($this->links as $link) {
						foreach ($link->vialist as $via) {
							if (isset($via[2])) {
								$x = $this->nodes[$via[2]]->x + $via[0];
								$y = $this->nodes[$via[2]]->y + $via[1];
							} else {
								$x = $via[0];
								$y = $via[1];
							}

							imagearc($image, $x, $y, 10, 10, 0, 360, $overlay);
							imagearc($image, $x, $y, 12, 12, 0, 360, $overlay);
						}
					}
				}
			}

			#$this->myimagestring($image, 3, 200, 100, "Test 1\nLine 2", $overlay,0);

			#	$this->myimagestring($image, 30, 100, 100, "Test 1\nLine 2", $overlay,0);
			#$this->myimagestring($image, 30, 200, 200, "Test 1\nLine 2", $overlay,45);

			// Ready to output the results...

			if ($filename == 'null') {
				// do nothing at all - we just wanted the HTML AREAs for the editor or HTML output
			} else {
				if ($filename == '') {
					imagepng($image);
				} else {
					$result    = false;
					$functions = true;

					if (function_exists('imagejpeg') && preg_match('/\.jpg/i', $filename)) {
						wm_debug("Writing JPEG file to $filename");

						$result = imagejpeg($image, $filename);
					} elseif (function_exists('imagegif') && preg_match('/\.gif/i',$filename)) {
						wm_debug("Writing GIF file to $filename");

						$result = imagegif ($image, $filename);
					} elseif (function_exists('imagepng') && preg_match('/\.png/i',$filename)) {
						wm_debug("Writing PNG file to $filename");

						$result = imagepng($image, $filename);
					} else {
						wm_warn('Failed to write map image. No function existed for the image format you requested. [WMWARN12]');

						$functions = false;
					}

					if (($result == false) && ($functions == true)) {
						if (file_exists($filename)) {
							wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN13]");
						} else {
							wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN14]");
						}
					}
				}
			}

			if ($this->context == 'editor2') {
				$cachefile = $this->cachefolder . '/' . dechex(crc32($this->configfile)) . '_bg.' . $this->cachefile_version . '.png';
				imagepng($image, $cachefile);
				$cacheuri  = $this->cachefolder . '/' . dechex(crc32($this->configfile)) . '_bg.' . $this->cachefile_version . '.png';
				$this->mapcache = $cacheuri;
			}

			if (function_exists('imagecopyresampled')) {
				// if one is specified, and we can, write a thumbnail too
				if ($thumbnailfile != '') {
					$result = false;

					if ($this->width > $this->height) {
						$factor=($thumbnailmax / $this->width);
					} else {
						$factor=($thumbnailmax / $this->height);
					}

					$this->thumb_width  = ceil($this->width * $factor);
					$this->thumb_height = ceil($this->height * $factor);

					$imagethumb = imagecreatetruecolor($this->thumb_width, $this->thumb_height);

					imagecopyresampled($imagethumb, $image, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height,
						$this->width, $this->height
					);

					$result = imagepng($imagethumb, $thumbnailfile);

					imagedestroy($imagethumb);

					if (($result == false)) {
						if (file_exists($filename)) {
							wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN15]");
						} else {
							wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN16]");
						}
					}
				}
			} else {
				wm_warn("Skipping thumbnail creation, since we don't have the necessary function. [WMWARN17]");
			}

			imagedestroy ($image);
		}
	}

	function CleanUp() {
		$all_layers = array_keys($this->seen_zlayers);

		foreach ($all_layers as $z) {
			$this->seen_zlayers[$z] = null;
		}

		foreach ($this->links as $link) {
			$link->owner = null;

			$link->a = null;
			$link->b = null;

			unset($link);
		}

		foreach ($this->nodes as $node) {
			// destroy all the images we created, to prevent memory leaks
			if (isset($node->image)) {
				imagedestroy($node->image);
			}

			$node->owner = null;

			unset($node);
		}

		// Clear up the other random hashes of information
		$this->dsinfocache = null;
		$this->colourtable = null;
		$this->usage_stats = null;
		$this->scales      = null;
	}

	function PreloadMapHTML() {
		wm_debug('Trace: PreloadMapHTML()');

		// find the middle of the map
		$center_x = $this->width / 2;
		$center_y = $this->height / 2;

		// loop through everything. Figure out along the way if it's a node or a link
		$allitems = $this->buildAllItemsList();

		foreach ($allitems as $myobj) {
			$type   = $myobj->my_type();
			$prefix = substr($type, 0, 1);
			$style  = '';

			$dirs = array();
			//print "\n\nConsidering a $type - ".$myobj->name.".\n";
			if ($type == 'LINK') {
				$dirs = array(IN => array(0, 2), OUT => array(1, 3));
			}

			if ($type == 'NODE') {
				$dirs = array(IN => array(0, 1, 2, 3));
			}

			// check to see if any of the relevant things have a value
			$change = '';

			foreach ($dirs as $d=>$parts) {
				$change .= join('', $myobj->overliburl[$d]);
				$change .= $myobj->notestext[$d];
			}

			if ($this->htmlstyle == 'overlib') {
				//print "CHANGE: $change\n";

				// skip all this if it's a template node
				if ($type == 'LINK' && !isset($myobj->a->name)) {
					$change = '';
				}

				if ($type == 'NODE' && !isset($myobj->x)) {
					$change = '';
				}

				if ($change != '') {
					//print "Something to be done.\n";
					if ($type == 'NODE') {
						$mid_x = $myobj->x;
						$mid_y = $myobj->y;
					}

					if ($type == 'LINK') {
						$a_x = $this->nodes[$myobj->a->name]->x;
						$a_y = $this->nodes[$myobj->a->name]->y;

						$b_x = $this->nodes[$myobj->b->name]->x;
						$b_y = $this->nodes[$myobj->b->name]->y;

						$mid_x = ($a_x + $b_x) / 2;
						$mid_y = ($a_y + $b_y) / 2;
					}

					$left      = '';
					$above     = '';

					if ($myobj->overlibwidth != 0) {
						$left = 'WIDTH,' . $myobj->overlibwidth . ',';
						$style .= ($style != '' ? ';':'') . 'width:' . $myobj->overlibwidth . 'px';

						if ($mid_x > $center_x) {
							$left .= 'LEFT,';
						}
					} else {
						$style .= ($style != '' ? ';':'') . 'width:' . read_config_option('weathermap_width') . 'px';
					}

					if ($myobj->overlibheight != 0) {
						$above = 'HEIGHT,' . $myobj->overlibheight . ',';

						$style .= ($style != '' ? ';':'') . 'height:' . $myobj->overlibheight . 'px;';

						if ($mid_y > $center_y) {
							$above .= 'ABOVE,';
						}
					} else {
						$style .= ($style != '' ? ';':'') . 'height:' . read_config_option('weathermap_height') . 'px';
					}

					foreach ($dirs as $dir => $parts) {
						$caption = ($myobj->overlibcaption[$dir] != '' ? $myobj->overlibcaption[$dir] : $myobj->name);
						$caption = $this->ProcessString($caption, $myobj);

						$data_hover  = 'data-hover="<ul class=\'wm_container\'>';

						$n = 0;
						if (cacti_sizeof($myobj->overliburl[$dir]) > 0) {
							// print "ARRAY:".is_array($link->overliburl[$dir])."\n";
							foreach ($myobj->overliburl[$dir] as $url) {
								if ($n > 0) {
									$data_hover .= '<br>';
								}

								$data_hover .= "<li class='wm_child'><img style='{$style}' src='" . $this->ProcessString($url, $myobj) . "'></li>";
								$n++;
							}

							$data_hover .= '</ul>';
						}

						# print "Added $n for $dir\n";
						if (trim($myobj->notestext[$dir]) != '') {
							# put in a linebreak if there was an image AND notes
							if ($n > 0) {
								$data_hover .= '<br>';
							}

							$note = $this->ProcessString($myobj->notestext[$dir], $myobj);
							$note = html_escape($note);
							$note = str_replace("'", "\\&apos;", $note);
							$note = str_replace('"', "&quot;", $note);

							$data_hover .= $note;
						}

						$data_hover .= '" data-caption="' . html_escape($caption) . '"';

						foreach ($parts as $part) {
							$areaname = $type . ':' . $prefix . $myobj->id . ':' . $part;

							$this->imap->setProp('extrahtml', $data_hover, $areaname);
						}
					}
				} // if change
			} // overlib?

			// now look at inforurls
			foreach ($dirs as $dir => $parts) {
				foreach ($parts as $part) {
					$areaname = $type . ':' . $prefix . $myobj->id . ':' . $part;

					if (($this->htmlstyle != 'editor') && ($myobj->infourl[$dir] != '')) {
						$this->imap->setProp('href', $this->ProcessString($myobj->infourl[$dir], $myobj), $areaname);
					}
				}
			}
		}
	}

	function asJS() {
		$js = '';

		$js .= "\t\t\tvar Links   = new Array();\n";
		$js .= "\t\t\tvar LinkIDs = new Array();\n";
		# $js.=$this->defaultlink->asJS();

		foreach ($this->links as $link) {
			$js .= $link->asJS();
		}

		$js .= "\t\t\tvar Nodes   = new Array();\n";
		$js .= "\t\t\tvar NodeIDs = new Array();\n";
		# $js.=$this->defaultnode->asJS();

		foreach ($this->nodes as $node) {
			$js .= $node->asJS();
		}

		return $js;
	}

	function asJSON() {
		$json  = '';
		$json .= "{ \n";
		$json .= "\"map\": {  \n";

		foreach (array_keys($this->inherit_fieldlist)as $fld) {
			$json .= js_escape($fld).": ";
			$json .= js_escape($this->$fld);
			$json .= ",\n";
		}

		$json = rtrim($json,", \n");
		$json .= "\n},\n";

		$json .= "\"nodes\": {\n";
		$json .= $this->defaultnode->asJSON();

		foreach ($this->nodes as $node) {
			$json .= $node->asJSON();
		}

		$json = rtrim($json,", \n");
		$json .= "\n},\n";

		$json .= "\"links\": {\n";
		$json .= $this->defaultlink->asJSON();

		foreach ($this->links as $link) {
			$json .= $link->asJSON();
		}

		$json  = rtrim($json,", \n");
		$json .= "\n},\n";

		$json .= "'imap': [\n";
		$json .= $this->imap->subJSON("NODE:");

		// should check if there WERE nodes...
		$json .= ",\n";
		$json .= $this->imap->subJSON("LINK:");
		$json .= "\n]\n";
		$json .= "\n";

		$json .= ", 'valid': 1}\n";

		return($json);
	}

	// This method MUST run *after* DrawMap. It relies on DrawMap to call the map-drawing bits
	// which will populate the ImageMap with regions.
	//
	// imagemapname is a parameter, so we can stack up several maps in the Cacti plugin with their own imagemaps
	function MakeHTML($imagemapname = 'weathermap_imap') {
		wm_debug('Trace: MakeHTML()');

		// PreloadMapHTML fills in the ImageMap info, ready for the HTML to be created.
		$this->PreloadMapHTML();

		$html = '<div class="weathermapimage">' . PHP_EOL;

		if ($this->imageuri != '') {
			$html.=sprintf(
				'<center><img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
				$this->imageuri,
				$this->width,
				$this->height,
				$imagemapname
			);

			//$html .=  'alt="network weathermap" ';
			$html .= '/></center>' . PHP_EOL;
		} else {
			$html.=sprintf(
				'<center><img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
				$this->imagefile,
				$this->width,
				$this->height,
				$imagemapname
			);

			$html .= '/></center>' . PHP_EOL;
		}

		$html .= '</div>' . PHP_EOL;
		$html .= $this->SortedImagemap($imagemapname);

		return ($html);
	}

	function SortedImagemap($imagemapname) {
		$html = '<map name="' . $imagemapname . '" id="' . $imagemapname . '">' . PHP_EOL;

		# $html.=$this->imap->subHTML("NODE:",true);
		# $html.=$this->imap->subHTML("LINK:",true);

		$all_layers = array_keys($this->seen_zlayers);
		rsort($all_layers);

		wm_debug("Starting to dump imagemap in reverse Z-order...");

		// this is not precisely efficient, but it'll get us going
		// XXX - get Imagemap to store Z order, or map items to store the imagemap
		foreach ($all_layers as $z) {
			wm_debug("Writing HTML for layer $z");

			$z_items = $this->seen_zlayers[$z];

			if (is_array($z_items)) {
				wm_debug("   Found things for layer $z");

				// at z=1000, the legends and timestamps live
				if ($z == 1000) {
					wm_debug('     Builtins fit here.');

   					$html .= $this->imap->subHTML('LEGEND:', true, ($this->context != 'editor'));
					$html .= $this->imap->subHTML('TIMESTAMP', true, ($this->context != 'editor'));
				}

				foreach($z_items as $it) {
					# print "     " . $it->name . "\n";
					if ($it->name != 'DEFAULT' && $it->name != ':: DEFAULT ::') {
						$name = '';

						if (strtolower(get_class($it))=='weathermaplink') {
							$name = 'LINK:L';
						}

						if (strtolower(get_class($it))=='weathermapnode') {
							$name = 'NODE:N';
						}

						$name .= $it->id . ':';

						wm_debug("      Writing $name from imagemap");

						$html .= $this->imap->subHTML($name,true,($this->context != 'editor'));
					}
				}
			}
		}

		$html .= "\t\t\t\t" . '</map>' . PHP_EOL;

		return($html);
	}

	// update any editor cache files.
	// if the config file is newer than the cache files, or $agelimit seconds have passed,
	// then write new stuff, otherwise just return.
	// ALWAYS deletes files in the cache folder older than $agelimit, also!
	function CacheUpdate($agelimit=600) {
		global $weathermap_lazycounter;

		$cachefolder = $this->cachefolder;
		$configchanged = filemtime($this->configfile );

		// make a unique, but safe, prefix for all cachefiles related to this map config
		// we use CRC32 because it makes for a shorter filename, and collisions aren't the end of the world.
		$cacheprefix = dechex(crc32($this->configfile));

		wm_debug("Comparing files in $cachefolder starting with $cacheprefix, with date of $configchanged");

		$dh=opendir($cachefolder);

		if ($dh) {
			while ($file=readdir($dh)) {
				$realfile = $cachefolder . '/' . $file;

				if (is_file($realfile) && ( preg_match('/^'.$cacheprefix.'/',$file))) {
					wm_debug("$realfile");

					if ( (filemtime($realfile) < $configchanged) || ((time() - filemtime($realfile)) > $agelimit) ) {
						wm_debug("Cache: deleting $realfile");

						unlink($realfile);
					}
				}
			}

			closedir ($dh);

			foreach ($this->nodes as $node) {
				if (isset($node->image)) {
					$nodefile = $cacheprefix . '_' . dechex(crc32($node->name)) . '.png';
					$this->nodes[$node->name]->cachefile = $nodefile;
					imagepng($node->image, $cachefolder . '/' . $nodefile);
				}
			}

			foreach ($this->keyimage as $key=>$image) {
				$scalefile = $cacheprefix . '_scale_' . dechex(crc32($key)) . '.png';
				$this->keycache[$key] = $scalefile;
				imagepng($image, $cachefolder . '/' . $scalefile);
			}

			$json = "";
			$fd = fopen($cachefolder . '/' . $cacheprefix . '_map.json', 'w');

			foreach (array_keys($this->inherit_fieldlist)as $fld) {
				$json .= js_escape($fld) . ': ';
				$json .= js_escape($this->$fld);
				$json .= ",\n";
			}

			$json = rtrim($json,", \n");
			fputs($fd,$json);
			fclose($fd);

			$json = "";
			$fd = fopen($cachefolder . '/' . $cacheprefix . '_tree.json', 'w');
			$id = 10;	// first ID for user-supplied thing

			$json .= "{ id: 1, text: 'SCALEs'\n, children: [\n";

			foreach ($this->colours as $scalename=>$colours) {
				$json .= '{ id: ' . $id++ . ", text:" . js_escape($scalename) . ", leaf: true }, \n";
			}

			$json = rtrim($json,", \n");
			$json .= "]},\n";

			$json .= "{ id: 2, text: 'FONTs',\n children: [\n";

			foreach ($this->fonts as $fontnumber => $font) {
				if ($font->type == 'truetype') {
					$json .= sprintf("{ id: %d, text: %s, leaf: true}, \n", $id++, js_escape("Font $fontnumber (TT)"));
				}

				if ($font->type == 'gd') {
					$json .= sprintf("{ id: %d, text: %s, leaf: true}, \n", $id++, js_escape("Font $fontnumber (GD)"));
				}
			}

			$json = rtrim($json,", \n");
			$json .= "]},\n";

			$json .= "{ id: 3, text: 'NODEs',\n children: [\n";
			$json .= "{ id: ". $id++ . ", text: 'DEFAULT', children: [\n";

			$weathemap_lazycounter = $id;

			// pass the list of subordinate nodes to the recursive tree function
			$json .= $this->MakeTemplateTree( $this->node_template_tree );

			$id = $weathermap_lazycounter;

			$json = rtrim($json,", \n");
			$json .= "]} ]},\n";

			$json .= "{ id: 4, text: 'LINKs',\n children: [\n";
			$json .= "{ id: ". $id++ . ", text: 'DEFAULT', children: [\n";

			$weathemap_lazycounter = $id;

			$json .= $this->MakeTemplateTree( $this->link_template_tree );

			$id = $weathermap_lazycounter;

			$json = rtrim($json,", \n");
			$json .= "]} ]}\n";

			fputs($fd,"[". $json . "]");
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_nodes.json', 'w');

			$json = '';

//			$json = $this->defaultnode->asJSON(true);

			foreach ($this->nodes as $node) {
				$json .= $node->asJSON(true);
			}

			$json = rtrim($json,", \n");

			fputs($fd,$json);
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_nodes_lite.json', 'w');

			$json = "";

//			$json = $this->defaultnode->asJSON(false);

			foreach ($this->nodes as $node) {
				$json .= $node->asJSON(false);
			}

			$json = rtrim($json,", \n");

			fputs($fd,$json);
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_links.json', 'w');

			$json = "";

//			$json = $this->defaultlink->asJSON(true);

			foreach ($this->links as $link) {
				$json .= $link->asJSON(true);
			}

			$json = rtrim($json,", \n");

			fputs($fd,$json);
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_links_lite.json', 'w');

			$json = "";

//			$json = $this->defaultlink->asJSON(false);

			foreach ($this->links as $link) {
				$json .= $link->asJSON(false);
			}

			$json = rtrim($json,", \n");

			fputs($fd,$json);
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_imaphtml.json', 'w');

			$json = $this->imap->subHTML('LINK:');

			fputs($fd,$json);
			fclose($fd);

			$fd = fopen($cachefolder . '/' . $cacheprefix . '_imap.json', 'w');

			$json = '';

			$nodejson = trim($this->imap->subJSON('NODE:'));

			if ($nodejson != '') {
				$json .= $nodejson;

				// should check if there WERE nodes...
				$json .= ",\n";
			}

			$json .= $this->imap->subJSON('LINK:');

			fputs($fd,$json);
			fclose($fd);
		} else {
			wm_debug("Couldn't read cache folder.");
		}
	}

	function MakeTemplateTree(&$tree_list, $startpoint = 'DEFAULT') {
		global $weathermap_lazycounter;

		$output = "";
		foreach ($tree_list[$startpoint] as $subnode) {
			$output .= '{ id: ' . $weathermap_lazycounter++ . ', text: ' . js_escape($subnode);

			if ( isset($tree_list[$subnode])) {
				$output .= ", children: [ \n";
				$output .= $this->MakeTemplateTree($tree_list, $subnode);
				$output = rtrim($output,", \n");
				$output .= "] \n";
			} else {
				$output .= ", leaf: true ";
			}

			$output .= "}, \n";
		}

		return($output);
	}

	function DumpStats($filename = '') {
		$report = "Feature Statistics:\n\n";

		foreach ($this->usage_stats as $key=>$val) {
			$report .= sprintf("%70s => %d\n",$key,$val);
		}

		if ($filename == '') {
			print $report;
		}
	}

	function SeedCoverage() {
		global $WM_config_keywords2;

		foreach (array_keys($WM_config_keywords2) as $context) {
			foreach (array_keys($WM_config_keywords2[$context]) as $keyword) {
				foreach ($WM_config_keywords2[$context][$keyword] as $patternarray) {
					$key = sprintf("%s:%s:%s",$context, $keyword ,$patternarray[1]);
					$this->coverage[$key] = 0;
				}
			}
		}
	}

	function LoadCoverage($file) {
		// ToDo - Why the return?
		return 0;

		$i = 0;

		$fd = fopen($file, 'r');

		if (is_resource($fd)) {
			while(!feof($fd)) {
				$line = fgets($fd,1024);
				$line = trim($line);

				list($val,$key) = explode("\t",$line);

				if ($key != '') {
					$this->coverage[$key] = $val;
				}

				if ($val > 0) {
					$i++;
				}
			}

			fclose($fd);
		}
	}

	function SaveCoverage($file) {
		$i = 0;

		$fd = fopen($file, 'w+');

		foreach ($this->coverage as $key=>$val) {
			fputs($fd, "$val\t$key\n");

			if ($val > 0) {
				$i++;
			}
		}

		fclose($fd);
	}
}

