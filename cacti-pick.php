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

// ******************************************
// sensible defaults
$mapdir       = 'configs';
$cacti_base   = '../../';
$cacti_url    = '/';

$config['base_url'] = $cacti_url;

include(__DIR__ . '/../../include/global.php');

if (file_exists(__DIR__ . '/editor-config.php')) {
	include_once(__DIR__ . '/editor-config.php');
}

$config['base_url'] = (isset($config['url_path']) ? $config['url_path'] : $cacti_url);

// ******************************************
if (isset($_SESSION['cacti']['weathermap']['last_used_host_id'][0])) {
	print "<b>Last Host Selected:</b><br>";

	$last['id'] = array_reverse($_SESSION['cacti']['weathermap']['last_used_host_id']);
	$last['name'] = array_reverse($_SESSION['cacti']['weathermap']['last_used_host_name']);

	foreach ($last['id'] as $key => $id) {
		list($name) = explode(' - ', $last['name'][$key], 2);

		print "<a href=cacti-pick.php?host_id=" . $id . "&command=link_step1&overlib=1&aggregate=0>[" . html_escape($name) . "]</a><br>";
	}
}

function js_escape($str) {
	$str = str_replace('\\', '\\\\', $str);
	$str = str_replace("'", "\\\'", $str);

	$str = "'".$str."'";

	return($str);
}

if (isset($_REQUEST['command']) && $_REQUEST["command"]=='link_step2') {
	$dataid = intval($_REQUEST['dataid']);

	$line = db_fetch_row_prepared("SELECT gti.local_graph_id, title_cache
		FROM graph_templates_item AS gti
		INNER JOIN graph_templates_graph AS gtg
		ON gti.local_graph_id = gtg.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		WHERE local_data_id = ?
		LIMIT 1",
		array($dataid));

	$local_graph_id = $line['local_graph_id'];
	$host_id = $_REQUEST['host_id'];
?>
<html>
<head>
	<script type="text/javascript">
	function update_source_step2(graphid) {
		var graph_url, hover_url;
		var base_url = '<?php print isset($config['base_url']) ? $config['base_url']:''; ?>';

		if (typeof window.opener == 'object') {
			graph_url = base_url + 'graph_image.php?local_graph_id=' + graphid + '&rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300';
			info_url  = base_url + 'graph.php?rra_id=all&local_graph_id=' + graphid;

			opener.document.forms['frmMain'].link_infourl.value = info_url;
			opener.document.forms['frmMain'].link_hover.value = graph_url;
		}
		self.close();
	}

	$(function() {
		update_source_step2(<?php print $local_graph_id ?>);
	});

	</script>
</head>
<body>
This window should disappear in a moment.
</body>
</html>
	<?php
	if ($host_id > 0 && !in_array($host_id, $_SESSION['cacti']['weathermap']['last_used_host_id'])) {
		$_SESSION['cacti']['weathermap']['last_used_host_id'][] = $host_id;
		$_SESSION['cacti']['weathermap']['last_used_host_name'][] = $line['title_cache'];

		$_SESSION['cacti']['weathermap']['last_used_host_id'] = array_slice($_SESSION['cacti']['weathermap']['last_used_host_id'], -5);
		$_SESSION['cacti']['weathermap']['last_used_host_name'] = array_slice($_SESSION['cacti']['weathermap']['last_used_host_name'], -5);
	}
	// end of link step 2
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'link_step1') {
?>
<html>
<head>
	<script type='text/javascript'>

	function filterlist(previous) {
		var filterstring = $('input#filterstring').val();

		if (filterstring=='') {
			$('ul#dslist > li').show();
			return;
		}

		if (filterstring!=previous) {
			$('ul#dslist > li').hide();
			$("ul#dslist > li:contains('" + filterstring + "')").show();
		}
	}

	$(function() {
		$('span.filter').keyup(function() {
			var previous = $('input#filterstring').val();
			setTimeout(function () {filterlist(previous)}, 500);
		}).show();
	});

	function update_source_step1(dataid,datasource,host_id) {
		var newlocation;
		var fullpath;

		var rra_path = <?php print js_escape($config['rra_path']); ?>;

		if (typeof window.opener == 'object') {
			fullpath = datasource.replace(/<path_rra>/, rra_path);

			if (document.forms['mini'].aggregate.checked) {
				opener.document.forms['frmMain'].link_target.value = opener.document.forms['frmMain'].link_target.value  + ' ' + fullpath;
			} else {
				opener.document.forms['frmMain'].link_target.value = fullpath;
			}
		}

		if (document.forms['mini'].overlib.checked) {
			newlocation = 'cacti-pick.php?command=link_step2&dataid=' + dataid + '&host_id=' + host_id;
			self.location = newlocation;
		} else {
			self.close();
		}
	}

	function applyDSFilterChange(objForm) {
		strURL = '?host_id=' + objForm.host_id.value;
		strURL = strURL + '&command=link_step1';

		if (objForm.overlib.checked) {
			strURL = strURL + '&overlib=1';
		} else {
			strURL = strURL + '&overlib=0';
		}

		// document.frmMain.link_bandwidth_out_cb.checked
		if ( objForm.aggregate.checked) {
			strURL = strURL + '&aggregate=1';
		} else {
			strURL = strURL + '&aggregate=0';
		}
		document.location = strURL;
	}
	</script>
	<style type='text/css'>
		body { font-family: sans-serif; font-size: 10pt; }
		ul { list-style: none;  margin: 0; padding: 0; }
		ul { border: 1px solid black; }
		ul li.row0 { background: #ddd;}
		ul li.row1 { background: #ccc;}
		ul li { border-bottom: 1px solid #aaa; border-top: 1px solid #eee; padding: 2px;}
		ul li a { text-decoration: none; color: black; }
	</style>
	<title>Pick a data source</title>
</head>
<body>
<?php

$host_id   = -1;
$overlib   = true;
$aggregate = false;

if (isset($_REQUEST['aggregate'])) {
	$aggregate = ($_REQUEST['aggregate'] == 0 ? false : true);
}

if (isset($_REQUEST['overlib'])) {
	$overlib = ($_REQUEST['overlib'] == 0 ? false : true);
}

$hosts = db_fetch_assoc_prepared("SELECT id,
	CONCAT_WS('',description,' (',hostname,')') AS name
	FROM host
	ORDER BY description,hostname");
?>

<h3>Pick a data source:</h3>

<form name='mini'>
<?php
if (cacti_sizeof($hosts) > 0) {
	print 'Host: <select name="host_id"  onChange="applyDSFilterChange(document.mini)">';

	print '<option ' . ($host_id == -1 ? 'SELECTED' : '' ).' value="-1">Any</option>';
	print '<option ' . ($host_id == 0  ? 'SELECTED' : '' ).' value="0">None</option>';

	foreach ($hosts as $host) {
		print '<option ';

		if ($host_id == $host['id']) {
			print ' selected ';
		}

		print 'value="' . $host['id'] . '">' . html_escape($host['name']) . '</option>';
	}

	print '</select><br />';
}

print '<span class="filter" style="display: none;">Filter: <input id="filterstring" name="filterstring" size="20"> (case-sensitive)<br /></span>';
print '<input id="overlib" name="overlib" type="checkbox" value="yes" ' . ($overlib ? 'CHECKED' : '' ) . '> <label for="overlib">Also set OVERLIBGRAPH and INFOURL.</label><br />';
print '<input id="aggregate" name="aggregate" type="checkbox" value="yes" '.($aggregate ? 'CHECKED' : '' ).'> <label for="aggregate">Append TARGET to existing one (Aggregate)</label>';
print '</form><div class="listcontainer"><ul id="dslist">';

if (isset_request_var('host_id') && get_filter_request_var('host_id') >= 0) {
	$host_id = get_filter_request_var('host_id');

	$queryrows = db_fetch_assoc_prepared('SELECT dl.host_id, dtd.local_data_id,
		dtd.name_cache, dtd.active, dtd.data_source_path
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		WHERE dl.host_id = ?
		ORDER BY name_cache',
		array($host_id));
} else {
	$queryrows = db_fetch_assoc_prepared('SELECT dl.host_id, dtd.local_data_id,
		dtd.name_cache, dtd.active, dtd.data_source_path
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		ORDER BY name_cache');
}

$i=0;

if (is_array($queryrows) && sizeof($queryrows) > 0) {
	foreach ($queryrows as $line) {
		print "<li class=\"row".($i%2)."\">";

		$key = $line['local_data_id']."','".$line['data_source_path'];

		print "<a href=\"#\" onclick=\"update_source_step1('$key','$host_id')\">". $line['name_cache'] . "</a>";
		print "</li>\n";

		$i++;
	}
} else {
	print "<li>No results...</li>";
}

?>
</ul>
</div>
</body>
</html>
<?php
} // end of link step 1

if (isset($_REQUEST['command']) && $_REQUEST['command']=='node_step1') {
	$host_id = -1;

	$overlib   = true;
	$aggregate = false;

	if (isset($_REQUEST['aggregate'])) {
		$aggregate = ($_REQUEST['aggregate'] == 0 ? false : true);
	}

	if (isset($_REQUEST['overlib'])) {
		$overlib = ($_REQUEST['overlib'] == 0 ? false : true);
	}

	$hosts = db_fetch_assoc_prepared("SELECT id, CONCAT_WS('',description,' (',hostname,')') AS name
		FROM host
		ORDER BY description, hostname");
?>
<html>
<head>
	<!-- <script type="text/javascript" src="vendor/jquery/dist/jquery.min.js"></script> -->
	<script type="text/javascript">
	function filterlist(previous) {
		var filterstring = $('input#filterstring').val();

		if (filterstring=='') {
			$('ul#dslist > li').show();
			return;
		}

		if (filterstring!=previous) {
			$('ul#dslist > li').hide();
			$('ul#dslist > li').contains(filterstring).show();
		}
	}

	$(function() {
		$('span.filter').keyup(function() {
			var previous = $('input#filterstring').val();
			setTimeout(function () {filterlist(previous)}, 500);
		}).show();
	});

	function applyDSFilterChange(objForm) {
		var strURL = '?host_id=' + objForm.host_id.value;

		strURL += '&command=node_step1';

		if ( objForm.overlib.checked) {
			strURL = strURL + "&overlib=1";
		} else {
			strURL = strURL + "&overlib=0";
		}

		//if ( objForm.aggregate.checked)
		//{
		//	strURL = strURL + "&aggregate=1";
		//}
		//else
		//{
		//	strURL = strURL + "&aggregate=0";
		//}
		document.location = strURL;
	}

	function update_source_step1(graphid) {
		var graph_url, hover_url;

		var base_url = '<?php print isset($config['base_url'])?$config['base_url']:''; ?>';

		if (typeof window.opener == "object") {
			graph_url = base_url + 'graph_image.php?rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300&local_graph_id=' + graphid;
			info_url = base_url + 'graph.php?rra_id=all&local_graph_id=' + graphid;

			// only set the overlib URL unless the box is checked
			if ( document.forms['mini'].overlib.checked) {
				opener.document.forms["frmMain"].node_infourl.value = info_url;
			}

			opener.document.forms["frmMain"].node_hover.value = graph_url;
		}

		self.close();
	}
	</script>
	<style type='text/css'>
		body { font-family: sans-serif; font-size: 10pt; }
		ul { list-style: none;  margin: 0; padding: 0; }
		ul { border: 1px solid black; }
		ul li.row0 { background: #ddd;}
		ul li.row1 { background: #ccc;}
		ul li { border-bottom: 1px solid #aaa; border-top: 1px solid #eee; padding: 2px;}
		ul li a { text-decoration: none; color: black; }
	</style>
	<title>Pick a graph</title>
</head>
<body>

<h3>Pick a graph:</h3>

<form name="mini">
<?php
if (cacti_sizeof($hosts) > 0) {
	print 'Host: <select name="host_id"  onChange="applyDSFilterChange(document.mini)">';

	print '<option ' . ($host_id == -1 ? 'SELECTED' : '' ) . ' value="-1">Any</option>';
	print '<option ' . ($host_id == 0  ? 'SELECTED' : '' ) . ' value="0">None</option>';

	foreach ($hosts as $host) {
		print '<option ';

		if ($host_id==$host['id']) {
			print " selected ";
		}

		print 'value="' . $host['id'] . '">' . html_escape($host['name']) . '</option>';
	}

	print '</select><br />';
}

print '<span class="filter" style="display: none;">Filter: <input id="filterstring" name="filterstring" size="20"> (case-sensitive)<br /></span>';
print '<input id="overlib" name="overlib" type="checkbox" value="yes" '.($overlib ? 'CHECKED' : '' ).'> <label for="overlib">Set both OVERLIBGRAPH and INFOURL.</label><br />';

print '</form><div class="listcontainer"><ul id="dslist">';

//$SQL_picklist = "SELECT graph_templates_graph.id, graph_local.host_id, graph_templates_graph.local_graph_id, graph_templates_graph.height, graph_templates_graph.width, graph_templates_graph.title_cache, graph_templates.name, graph_local.host_id	FROM (graph_local,graph_templates_graph) LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) WHERE graph_local.id=graph_templates_graph.local_graph_id ";
if (isset_request_var('host_id') && get_filter_request_var('host_id') > 0) {
	$host_id = get_request_var('host_id');

	$queryrows = db_fetch_assoc_prepared("SELECT gtg.id, gl.host_id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, gt.name
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		WHERE gl.host_id = ?
		ORDER BY title_cache",
		array($host_id));
} else {
	$queryrows = db_fetch_assoc_prepared("SELECT gtg.id, gl.host_id, gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, gt.name
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		ORDER BY title_cache");
}

$i=0;

if (is_array($queryrows) && sizeof($queryrows) > 0) {
	foreach ($queryrows as $line) {
		print "<li class=\"row".($i%2)."\">";

		$key = $line['local_graph_id'];

		print "<a href=\"#\" onclick=\"update_source_step1('$key')\">". $line['title_cache'] . "</a>";
		print "</li>\n";

		$i++;
	}
} else {
	print "No results...";
}
?>
</ul>
</body>
</html>
<?php
} // end of node step 1

