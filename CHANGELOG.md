# CHANGELOG.md

## Background

This version of Weathermap is a fork of the original work by Howard Jones, and brings his 0.98x version
into the Cacti 1.x world.  Although, the change notes are roughly correct,, there were just too
many changes to capture them all.

There is a more substantial release that Howie has been working on in the works, that is a fundamental shift
in the framework and provides several improvements to prior releases. There are also small usability changes 
aimed at reducing repeat "error reports" in the forums.

IMPORTANT NOTE: This version only works on CACTI 1.x++!

## Changes

--- 1.1 ---
* issue#87: WeatherMap Sort Order not respected on Top Tab
* issue#89: Allow more Graphs types to be searched for
* issue#90: Error: You have an error in your SQL syntax, Mysql 8.0 Weathermap 1.0
* issue#111: Weathermap permissions management errors
* issue#119: The table weathermap_settings does not enforce a unique key on the mapid, groupid, and optname columns
* issue#121: Division by zero in rare cases
* feature#97: Auto-set the boost setting instead of making it a discrete user setting


--- 1.0 Beta 4 ---
* issue#64: Map Properties-Map Size-Unable to display entire map
* issue#74: Weathermap Editor Issues
* issue#75: There is no option to create a new map
* issue#76: Graph Selector and Data Source Selector enhancements suggestions.
* issue#77: Missing scroll bar on large map
* issue#79: Bandwidth test a bit off with angled style and popup size issues
* issue#81: Weathermap 1.0 - Editor button leads to the Editor page without css
* issue#82: Unable to sort Maps

--- 1.0 Beta 3 ---
* issue#62: Missing ICON data
* issue#63: LOG:ICONFILE file not found.  When using the specified icon.
* issue#65: The problem with the default width of the hover map
* issue#66: Automatic refresh of webpage will not work
* issue#67: Weathermap log error
* issue#68: Switching to php 8.1 implicit conversion from float to int looses precision
* issue#70: CSS improvements
* issue#71: The OVERLIBGRAPH of the link at the edge position is outside the bounding box.
* issue#72: The NOTES function fails.

--- 1.0 Beta 2 ---
* issue#49: The Data Source Selector does not work

--- 1.0 Beta 1 ---
* feature#43: Allow Weathermap Permissions to Include Cacti User Groups
* issue#18: No scrolling list of allowed users
* issue#37: Need to clear iCCP chunk from png files for newer libpng
* issue#46: 1.0 - QA When adding datasources or graphs using the Add button, a space will always be added
* issue#47: 1.0 - QA Don't assume that the Cacti poller has write access to the images folder and subfolders

--- 1.0 ---
* FIXED  - General support for PHP 8.2
* FIXED  - Removed any "mysql*" function calls
* FIXED  - Use Cacti Database API as applicable
* FIXED  - Use Cacti Database API prepared statements.
* FIXED  - Remove dependency on PECL Console_Getopt() library
* FIXED  - Image Library Support changes in PHP 8.x
* FIXED  - Deprecation of strftime() in PHP 8.1
* FIXED  - Label render centering and math errors for background
* FIXED  - Editing Node and Link configuration text from browser
* TODO   - Parallel Map Rendering API
* TODO   - Internationalization (i18n)
* TODO   - Drop Downs for DSStats and Thold
* TODO   - Label Offset and Angle form objects
* TODO   - Colors Interface
* TODO   - Manage Images Interace
* TODO   - Rename conf file and title from Cacti UI
* ADDED  - Right mouse context menu during map editing
* ADDED  - Support for Cacti Themes
* ADDED  - Drop image functionality for Nodes and Background images
* ADDED  - Drop Down actions similar to follow the Cacti UI standard
* ADDED  - More statistics gathering for map rendering
* ADDED  - Separated background and object image locations
* ADDED  - Large library of network objects converted from SVG
* ADDED  - Removal of Cacti pick functionality by adding directly to dialogs
* ADDED  - Disabled context menu except for correct objects
* CHANGE - The map editing interface to use jQueryUI dialog for form rendering
* CHANGE - Simplify the Editor script separating functions and api cactions to separate files
* CHANGE - Cacti PSR throughout
* CHANGE - Remove quite a bit of legacy unused code
* CHANGE - Movement of css and javascript files to LSB locations
* CHANGE - The Automatic Cycling of Maps to used FontAwesome Glyphs instead of images
* CHANGE - The Weathermap interface to follow Cacti UI standard
* CHANGE - Logging format to follow Cacti standards
* CHANGE - Perfrom Map Editing through callbacks removing page refreshes
* CHANGE - Move Group tabs to Cacti tab standard
* REMOVE - Dependency on overlib.js.  This file has been removed

--- 0.98a ---
* FIXED  - Works with PHP 7.x and 5.6 - removed all mysql_*() function calls, and use PDO instead
* FIXED  - Blank line issue with RRDtool 1.5+

--- 0.98 ---
* FIXED  - MySQL error in table-creation. MySQL 5.6 is fussier.
* FIXED  - Editor 'delete link' broken
* FIXED  - KILO was ignored when processing %k in special tokens
* FIXED  - Various fixes for PHP deprecated or strict-mode warnings
* FIXED  - Line-ending trimming in 'external script' data source
* FIXED  - rounding error 'kinks' in angled VIA links
* FIXED  - Config file path validation issue in editor (CVE-2013-3739)
* FIXED  - Cloning a templated node in editor retains the template in the clone
* FIXED  - cacti_use_ifspeed incorrect when interfaces > 20M and ifhighspeed available
* FIXED  - More PHP 5.3/5.4/strict related errors (split -> explode)
* CHANGE - Finally switch to "new-style" plugin API.
* CHANGE - Editor data picker improved sort (thanks shd)
* CHANGE - (Cacti plugin only) images are written to a temporary file first, to avoid displaying half-written images
* CHANGE - Editor no longer uses editor-config.php
* ADDED  - Weathermap will use anti-aliasing if your GD supports it (php-bundled GD doesn't) (thanks shd)
* ADDED  - Special token formatting can handle timeticks and time_t formatting (%T and %t respectively)
* ADDED  - new DATAOUTPUTFILE allows collected data to be written to a file for later use (automatically enabled in Cacti)
* ADDED  - new wmdata: datasource plugin can read data from files produced by DATAOUTPUTFILE
* ADDED  - IMAGEOUTPUTFILE and HTMLOUTPUTFILE are honoured in Cacti poller as a location for a second copy of those files.
* ADDED  - Editor 'tidy link' function replaces Vert & Horiz, and does much nicer job
* ADDED  - 'retidy' option in editor to recalculate all links previously positioned with 'tidy'.
* ADDED  - KEYBGCOLOR and KEYOUTLINECOLOR both accept 'none'
* ADDED  - command-line weathermap has new --no-warn option to disable warnings
* ADDED  - AICONFILLCOLOR accepts 'none' for drawing giant transparent shapes.
* ADDED  - Extra warning for Boost users about poller_output
* ADDED  - if there are actually 0 maps in the database, the 'Weathermaps' tab gives some basic instructions.
* ADDED  - Cacti data picker in editor tracks most recently used hosts (thanks Zdolny)
* ADDED  - New permission in Cacti: edit maps. Maps can be edited by authorized users without needing to enable the editor in the source code.

--- 0.97c  ---
* NOTE   - Had no additional changes - it's just 0.97b with some silly errors fixed, and  all
           the CSS files included with the correct paths. There were also reports of problems with the
           actual zip file.

--- 0.97b ---
* NOTE   - This is a special release starting from 0.97a and backporting all the quick bugfixes from 
           the 0.98 code.  There are larger structural changes in 0.98 and new features, but these 
           bugfixes were useful enough to warrant a new 0.97 release (especially the mysql schema change). 
           A real 0.98 release will follow in due course.
* NOTE   - Also late addition to 0.97b - a couple of security analysts have pointed out flaws in the editor.
           First, the ability to remotely create .php files and then cross-site-scripting vulnerabilities. Both
           are really facets of the same thing - lack of input validation. 0.97b improves this a great deal.
* NOTE   - Thanks to Gerry Eisenhaur and Daniel Ricardo dos Santos respectively for their security bug reports.
* FIXED  - absolute SCALE definitions didn't support K (thanks wwwdrich)
* FIXED  - memory leak in poller code. Memory usage is MUCH lower now.
* FIXED  - updated mysql schema commands to use modern ENGINE instead of TYPE;
* FIXED  - static datasource plugin honours KILO
* FIXED  - check-gdbug.php shouldn't complain about empty ob_flush buffers anymore
* FIXED  - SNMP DS should deal better with non-numeric (and blank) return values
* FIXED  - NINK colours were exchanged (thanks Deathwing00!)
* FIXED  - WriteConfig (i.e. editor) won't 'lose' absolute keyword from
* FIXED  - fixed some function-name clashes with other plugins
* FIXED  - PHP 5.3/5.4/strict related errors ("Creating default object from empty value")
* CHANGE - editor snap function improved (snap to *nearest* point) (thanks Andreas Braun)
* CHANGE - the Cacti UI will warn you about fundamental file permissions problems
* CHANGE - editor ignores attempts to rename nodes to have space in names
* CHANGE - Moved all PHP that doesn't need to be web-accessible into lib
* CHANGE - Editor won't deal with config files that don't have a .conf extension
* CHANGE - Manual updates for changes in Cacti, and improvements in styling. Security section added.
* CHANGE - General improvements in input validation and output escaping in editor
* CHANGE - 'External Script' datasource plugin is disabled by default in new installs (NOT upgrades though!)
* ADDED  - Caching for cacti data fetched by dsstats and rrdtool/poller_output DS plugins
* ADDED  - Ability to disable any warning with 'SET nowarn_WMxxx 1'
* ADDED  - .htaccess files added for directories that don't need to be web-accessible - needs 
           "AllowOverride Limit" or "AllowOverride All" in your Apache config.

--- 0.97a
* FIXED  - Incorrect action URL in 'map selector' combo box for Cacti users.
* FIXED  - cacti_graph_id set to 0 instead of ID, by rrd/poller_output and dsstats plugins (thanks sh0x)
* FIXED  - 'classic' legend drew 'hidden' colour values for things like key background colour. (thanks jmayniac)
* FIXED  - PHP 5.3 deprecated code in HTML_Imagemap.class.php
* FIXED  - 'Show Only First' option ignored in Cacti (thanks inko_nick)
* FIXED  - Editor deals with overlapping nodes on different ZORDERS properly.
* FIXED  - "property of non-object in editor.php line 466" while editing map properties (thanks to iNeo)
* FIXED  - no-data option on command-line didn't work
* FIXED  - Clone Node was broken in 0.97
* FIXED  - Maps with per-user permissions show up multiple times in map selector
* FIXED  - Removed incorrect warning about imagefilter and USEICONSCALE.
* FIXED  - string escaping bug with editor and direct config changes (thanks uhtred)
* FIXED  - --imageuri was ignored on command-line (thanks Marcus Stögbauer)
* FIXED  - links with targets containing spaces are broken by the editor (thanks Andreas Braun)
* FIXED  - deprecated jQuery function call in cacti-pick.php (thanks again Andreas Braun)
* CHANGE - Group sorting is a bit more logical and the presentation nicer.
* CHANGE - cacti-integrate.php uses getopt to take more command-line params
* CHANGE - Updated jQuery to latest version
* CHANGE - Number formatting will pick 1G over 1000M (and similar) (thanks cerbum)
* CHANGE - The editor is disabled by default - see top of editor.php (and install guide)
* ADDED  - LINK WIDTH accepts decimals
* ADDED  - cacti-integrate.php can generate DSStats TARGETs too
* ADDED  - Simple VIA editing in editor (thanks to Zdolny)
* ADDED  - SCALE can accept G,M,K,T,m,u,n suffixes (for absolute scales)

--- 0.97
* FIXED  - RRD Aggregation regexp was failing (thanks to shd)
* FIXED  - Scale numerals honour locale (thanks again, shd)
* FIXED  - THold plugin check failed with Thold 0.4.1 (PA 2.x, actually)
* FIXED  - Uninitialized variable in ReadData when plugin is disabled
* FIXED  - Zero-length link check didn't include offsets (thanks Ryan Botoluzzi)
* FIXED  - Cacti-pick should get right rra path for packagers that move the rra directory (e.g. Ubuntu, Debian *again*)
* FIXED  - DS plugins that return one value and a null should work properly
* FIXED  - "Strange" characters (e.g. /) in NODE and LINK names broke the imagemap.
* FIXED  - Map Style settings in editor were broken after internal defaults changes
* FIXED  - Imagemap no longer contains areas with no href defined
* FIXED  - SPLITPOS was ignored with VIASTYLE angled (thanks to uhtred)
* FIXED  - 'AICONOUTLINECOLOR none' is actually valid now (thanks to mgb & Leathon)
* FIXED  - readdir() loop never stops, on some systems (thanks to jerebernard)
* FIXED  - bad regexp in the MRTG DS plugin (thanks to Matt McMahon)
* FIXED  - 0.96 had a new 'time' DS plugin - now documented!
* FIXED  - NCFPC now only complains about missing scale lines on NODEs for the variable that is in use.
* ADDED  - USEICONSCALE no longer has special dependencies - and the colours are nicer too.
* ADDED  - Option of a dropdown selector to navigate between maps (in full-size view)
* ADDED  - Maps can be organised into groups in Cacti plugin. These appear as tabs in the UI for viewing maps.
* ADDED  - Extra variables can be defined per-group, so all maps in a group can have similar settings (e.g. a "24hr average" tab).
* ADDED  - INCLUDE keyword to include a file of common definitions (based on work by BorisL)
           (NOTE: this can confuse the editor sometimes - see the manual page for INCLUDE)
* ADDED  - Warning for maps that contain OVERLIBGRAPH but not 'HTMLSTYLE overlib'
* ADDED  - Warning for use of TEMPLATE not as the first line of an object (overwrites settings otherwise)
* ADDED  - SCALE will accept values below 0, and also above 100
* ADDED  - USESCALE has two new options: absolute and percent, which allows you to have a SCALE of absolute values
* ADDED  - New datasource plugin to support statistics from TheWitness's DSStats Cacti Plugin. This 
           gets you daily,weekly,monthly and annual stats with no complicated rrdtool stuff.
* ADDED  - New converter to take a rrdtool-based map config and make it into a DSStats-based one
* ADDED  - static datasource can be used for negative values
* ADDED  - SNMP datasource has configurable timeout and retry values.
* ADDED  - SNMP datasource has option to give up on a failing host
* ADDED  - LABELOFFSET supports percentage compass offsets and radial offsets, like NODES does.
* ADDED  - Percentage compass offsets (NODES and LABELOFFSET) support > 100% offsets

--- 0.96a
* FIXED  - New z-ordering code did not work correctly on PHP4. This broke (at least) the editor. (thanks toe_cutter)
* FIXED  - \n is no longer treated as a newline in TARGETs (thanks NetAdmin)
* FIXED  - KILO was broken completely between 0.95b and 0.96 (thanks Jethro Binks)
* FIXED  - Link comments in certain positions could cause div-by-zero errors. (thanks again Jethro)
* FIXED  - USEICONSCALE didn't colorise (broken between 0.95b and 0.96 again) (thanks colejv)
* FIXED  - Managed to make LABELOFFSET case-sensitive.

--- 0.96
* ADDED  - TEMPLATE allows a node or link to copy it's settings from another, instead of from DEFAULT.
* ADDED  - RRD datasource can take SET rrd_default_path to make configs a little easier to read.
* ADDED  - RRD datasource can take SET rrd_default_in_ds and rrd_default_out_ds for non-Cacti users.
* ADDED  - RRD datasource can get Cacti query information (in poller_output mode ONLY) - like ifAlias, ifSpeed etc
* ADDED  - RRD datasource can take the ifSpeed/ifHighSpeed from the above, and use it in the map.
* ADDED  - RRD datasource fills in Cacti cacti_path_rra and cacti_url with Cacti base path and URL
* ADDED  - RRD datasource can take global SET rrd_options to add extra options to rrdtool command lines
* ADDED  - SNMP datasource also stores the raw data from the SNMP agent in snmp_raw_in/snmp_raw_out
* ADDED  - SNMP datasource allows '-' as an OID, similar to '-' targets in RRDs.
* ADDED  - Control the drawing order with ZORDER.
* ADDED  - New artificial icons: nink, inpie and outpie. See ICON in manual.
* ADDED  - Warning for probably-incorrect BWLABELPOS where in<out
* ADDED  - Warning for gaps in a SCALE
* ADDED  - New global SET variables to disable some common warnings you may not care about :-)
* ADDED  - Cacti management screen shows number of warnings for each map last time it ran
* ADDED  - Cacti management screen also has a link to the log entries for the map in question
* ADDED  - The TARGET aggregation thing can also take scale factors now: -5.5*myrrdfile.rrd
* ADDED  - Cacti plugin caches thumbnail sizes, improving thumbnail view rendering
* ADDED  - Cacti plugin allows adding the same map twice (more useful than it sounds)
* ADDED  - Cacti plugin allows setting of map-global variables in the management UI
* ADDED  - Cacti plugin allows settings of global map-global (across all maps) variables too
* ADDED  - Cacti plugin adds links in 'user' pages to management screen (if you are an admin)
* ADDED  - HTMLSTYLESHEET keyword allows you to specify a URL for a CSS stylesheet (CLI tool only)
* ADDED  - A few extra CSS id and class attributes, to make styling the page easier.
* ADDED  - New token: in/outscalecolor contains HTML colour code of node/link colours for use in NOTES
* ADDED  - New NODES offset type - angle+radius
* ADDED  - New NODES offset type - compass-point+percentage
* ADDED  - 'KEYSTYLE inverted' - to get a thermometer-style vertical legend.
* ADDED  - 'COMMENTSTYLE center' to make comments run along the centre of a link arrow. (and 'edge' for the usual)
* ADDED  - COMMENTFONTCOLOR accepts 'contrast' as an option, for when it's over a link
* ADDED  - VIASTYLE angled (or curved) - you can turn sharp corners now
* ADDED  - Comment (and pos) editing in editor (based on code by Zdolny)
* ADDED  - Editor Settings dialog works, and allows you to set grid-snap and some overlays
* ADDED  - SCALE allows 'none' as a colour (for non-gradients). Only affects LINKs so far.
* ADDED  - fping plugin allows for changing the number of pings.
* ADDED  - TARGET strings can be enclosed in quotes, to allow spaces in them (mainly for external ! scripts)
* ADDED  - 'KEYSTYLE tags' - like classic, but uses the scale tags instead of percentages.
* ADDED  - scripts in random-bits to help with automatic/assisted mapping.
* ADDED  - lots more pretty pictures in the manual, so you can see what I mean.
* ADDED  - IMAGEURI keyword to match --image-uri command-line option (ignored in Cacti plugin)
* ADDED  - MINTIMEPOS and MAXTIMEPOS to track data source times
* CHANGE - Cacti plugin uses 'processed' map title now (allows {} tokens in the title)
* CHANGE - A NODE with no POSITION is not drawn, instead of drawn at 0,0. Useful for templates.
* CHANGE - A LINK with no NODES is no longer an error. Also for templates.
* CHANGE - The link_bulge secret mode bulges each side of a link independently now
* CHANGE - whitespace is stripped from the beginning and end of each line before parsing
* CHANGE - OVERLIBWIDTH/HEIGHT are used for img tag width and height - so they must be *correct* now
* FIXED  - Cacti poller_output support works more reliably/at all on Windows
* FIXED  - Renaming a node in the editor correctly handles other relatively-positioned nodes and vias
* FIXED  - Minor issue with CRLF in map title for Cacti Plugin
* FIXED  - CLI tool set --define options incorrectly.
* FIXED  - Oneway links don't draw the INCOMMENT anymore
* FIXED  - negative TIMEPOS didn't hide the timestamp
* FIXED  - DEFAULT SCALE covers 0-100 properly (Thanks Dan Fusselman)
* FIXED  - Scaled ICON in DEFAULT didn't get overwritten properly in nodes (Thanks Fabrizio Carusi)
* FIXED  - No more floating-point imagemap coords (Thanks Trond Aspelund)
* FIXED  - RRDtool regional output (. vs ,) workaround
* FIXED  - Cacti poller_output handles NaN more gracefully now
* FIXED  - SNMP datasource should work with Windows SNMP again
* FIXED  - MRTG datasource tried to stat() URLs
* FIXED  - Error reporting for CLI --define was bad. --help text was out of date.
* FIXED  - Editor will honour LABEL from NODE DEFAULT, if it is set.

--- 0.95b
* FIXED  - SQL schema issue that upset some mysql versions
* FIXED  - Removed sometime-problem debugging code in editor
* FIXED  - incorrect jquery path in cacti-picker
* FIXED  - INFOURL not used without overlib
* FIXED  - 'none' colours on LINKs were broken by typo.
* FIXED  - NOTES, INNOTES, OUTNOTES all just flat-out borken in 0.95 and 0.95a
* FIXED  - Page title can be incorrect if filehash starts with a digit.
* FIXED  - Cacti plugin behaves a little better with auth disabled.

--- 0.95a
* FIXED  - problem with global map variables. Oops.
* FIXED  - few deprecated function references.
* FIXED  - couple of tiny docs errors.
* FIXED  - bug where INFOURL without OVERLIBGRAPH would be ignored
* CHANGE - Added in a SCALE line in the DEFAULT scale for between 0 and 1. Stops a lot of warnings on low-traffic links.
* CHANGE - Tweaked node cloning in editor - clone is offset in x and y now.

--- 0.95
* ISSUE  - ININFOURL/OUTINFOURL, INOVERLIBGRAPH/OUTOVERLIBGRAPH are not handled well by the editor. If you edit a map that uses these,
           then the 'in' side of the link will be copied to the 'out' side. New editor will handle this better.
* ADDED  - you get a warning if you are using values outside of the defined SCALE now.
* ADDED  - You can add a 'tag' to a SCALE line, to be used in ICON or LABELs later.
* ADDED  - USEICONSCALE - colorize icon images (based on patches from llow)
* ADDED  - screenshot mode. "SET screenshot_mode 1" at the top of the map will anonymise all labels, comments and bwlabels.
* ADDED  - LABELFONTCOLOR can use a special value of 'contrast' to always contrast with the label colour.
* ADDED  - Artificial Icons. Special icon 'filenames' - 'box' 'round' 'rbox' create a shaped icon without any file.
* ADDED  - Map titles show up in browser title now.
* ADDED  - a basic 'live view' function which generates a map on demand. Sometimes. It's not very useful.
* ADDED  - LABELANGLE allows you to rotate node labels to 90,180,270 degrees. Needs truetype font.
* ADDED  - improved data-source picker in editor: host filter
* ADDED  - improved data-source picker in editor: option to aggregate data sources
* ADDED  - Moved data-source picker changes across into the graph-picker for NODEs too.
* ADDED  - SPLITPOS keyword to control position of midpoint in links
* ADDED  - VIAs can be positioned relative to NODEs (like NODEs can) (thanks again to llow)
* ADDED  - Weathermap has a hook in the map viewing page to allow other plugins to add code there
* ADDED  - .htaccess files bundled with Weathermap to restrict direct access to configs and output
* ADDED  - filenames for output are much less guessable now (may break external references to maps)
* ADDED  - You can use 'DUPLEX half' on a link to make the bandwidth percentage calculate work for half-duplex links
* ADDED  - ININFOURL/OUTINFOURL, INOVERLIBGRAPH/OUTOVERLIBGRAPH, INNOTES/OUTNOTES
* ADDED  - allow you to have different urls for the in and out side of links (based on idea from llow)
* ADDED  - OVERLIBGRAPH (and IN/OUT versions) can take multiple URLs separated by spaces (again from idea by llow)
* ADDED  - debug/warning log output contains the map name, and the debug output is marked DEBUG
* ADDED  - debug log output contains the calling function, file/line number, too. Making debugging-by-mail easier.
* ADDED  - fping: TARGET to do live pings of devices. See targets.html
* ADDED  - a sample 'skeleton' DS plugin
* ADDED  - an additional check-gdbug.php to spot bad GD installs
* ADDED  - MRTG DS plugin can do a few new tricks. See TARGET and targets.html
* CHANGE - DS plugins are able to return negative results now ***breaks user-developed DS plugins***
* CHANGE - the scale: prefix for the RRD DS plugin can take negative scale factors
* CHANGE - (internal) plugins are each created as a single object now. Result: the plugin can cache results internally.
* CHANGE - (internal) broke out some of the larger classes (node, link) into separate files.
* FIXED  - KEYOUTLINECOLOR is actually used now (thanks to llow once more)
* FIXED  - Editor doesn't throw away WIDTH and HEIGHT with no BG image
* FIXED  - Cacti Data-source and Graph picker doesn't restrict scrolling or resizing anymore
* FIXED  - weathermap-cacti-rebuild.php to work on both Cacti 0.8.6 and 0.8.7
* FIXED  - weathermap-cacti-rebuild.php to flat-out fail if Cacti environment is wrong.
* FIXED  - SNMP DS plugin had a typo that stopped it working at all (and no-one
           noticed for almost a year :-) ). (thanks to Fratissier Christophe for pointing it out)
* FIXED  - Added some better controls into SNMP DS plugin. You can correctly pull
           interface oper/admin status, for example, now.

--- 0.941
* FIXED  - Issue with '-' DS names again.
* FIXED  - Added extra code to help discourage browser caching.
* FIXED  - Removed some extra chatty debugging code from poller_output.
* ADDED  - Page titles are more useful in Cacti now

--- 0.94
* ADDED  - INBWFORMAT and OUTBWFORMAT allow you to format the text for BWLABEL, same as for COMMENTs
* ADDED  - New cactithold/cactimonitor data source plugin reads data from Cacti's Threshold plugin.
           (Original development for this plugin was paid for by Stellar Consulting - Thanks!)
* ADDED  - New LINKSTYLE command allows you to have one-way (one arrow) links.
* ADDED  - RRD DS can use Cacti's poller_output to get data without running RRDtool at all.
           (this also means it can work with the Boost plugin for large installations)
           See targets.html for more info on this one.
* ADDED  - Editor - Align horizontal and Align-vertical for links. Calculates link offsets to make link vertical/horizontal.
* CHANGE - Finally a better tab image, and a red 'active' one too, for the Cacti plugin.
* FIXED  - "Full Screen Maps" mode in Cacti Plugin was broken by me adding the "View Only First" mode.
* FIXED  - Imagemaps for horiz/vert format legend were wrong in editor (thanks to Alex Moura for pointing this out)
* FIXED  - Changes for compatibility with Cacti 0.8.7's moved config file.

--- 0.93
* Added  - weathermap-cacti-plugin.php?action=viewmap&id=mapfilename  works as well as a map number - useful for crosslinks
* FIXED  - the key_hidezero secret setting hides the zero in a gradient in a classic scale too.
* CHANGE - the auth realm names for Cacti have been changed to match ReportIt and Aggregate - easier to tell who does what
* ADDED  - unique code for each warning message, and a page to explain it on the website. Ugh.
* ADDED  - warning in editor file-selector so you can tell if the file is read-only
* ADDED  - click config filename to edit in editor from Cacti (thanks to streaker69)
* FIXED  - editor-generated node names are a bit shorter (and easier to read) now.
* FIXED  - keyboard focus switches nicely to the popup dialogs now.
* ADDED  - cactihost: DS plugin fetches a bunch of other stats from Cacti's DB now, too (like availability and response times)
* ADDED  - Picking Cacti sources from editor has a javascript "live filter" feature now (needs a little work)
* ADDED  - node coordinates are directly editable in the editor now
* ADDED  - File picker allows you to use an existing map as a template
* CHANGE - Editor warns about older editor-config.php format now
* ADDED  - Editor now allows you to clone a node with all it's styling intact.
* ADDED  - When picking coordinates (new node, move node, move timestamp etc), you can see the coordinates
* ADDED  - Editor toolbar fixed to window, to make it easier to scroll around large maps
* ADDED  - RRD Datasource has improved warnings for non-existent DS names
* FIXED  - RRD doesn't consider DSes other than the ones you named when finding a valid line.
* CHANGE - Formatted numbers (Mega, Kilo etc) now can include milli, micro and nano (m,u,n).
* FIXED  - COMMENTPOS 0 doesn't kill everything anymore
* FIXED  - OVERLIB would behave incorrectly with PHP4 and relatively positioned nodes (Bernd Ziller)
* ADDED  - Editor allows you to edit raw text of nodes and links
* ADDED  - Editor link in management page (warnesj)
* ADDED  - Docs link in management page too (streaker69)
* ADDED  - Editor has a better warning for unwriteable files and directory now.
* ADDED  - When you come TO the editor from Cacti, the Change File goes BACK to Cacti
* ADDED  - "Show Only First" mode in Cacti UI - useful for heirarchies of maps with a parent.
* ADDED  - scale: prefix for RRD datasource - multiply/divide by any value as you read an rrd datasource
* FIXED  - Non-unique IDs in imagemaps, in overlib mode.
* FIXED  - Better-validating HTML produced
* FIXED  - angled bwlabels have the correct imagemap
* FIXED  - divide-by-zero error for some (?) PHP versions in poller
* ADDED  - Warning for duplicate node or link names

--- 0.92
* FIXED  - weathermap CLI help said --random-data instead of --randomdata
* FIXED  - one last php short_tag in poller_common.php - thanks Bernado Diez
* FIXED  - a SET in DEFAULT node/link is inherited by all node/links now.
* FIXED  - changing defaults in the editor changes existing objects that use the default value
* FIXED  - unreadable files in the configs/ directory don't kill the editor file-picker anymore
* FIXED  - weathermap.conf really *is* a simple map again. My test version went out with 0.9 and 0.91
* CHANGE - added a lot more memory debug points in
* CHANGE - refactored the curve/link drawing code to make some new features possible/easier
           Further tweaks and improvements to check.php (Basic GD check, memory_limit check, 
           PEAR Getopt check, and more explanation now) Small improvements to the editor's font-picking, 
           including samples of all fonts.
* Added  - Editor handles VIAs in a MUCH better way when moving nodes.
* CHANGE - Improved clipping reporting, improved plugin loading (Niels Baggesen)
* Added  - BWSTYLE lets you choose between regular and angled bwlabels
           angled bwlabels follow the angle of the link arrow, which can save space.
* Added  - Editor can pick Cacti graphs for NODE's overlib/infourl (but NOT targets)
* Added  - COMMENTPOS allows you to move the position of comments along the link (like BWLABELPOS)
* FIXED  - strange edge case with gradient SCALE caused div-by-zero (Tiago Giorgetti)
* CHANGE - The editor doesn't *require* an editor-config.php anymore. It *will* warn you if the defaults 
           aren't enough, however.

0.91
* FIXED  - RRD bug with '-' DS names. This was fixed in 0.9pre3, but somehow slipped through.
* FIXED  - KILO bug again (Steve Woodcock)
* FIXED  - handling of MRTG html files on remote systems
* FIXED  - ReadConfig doesn't complain about KEYPOS DEFAULT -1 -1 (as written by WriteConfig) anymore
* FIXED  - NOTES was not fully tested, and broke cactihost: targets, at least.
* FIXED  - BWLABELPOS wasn't handled properly by the editor/WriteConfig (it would swap the positions over)
* FIXED  - Documentation fixes for installation (to include check.php) and NODE TARGETs.
* ADDED  - check.php checks for presence of possibly-missing functions in your PHP installation
* ADDED  - 'quiet' logging setting for Cacti plugin - in LOW logging, only errors are logged.
* ADDED  - add a - to the front of a targetspec and you can take away values instead of aggregating them 
           (think Total-VPN=Internet, for example)
* ADDED  - the scale line that was 'hit' for each link direction is stored in inscalekey and outscalekey

--- 0.9 
* CHANGE - Changed node rendering - now we render to a transparent mini-image and blit them on. This will be 
           good for the editor.
* FIXED  - long-undetected bug in HTML_ImageMap for similarly named nodes or links (SLander)
* ADDED  - BWLABELPOS - you can specify the position of the bandwidth labels along the link
* ADDED  - OVERLIBCAPTION based on code from Jared Gillis
* CHANGE - Moved all ReadData code into plugins to allow for user-supplied data sources
* FIXED  - unreported bug where WriteConfig throws away floating point part of SCALEs
* FIXED  - another variation of the imagemap sorting bug from 0.81 (Erik van Cutsem)
* FIXED  - KILO ignored bug (found/patch by Steve Woodcock)
* FIXED  - unreported bug where WriteConfig throws away HTMLOUTPUTFILE and IMAGEOUTPUTFILE
* ADDED  - facility to use multiple SCALEs in a map. SCALE takes an optional name. NODEs and LINKs can have a 
           USESCALE line.
* FIXED  - bugs relating to assumption that 'admin' user always exists, and that users never go away, in the 
           Cacti mgmt tab (adrian marsh)
* ADDED  - Logging is tidied up a bit under Cacti's poller
* FIXED  - Added a warning for RRD data sources where the DS names you specify don't exist in the RRD file
* ADDED  - numeric offsets for NODES lines, like for LABELOFFSET. Move the end of a link whereever you like.
* ADDED  - a new 'static' datasource - you can hardcode values, if you ever needed to.
* ADDED  - a 'gauge:' datasource to the rrd plugin. Allows you to use values from any rrd without bit/byte-conversion.
* ADDED  - formatting tokens into most strings - you can print data on the map from various places, including 
           stuff placed there by plugins.
* ADDED  - if you specify an output filename ending in .jpg or .gif, you'll get a GIF or JPEG file.
* ADDED  - BACKGROUND and ICON also understand JPEG, GIF and PNG, if your GD library understands them.
* ADDED  - support for non-standard polling periods in Cacti plugin. You can choose to only update every so often, 
           or not at all (manually).
* ADDED  - manual 'recalculate now' button in Cacti plugin's management UI
* ADDED  - SET command to pass hints into plugins or weathermap core, per-node, per-link or globally.
* CHANGE - Cleaned up config parser. Can be more fussy. More consistent though.
* ADDED  - CLI tool takes --define to define SET-style variables

--- 0.82
* FIXED  - another variation on ReadFromRRD not detecting NaNs properly (hyland)
* FIXED  - SQL error which affects only some MySQL 5s (gundamx)
* FIXED  - some minor php errors in setup.php (cigamit)
* CHANGE - Changed the rrdtool period from now-400 to now-800 to avoid long-poller-cycle problems.
* CHANGE - Did some more /=>DIRECTORY_SEPARATOR changes - there weren't any problems, but it's a potential one.
* FIXED  - if you use the editor and your rrdtool is somewhere other than /usr/bin/rrdtool 
           then you get an incorrect error about checking line 27 of the CLI tool.

--- 0.81 
* ADDED  - Weathermaps link to the Cacti 'Configuration' side-menu. Removed 'Manage Maps' link in Weathermap tab. 
           (knobdy) File-picker in editor no longer masks on *.conf
* FIXED  - sort-order bug for imagemaps (Fran Boon)
* FIXED  - Plugin shows same map twice if you have 'Anyone + users' (Fran Boon, again)
* FIXED  - Default bandwidth duplicated field in editor. (qjy2000_cn)
* FIXED  - Now allow TITLEPOS 0 0 (actually, y=0 is useless, but x=0 might be useful) (knobdy)
* CHANGE - Changed the dependency tests to NOT give a warning about particular DLL names.
* ADDED  - More dependency tests for specific functions.
* CHANGE - Some debug messages to warning, so that they are visible in the logs even without DEBUG on.
* FIXED  - Minor (unreported) bug with sort order in Manage Maps
* CHANGE - Most Cacti Plugin code to use Cacti's (logging) SQL functions. Hopefully this will help windows users
* ADDED  - More error reporting generally.
* FXED   - Error when creating multiple links between nodes in the editor (fozzy)
* CHANGE - Docs update - More FAQs and config reference improvements/amendments.
* ADDED  - 'Cycle' mode to plugin - automatically cycle between your weathermaps.

--- 0.8 
* ADDED  - Ability to have multiple targets for a LINK - aggregate your T1s
* ADDED  - Ability to use half an RRD (use '-' as the DS name) - if you have 'in' in one RRD, and 'out' in another
* ADDED  - Tab-seperated file datasource for TARGET lines, so you can draw anything you can dump into a textfile.
* ADDED  - Ability to specify which corner of a NODE each LINK-end goes to - handy for busy hub nodes
* ADDED  - Support for FreeType & GD fonts
* ADDED  - VIA points - a link can go around corners, and they're nicely curved too.
* ADDED  - Gradient SCALEs - specify two colours for a band to have colours interpolated.
* ADDED  - Many more colour controls for map elements
* ADDED  - Cacti plugin. View and Manage UI, plus poller integration. No editor (yet)
* CHANGE - Example config to use new 0.8 features
* CHANGE - Re-organised manual, and heavily re-written config reference.
* FIXED  -  cacti-pick.php some more (all done now?)
* ADDED  - non-bandwidth bandwidth labels: BWLABELS unformatted

* Many Thanks to James Lang, Niels Baggesen and the [php-weathermap] mailing list for feedback during the testing 
  of this release.

--- 0.71 
* FIXED  - Database code in cacti-pick.php
* FIXED  - Editor to handle blank maps better.
* FIXED  - A problem with cached images in editor.

--- 0.7 
* CHANGE - the manual and example map to reflect all these changes.
* ADDED  - Config options for HTMLOUTPUTFILE and IMAGEOUTPUTFILE. The idea is to reduce the size of command-lines, 
           and make the map files more self-contained. CLI options still take precedence.
* ADDED  - Includes new 'DHTML' editor for the first time. This is still very much in testing - backup any map configs 
           you edit with it first!
* FIXED  - DrawLegend() to not go below a minimum width (it used to use the title length, but that can be too small now)
* ADDED  - BACKGROUNDCOLOR option: take an R G B like the SCALE lines (request from kbriggs)
* REMOVE - Ripped out some of the 'live PHP' code to make DrawMap a bit simpler.
* ADDED  - A check for PNG support in CLI command.
* ADDED  - command-line error-checking (Niels is back again)
* ADDED  - A fix for Windows line-endings. Seems that PHP uses Unix-endings even on Win32
* ADDED  - LINK DEFAULT and NODE DEFAULT - set the defaults for (nearly) any parameter. This also means that most 
           node-affecting and link-affecting parameters are now per-node and per-link.
* ADDED  - ARROWSTYLE option - there's 'classic' and 'compact' with neater arrowheads.
* ADDED  - LABELOFFSET option to change the relative position of the LABEL when an ICON is also used.
* ADDED  - OVERLIBWIDTH and OVERLIBHEIGHT to allow better OverLib output (Niels B, once more)
* CHANGE - Switched to using 24bit images internally. This should improve the handling of PNG transparency in ICONs.
* CHANGE - Improved number and content of error messages.

--- 0.6 - Renamed weathermap.php to just 'weathermap' to make it more
* FIXED  - obviously not a PHP page.
* FIXED  - NODE name regexp (thanks Niels Baggesen)
* ADDED  - Warning for non-existent NODES in LINK (thanks again Niels Baggesen)
* FIXED  - HTML fix for <map> in generated HTML (Niels once more)
* CHANGE - More HTML fixes to make us a bit more XHTML-like.
* ADDED  - You can have an ICON and a LABEL now. LABEL is centred over the NODE, for the moment. Also, there's a 
           drop shadow effect, to make it easier to read the overlaid text.
* ADDED  - New config features: LINKFONT, NODEFONT and KEYFONT to control the fonts used for those things. It's a 
           number from 1 to 5.
* ADDED  - Added BWLABELS NONE for no labels on links at all (request from Ueli Heuer)
* CHANGE - Moved responsibility for complete HTML page from the class to the CLI program - MakeHTML produces an 
           HTML fragment now.
* ADDED  - Ability to customise timestamp and legend text, by adding new text after the KEYPOS and TIMEPOS commands.

--- 0.5a - Fixed totally embarrassing problems with the DS-specification code.
* FIXED  - As far as I can figure I never did test it. Oh dear. Thanks to Jethro Binks for speedy patches.

--- 0.5 - Fixed asymmetric BANDWIDTH bug - thanks rpingar
* FIXED  - Make rrdtool commandline work on Windows - also thanks to rpingar
* ADDED  - Specification of RRD DS names in TARGET ( blah.rrd:ds0:ds1 - that's in then out)
* FIXED  - Fix for different C libraries returning something other than NaN for a NaN (thru rrd) - now we look 
           for good data rather than bad data.
* ADDED  - Allow for decimals in BANDWIDTH specifications - 1.5M should work now
* ADDED  - Allow for decimals in SCALE specifications - mainly useful for very small values on big links
* CHANGE - Brought back sub-1.0 percentages, which got lost somewhere along the line
* ADDED  - Warning for >100% lines
* ADDED  - TIMEPOS option so *you* get to choose where the timestamp goes, mgb
* FIXED  - Stupid bug in ReadRRDData - Weathermap should follow the data better now. Thanks to 'cl'

--- 0.4 - Changed all internals to deal in bits/sec instead of bytes
* CHANGE - Moved timestamp back up to the top-right corner.
* FIXED  - *BREAKAGE* Changed BANDWIDTH to use bits too
* ADDED  - support for K,M,G,T suffixes on bandwidth specs
* ADDED  - KILO config file option to redefine 1K=1000 or 1K=1024 (or anything actually)
* ADDED  - --sizedebug commandline option, to help with figuring out what you did wrong with the new BANDWIDTH 
           format. Shows max bandwidth instead of the current bandwidth on all links. Included something a bit 
           more like a manual.
* FIXED  - *BREAKAGE* Changed BWLABELS options to be bits/percent, since they *are* bits!
* CHANGE - LABEL regexp relaxed to allow spaces in labels
* FIXED  - HTMLSTYLE, BWLABELS regexps tightened up to detect more errors
* ADDED  - New example config in docs/ directory
* CHANGE - Moved editor.php out of the way to random-bits
* ADDED  - Included a copy of auto-overlib.pl, just cos it's handy

--- 0.3 - ICON config directive for NODEs added - same effect as jas0420's perl code
* FIXED  - WriteConfig a little - TITLEs are written.
* ADDED  - OVERLIBGRAPH config is written for NODES.
* FIXED  - Bug with OVERLIBGRAPH DHTML and NODEs
* ADDED  - --image-uri option back in from the perl version

--- 0.2 - NODEs with no label aren't drawn but can still be an endpoint for a LINK.
* FIXED  - Small bugfixes from mgb
* ADDED  - Included the editor.php for people to see
* FIXED  - Code tidyup for weathermap.php

--- 0.1 
Initial pre-release version
