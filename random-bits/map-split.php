<?php
/*
 +-------------------------------------------------------------------------+
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

	require_once 'Weathermap.class.php';

	// EDIT THESE!
	// Which file to read in (the big map)
	$input_mapfile = "configs/09-test.conf";
	// how big do you want your new maps to be?
	$desired_width = 640;
	$desired_height = 480;

	$map = new WeatherMap;
	$map->ReadConfig($input_mapfile);

	print "Size of source is ".$map->width."x".$map->height."\n";

	$rows = intval($map->height/$desired_height)+1;
	$cols = intval($map->width/$desired_width)+1;
	$num = $rows * $cols;


	if($num == 1)
	{
		print "This map is already within your constraints.\n";
	}
	else
	{
		print "We'll need to make $num ($cols x $rows) smaller maps\n";
		for($row=0;$row < $rows; $row++)
		{
			for($col=0;$col<$cols; $col++)
			{
				print "=====================================\nMaking the submap $col,$row\n";
				$min_x = $col*$desired_width;
				$min_y = $row*$desired_height;
				$max_x = ($col+1)*$desired_width;
				$max_y = ($row+1)*$desired_height;
				print "We'll read the map, and throw out everything not inside ($min_x,$min_y)->($max_x,$max_y)\n";

				$map = new WeatherMap;
				$map->ReadConfig($input_mapfile);

				foreach ($map->nodes as $node)
				{
					$target = $node->name;
					if( ($node->x < $min_x) || ($node->x >= $max_x) ||
						($node->y < $min_y) || ($node->y >= $max_y) )
					{

						print "$target falls outside of this map. Deleting it and links that use it.\n";

						foreach ($map->links as $link)
						{
							if( ($target == $link->a->name) || ($target == $link->b->name) )
							{
								print "link $link->name uses it. Deleted.\n";
								unset($map->links[$link->name]);
							}
						}
						unset($map->nodes[$target]);
					}
					else
					{
						print "$target is OK, but will be moved for the new map from ".$node->x.",".$node->y." to ";
						$x = $node->x;
						$y = $node->y;

						$x = $node->x  - $min_x;
						$y = $node->y  - $min_y;
						$map->nodes[$target]->x = $x;
						$map->nodes[$target]->y = $y;
						print "$x,$y\n";
					}
				}
				$output_mapfile = $input_mapfile."-".$row."-".$col.".conf";
				$map->width = $desired_width;
				$map->height = $desired_height;
				$map->background="";
				$map->WriteConfig($output_mapfile);
			}
		}

	}

