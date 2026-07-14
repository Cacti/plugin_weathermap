<?php

declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2022-2026 The Cacti Group, Inc.                           |
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

/**
 * new version of config_keywords
 * array of contexts, contains an array of keywords, contains a (short) list of regexps as now
 * this way, we don't scan the whole table, and we call preg_match a WHOLE lot less
 * there will be more lines in the array, but we'll be checking less of them
 */
$WM_config_keywords2 = [
	'GLOBAL' => [
		'FONTDEFINE' => [
			['GLOBAL', "/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s+(\d+)\s*$/i", 'ReadConfig_Handle_FONTDEFINE'],
			['GLOBAL', "/^\s*FONTDEFINE\s+(\d+)\s+(\S+)\s*$/i", 'ReadConfig_Handle_FONTDEFINE'],
		],
		'KEYOUTLINECOLOR' => [
			[
				'GLOBAL',
				'/^KEYOUTLINECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
			[
				'GLOBAL',
				'/^KEYOUTLINECOLOR\s+(none)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'KEYTEXTCOLOR' => [
			[
				'GLOBAL',
				'/^KEYTEXTCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'TITLECOLOR' => [
			[
				'GLOBAL',
				'/^TITLECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'TIMECOLOR' => [
			[
				'GLOBAL',
				'/^TIMECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'KEYBGCOLOR' => [
			[
				'GLOBAL',
				'/^KEYBGCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
			[
				'GLOBAL',
				'/^KEYBGCOLOR\s+(none)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'BGCOLOR' => [
			[
				'GLOBAL',
				'/^BGCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_GLOBALCOLOR'
			],
		],
		'SET' => [
			[
				'GLOBAL',
				'SET',
				'ReadConfig_Handle_SET'
			],
		],
		'HTMLSTYLESHEET' => [
			[
				'GLOBAL',
				'/^HTMLSTYLESHEET\s+(.*)\s*$/i',
				['htmlstylesheet' => 1]
			],
		],
		'HTMLOUTPUTFILE' => [
			[
				'GLOBAL',
				'/^HTMLOUTPUTFILE\s+(.*)\s*$/i',
				['htmloutputfile' => 1]
			],
		],
		'BACKGROUND' => [
			[
				'GLOBAL',
				'/^BACKGROUND\s+(.*)\s*$/i',
				['background' => 1]
			],
		],
		'IMAGEOUTPUTFILE' => [
			[
				'GLOBAL',
				'/^IMAGEOUTPUTFILE\s+(.*)\s*$/i',
				['imageoutputfile' => 1]
			],
		],
		'DATAOUTPUTFILE' => [
			[
				'GLOBAL',
				'/^DATAOUTPUTFILE\s+(.*)\s*$/i',
				['dataoutputfile' => 1]
			],
		],
		'IMAGEURI' => [
			[
				'GLOBAL',
				'/^IMAGEURI\s+(.*)\s*$/i',
				['imageuri' => 1]
			],
		],
		'TITLE' => [
			[
				'GLOBAL',
				'/^TITLE\s+(.*)\s*$/i',
				['title' => 1]
			],
		],
		'HTMLSTYLE' => [
			[
				'GLOBAL',
				'/^HTMLSTYLE\s+(static|overlib)\s*$/i',
				['htmlstyle' => 1]
			],
		],
		'KILO' => [
			[
				'GLOBAL',
				'/^KILO\s+(\d+)\s*$/i',
				['kilo' => 1]
			],
		],
		'KEYFONT' => [
			[
				'GLOBAL',
				'/^KEYFONT\s+(\d+)\s*$/i',
				['keyfont' => 1]
			],
		],
		'TITLEFONT' => [
			[
				'GLOBAL',
				'/^TITLEFONT\s+(\d+)\s*$/i',
				['titlefont' => 1]
			],
		],
		'TIMEFONT' => [
			[
				'GLOBAL',
				'/^TIMEFONT\s+(\d+)\s*$/i',
				['timefont' => 1]
			],
		],
		'WIDTH' => [
			[
				'GLOBAL',
				"/^WIDTH\s+(\d+)\s*$/i",
				['width' => 1]
			],
		],
		'HEIGHT' => [
			[
				'(GLOBAL)',
				"/^HEIGHT\s+(\d+)\s*$/i",
				['height' => 1]
			],
		],
		'TITLEPOS' => [
			[
				'GLOBAL',
				'/^TITLEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',
				[
					'titlex' => 1,
					'titley' => 2
				]
			],
			[
				'GLOBAL',
				'/^TITLEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',
				[
					'titlex' => 1,
					'titley' => 2,
					'title'  => 3
				]
			],
		],
		'TIMEPOS' => [
			[
				'GLOBAL',
				'/^TIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',
				[
					'timex' => 1,
					'timey' => 2
				]
			],
			[
				'GLOBAL',
				'/^TIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',
				[
					'timex'     => 1,
					'timey'     => 2,
					'stamptext' => 3
				]
			],
		],
		'MINTIMEPOS' => [
			[
				'GLOBAL',
				'/^MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',
				[
					'mintimex' => 1,
					'mintimey' => 2
				]
			],
			[
				'GLOBAL',
				'/^MINTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',
				[
					'mintimex'     => 1,
					'mintimey'     => 2,
					'minstamptext' => 3
				]
			],
		],
		'MAXTIMEPOS' => [
			[
				'GLOBAL',
				'/^MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s*$/i',
				[
					'maxtimex' => 1,
					'maxtimey' => 2
				]
			],
			[
				'GLOBAL',
				'/^MAXTIMEPOS\s+(-?\d+)\s+(-?\d+)\s+(.*)\s*$/i',
				[
					'maxtimex'     => 1,
					'maxtimey'     => 2,
					'maxstamptext' => 3
				]
			],
		],
	], // end of global
	'NODE' => [
		'TARGET' => [
			[
				'NODE',
				'TARGET',
				'ReadConfig_Handle_TARGET'
			],
		],
		'SET' => [
			[
				'NODE',
				'SET',
				'ReadConfig_Handle_SET'
			],
		],
		'AICONOUTLINECOLOR' => [
			[
				'NODE',
				'/^AICONOUTLINECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'NODE',
				'/^AICONOUTLINECOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'AICONFILLCOLOR' => [
			[
				'NODE',
				'/^AICONFILLCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'NODE',
				'/^AICONFILLCOLOR\s+(copy)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'LABELOUTLINECOLOR' => [
			[
				'NODE',
				'/^LABELOUTLINECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'NODE',
				'/^LABELOUTLINECOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'LABELBGCOLOR' => [
			[
				'NODE',
				'/^LABELBGCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'NODE',
				'/^LABELBGCOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'LABELFONTCOLOR' => [
			[
				'NODE',
				'/^LABELFONTCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'NODE',
				'/^LABELFONTCOLOR\s+(contrast)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'LABELFONTSHADOWCOLOR' => [
			[
				'NODE',
				'/^LABELFONTSHADOWCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'NOTES' => [
			[
				'NODE',
				'/^NOTES\s+(.*)\s*$/i',
				[
					'notestext[IN]'  => 1,
					'notestext[OUT]' => 1
				]
			],
		],
		'MAXVALUE' => [
			[
				'NODE',
				'/^(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 3
				]
			],
			[
				'NODE',
				'/^(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 2
				]
			],
		],
		'ORIGIN' => [
			['NODE',
				"/^ORIGIN\s+(C|NE|SE|NW|SW|N|S|E|W)/i",
				['position_origin' => 1]
			]
		],
		'POSITION' => [
			[
				'NODE',
				"/^POSITION\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i",
				[
					'x' => 1,
					'y' => 2
				]
			],
			[
				'NODE',
				"/^POSITION\s+(\S+)\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i",
				[
					'x'                 => 2,
					'y'                 => 3,
					'original_x'        => 2,
					'original_y'        => 3,
					'relative_to'       => 1,
					'relative_resolved' => false
				]
			],
			[
				'NODE',
				"/^POSITION\s+(\S+)\s+([-+]?\d+)r(\d+)\s*$/i",
				[
					'x'                 => 2,
					'y'                 => 3,
					'original_x'        => 2,
					'original_y'        => 3,
					'relative_to'       => 1,
					'polar'             => true,
					'relative_resolved' => false
				]
			],
		],
		'INFOURL' => [
			[
				'NODE',
				'/^INFOURL\s+(.*)\s*$/i',
				[
					'infourl[IN]'  => 1,
					'infourl[OUT]' => 1
				]
			],
		],
		'OVERLIBCAPTION' => [
			[
				'NODE',
				'/^OVERLIBCAPTION\s+(.*)\s*$/i',
				[
					'overlibcaption[IN]'  => 1,
					'overlibcaption[OUT]' => 1
				]
			],
		],
		'ZORDER' => [
			[
				'NODE',
				"/^ZORDER\s+([-+]?\d+)\s*$/i",
				['zorder' => 1]
			],
		],
		'OVERLIBHEIGHT' => [
			[
				'NODE',
				"/^OVERLIBHEIGHT\s+(\d+)\s*$/i",
				['overlibheight' => 1]
			],
		],
		'OVERLIBWIDTH' => [
			[
				'NODE',
				"/^OVERLIBWIDTH\s+(\d+)\s*$/i",
				['overlibwidth' => 1]
			],
		],
		'LABELFONT' => [
			[
				'NODE',
				'/^LABELFONT\s+(\d+)\s*$/i',
				['labelfont' => 1]
			],
		],
		'LABELANGLE' => [
			[
				'NODE',
				'/^LABELANGLE\s+(0|90|180|270)\s*$/i',
				['labelangle' => 1]
			],
		],
		'ICON' => [
			[
				'NODE',
				'/^ICON\s+(\S+)\s*$/i',
				[
					'iconfile'   => 1,
					'iconscalew' => '#0',
					'iconscaleh' => '#0'
				]
			],
			[
				'NODE',
				'/^ICON\s+(\S+)\s*$/i',
				['iconfile' => 1]
			],
			[
				'NODE',
				'/^ICON\s+(\d+)\s+(\d+)\s+(inpie|outpie|box|rbox|round|gauge|nink)\s*$/i',
				[
					'iconfile'   => 3,
					'iconscalew' => 1,
					'iconscaleh' => 2
				]
			],
			[
				'NODE',
				'/^ICON\s+(\d+)\s+(\d+)\s+(\S+)\s*$/i',
				[
					'iconfile'   => 3,
					'iconscalew' => 1,
					'iconscaleh' => 2
				]
			],
		],
		'LABEL' => [
			[
				'NODE',
				"/^LABEL\s*$/i",
				['label' => '']
			], // special case for blank labels
			[
				'NODE',
				"/^LABEL\s+(.*)\s*$/i",
				['label' => 1]
			],
		],
		'LABELOFFSET' => [
			[
				'NODE',
				'/^LABELOFFSET\s+([-+]?\d+)\s+([-+]?\d+)\s*$/i',
				[
					'labeloffsetx' => 1,
					'labeloffsety' => 2
				]
			],
			[
				'NODE',
				'/^LABELOFFSET\s+(C|NE|SE|NW|SW|N|S|E|W)\s*$/i',
				['labeloffset' => 1]
			],
			[
				'NODE',
				'/^LABELOFFSET\s+((C|NE|SE|NW|SW|N|S|E|W)\d+)\s*$/i',
				['labeloffset' => 1]
			],
			[
				'NODE',
				'/^LABELOFFSET\s+(-?\d+r\d+)\s*$/i',
				['labeloffset' => 1]
			],
		],
		'USESCALE' => [
			[
				'NODE',
				"/^(USESCALE)\s+([A-Za-z][A-Za-z0-9_]*)(\s+(in|out))?(\s+(absolute|percent))?\s*$/i",
				'ReadConfig_Handle_NODE_USESCALE'
			],
		],
		'USEICONSCALE' => [
			[
				'NODE',
				"/^(USEICONSCALE)\s+([A-Za-z][A-Za-z0-9_]*)(\s+(in|out))?(\s+(absolute|percent))?\s*$/i",
				'ReadConfig_Handle_NODE_USESCALE'
			],
		],
		'OVERLIBGRAPH' => [
			[
				'NODE',
				"/^OVERLIBGRAPH\s+(.+)$/i",
				'ReadConfig_Handle_OVERLIB'
			]
		],
	],
	'LINK' => [
		'TARGET' => [
			[
				'LINK',
				'TARGET',
				'ReadConfig_Handle_TARGET'
			],
		],
		'SET' => [
			[
				'LINK',
				'SET',
				'ReadConfig_Handle_SET'
			],
		],
		'NODES' => [
			[
				'LINK',
				'NODES',
				'ReadConfig_Handle_NODES'
			],
		],
		'VIA' => [
			[
				'LINK',
				'VIA',
				'ReadConfig_Handle_VIA'
			],
		],
		'COMMENTFONTCOLOR' => [
			[
				'LINK',
				'/^COMMENTFONTCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'LINK',
				'/^COMMENTFONTCOLOR\s+(contrast)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'OUTLINECOLOR' => [
			[
				'LINK',
				'/^OUTLINECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'LINK',
				'/^OUTLINECOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'BWOUTLINECOLOR' => [
			[
				'LINK',
				'/^BWOUTLINECOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'LINK',
				'/^BWOUTLINECOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'BWBOXCOLOR' => [
			[
				'LINK',
				'/^BWBOXCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
			[
				'LINK',
				'/^BWBOXCOLOR\s+(none)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'BWFONTCOLOR' => [
			[
				'LINK',
				'/^BWFONTCOLOR\s+(\d+)\s+(\d+)\s+(\d+)$/',
				'ReadConfig_Handle_COLOR'
			],
		],
		'NOTES' => [
			[
				'LINK',
				'/^NOTES\s+(.*)\s*$/i',
				[
					'notestext[IN]'  => 1,
					'notestext[OUT]' => 1
				]
			],
		],
		'MAXVALUE' => [
			[
				'LINK',
				'/^(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 3
				]
			],
			[
				'LINK',
				'/^(MAXVALUE)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 2
				]
			],
		],
		'WIDTH' => [
			[
				'LINK',
				"/^WIDTH\s+(\d+)\s*$/i",
				['width' => 1]
			],
			[
				'LINK',
				"/^WIDTH\s+(\d+\.\d+)\s*$/i",
				['width' => 1]
			],
		],
		'SPLITPOS' => [
			[
				'LINK',
				'/^SPLITPOS\s+(\d+)\s*$/i',
				['splitpos' => 1]
			],
		],
		'BWLABELPOS' => [
			[
				'LINK',
				'/^BWLABELPOS\s+(\d+)\s(\d+)\s*$/i',
				[
					'labeloffset_in'  => 1,
					'labeloffset_out' => 2
				]
			],
		],
		'COMMENTPOS' => [
			[
				'LINK',
				'/^COMMENTPOS\s+(\d+)\s(\d+)\s*$/i',
				[
					'commentoffset_in'  => 1,
					'commentoffset_out' => 2
				]
			],
		],
		'DUPLEX' => [
			[
				'LINK',
				'/^DUPLEX\s+(full|half)\s*$/i',
				['duplex' => 1]
			],
		],
		'BWSTYLE' => [
			[
				'LINK',
				'/^BWSTYLE\s+(classic|angled)\s*$/i',
				['labelboxstyle' => 1]
			],
		],
		'LINKSTYLE' => [
			[
				'LINK',
				'/^LINKSTYLE\s+(twoway|oneway)\s*$/i',
				['linkstyle' => 1]
			],
		],
		'COMMENTSTYLE' => [
			[
				'LINK',
				'/^COMMENTSTYLE\s+(edge|center)\s*$/i',
				['commentstyle' => 1]
			],
		],
		'ARROWSTYLE' => [
			[
				'LINK',
				'/^ARROWSTYLE\s+(classic|compact)\s*$/i',
				['arrowstyle' => 1]
			],
		],
		'VIASTYLE' => [
			[
				'LINK',
				'/^VIASTYLE\s+(curved|angled)\s*$/i',
				['viastyle' => 1]
			],
		],
		'INCOMMENT' => [
			[
				'LINK',
				'/^INCOMMENT\s+(.*)\s*$/i',
				['comments[IN]' => 1]
			],
		],
		'OUTCOMMENT' => [
			[
				'LINK',
				'/^OUTCOMMENT\s+(.*)\s*$/i',
				['comments[OUT]' => 1]
			],
		],
		'OVERLIBGRAPH' => [
			[
				'LINK',
				"/^OVERLIBGRAPH\s+(.+)$/i",
				'ReadConfig_Handle_OVERLIB'
			]
		],
		'INOVERLIBGRAPH' => [
			[
				'LINK',
				"/^INOVERLIBGRAPH\s+(.+)$/i",
				'ReadConfig_Handle_OVERLIB'
			]
		],
		'OUTOVERLIBGRAPH' => [
			[
				'LINK',
				"/^OUTOVERLIBGRAPH\s+(.+)$/i",
				'ReadConfig_Handle_OVERLIB'
			]
		],
		'USESCALE' => [
			[
				'LINK',
				'/^USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s*$/i',
				['usescale' => 1]
			],
			[
				'LINK',
				'/^USESCALE\s+([A-Za-z][A-Za-z0-9_]*)\s+(absolute|percent)\s*$/i',
				[
					'usescale'  => 1,
					'scaletype' => 2
				]
			],
		],
		'BWFONT' => [
			[
				'LINK',
				'/^BWFONT\s+(\d+)\s*$/i',
				['bwfont' => 1]
			],
		],
		'COMMENTFONT' => [
			[
				'LINK',
				'/^COMMENTFONT\s+(\d+)\s*$/i',
				['commentfont' => 1]
			],
		],
		'BANDWIDTH' => [
			[
				'LINK',
				'/^(BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 3
				]
			],
			[
				'LINK',
				'/^(BANDWIDTH)\s+(\d+\.?\d*[KMGT]?)\s*$/i',
				[
					'max_bandwidth_in_cfg'  => 2,
					'max_bandwidth_out_cfg' => 2
				]
			],
		],
		'OUTBWFORMAT' => [
			[
				'LINK',
				'/^OUTBWFORMAT\s+(.*)\s*$/i',
				[
					'bwlabelformats[OUT]' => 1,
					'labelstyle'          => '--'
				]
			],
		],
		'INBWFORMAT' => [
			[
				'LINK',
				'/^INBWFORMAT\s+(.*)\s*$/i',
				[
					'bwlabelformats[IN]' => 1,
					'labelstyle'         => '--'
				]
			],
		],
		'INNOTES' => [
			[
				'LINK',
				'/^INNOTES\s+(.*)\s*$/i',
				['notestext[IN]' => 1]
			],
		],
		'OUTNOTES' => [
			[
				'LINK',
				'/^OUTNOTES\s+(.*)\s*$/i',
				['notestext[OUT]' => 1]
			],
		],
		'INFOURL' => [
			[
				'LINK',
				'/^INFOURL\s+(.*)\s*$/i',
				[
					'infourl[IN]'  => 1,
					'infourl[OUT]' => 1
				]
			],
		],
		'ININFOURL' => [
			[
				'LINK',
				'/^ININFOURL\s+(.*)\s*$/i',
				['infourl[IN]' => 1]
			],
		],
		'OUTINFOURL' => [
			[
				'LINK',
				'/^OUTINFOURL\s+(.*)\s*$/i',
				['infourl[OUT]' => 1]
			],
		],
		'OVERLIBCAPTION' => [
			[
				'LINK',
				'/^OVERLIBCAPTION\s+(.*)\s*$/i',
				[
					'overlibcaption[IN]'  => 1,
					'overlibcaption[OUT]' => 1
				]
			],
		],
		'INOVERLIBCAPTION' => [
			[
				'LINK',
				'/^INOVERLIBCAPTION\s+(.*)\s*$/i',
				['overlibcaption[IN]' => 1]
			],
		],
		'OUTOVERLIBCAPTION' => [
			[
				'LINK',
				'/^OUTOVERLIBCAPTION\s+(.*)\s*$/i',
				['overlibcaption[OUT]' => 1]
			],
		],
		'ZORDER' => [
			[
				'LINK',
				"/^ZORDER\s+([-+]?\d+)\s*$/i",
				['zorder' => 1]
			],
		],
		'OVERLIBWIDTH' => [
			[
				'LINK',
				"/^OVERLIBWIDTH\s+(\d+)\s*$/i",
				['overlibwidth' => 1]
			],
		],
		'OVERLIBHEIGHT' => [
			[
				'LINK',
				"/^OVERLIBHEIGHT\s+(\d+)\s*$/i",
				['overlibheight' => 1]
			],
		],
	] // end of link
];
