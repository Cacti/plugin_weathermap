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

    $guest_account = TRUE;

    chdir( '../../' );
    include_once( "./include/auth.php" );
    // include_once("./include/config.php");

    // include the weathermap class so that we can get the version
    include_once( dirname( __FILE__ ) . "/lib/Weathermap.class.php" );
    include_once( dirname( __FILE__ ) . "/lib/database.php" );
    include_once( dirname( __FILE__ ) . "/lib/compat.php" );


    $colors = array();
    /* colors */
    $colors[ "dark_outline" ]         = "454E53";
    $colors[ "dark_bar" ]             = "AEB4B7";
    $colors[ "panel" ]                = "E5E5E5";
    $colors[ "panel_text" ]           = "000000";
    $colors[ "panel_link" ]           = "000000";
    $colors[ "light" ]                = "F5F5F5";
    $colors[ "alternate" ]            = "E7E9F2";
    $colors[ "panel_dark" ]           = "C5C5C5";
    $colors[ "header" ]               = "00438C";
    $colors[ "header_panel" ]         = "6d88ad";
    $colors[ "header_text" ]          = "ffffff";
    $colors[ "form_background_dark" ] = "E1E1E1";
    $colors[ "form_alternate1" ]      = "F5F5F5";
    $colors[ "form_alternate2" ]      = "E5E5E5";

    $action = "";
    if ( isset( $_POST[ 'action' ] ) ) {
        $action = $_POST[ 'action' ];
    } elseif ( isset( $_GET[ 'action' ] ) ) {
        $action = $_GET[ 'action' ];
    }

    switch ( $action ) {
        case 'viewthumb': // FALL THROUGH
        case 'viewimage':
            $id = -1;

            if ( isset( $_REQUEST[ 'id' ] ) && ( !is_numeric( $_REQUEST[ 'id' ] ) || strlen( $_REQUEST[ 'id' ] ) == 20 ) ) {
                $id = weathermap_translate_id( $_REQUEST[ 'id' ] );
            }

            if ( isset( $_REQUEST[ 'id' ] ) && is_numeric( $_REQUEST[ 'id' ] ) ) {
                $id = intval( $_REQUEST[ 'id' ] );
            }

            if ( $id >= 0 ) {
                $imageformat = strtolower( read_config_option( "weathermap_output_format" ) );

                $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
                $map    = db_fetch_assoc( "select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $id );

                if ( sizeof( $map ) ) {
                    $imagefile = dirname( __FILE__ ) . '/output/' . '/' . $map[ 0 ][ 'filehash' ] . "." . $imageformat;
                    if ( $action == 'viewthumb' ) $imagefile = dirname( __FILE__ ) . '/output/' . $map[ 0 ][ 'filehash' ] . ".thumb." . $imageformat;

                    $orig_cwd = getcwd();
                    chdir( dirname( __FILE__ ) );

                    header( 'Content-type: image/png' );

                    // readfile_chunked($imagefile);
                    readfile( $imagefile );

                    dir( $orig_cwd );
                } else {
                    // no permission to view this map
                }

            }

            break;

        case 'liveviewimage':
            $id = -1;

            if ( isset( $_REQUEST[ 'id' ] ) && ( !is_numeric( $_REQUEST[ 'id' ] ) || strlen( $_REQUEST[ 'id' ] ) == 20 ) ) {
                $id = weathermap_translate_id( $_REQUEST[ 'id' ] );
            }

            if ( isset( $_REQUEST[ 'id' ] ) && is_numeric( $_REQUEST[ 'id' ] ) ) {
                $id = intval( $_REQUEST[ 'id' ] );
            }

            if ( $id >= 0 ) {
                $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
                $map    = db_fetch_assoc( "select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $id );

                if ( sizeof( $map ) ) {

                    $mapfile  = dirname( __FILE__ ) . '/configs/' . '/' . $map[ 0 ][ 'configfile' ];
                    $orig_cwd = getcwd();
                    chdir( dirname( __FILE__ ) );

                    header( 'Content-type: image/png' );

                    $map          = new WeatherMap;
                    $map->context = '';
                    // $map->context = "cacti";
                    $map->rrdtool = read_config_option( "path_rrdtool" );
                    $map->ReadConfig( $mapfile );
                    $map->ReadData();
                    $map->DrawMap( '', '', 250, TRUE, FALSE );
                    dir( $orig_cwd );
                }

            }

            break;
        case 'liveview':
            top_graph_header();
            print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
            print "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

            $id = -1;

            if ( isset( $_REQUEST[ 'id' ] ) && ( !is_numeric( $_REQUEST[ 'id' ] ) || strlen( $_REQUEST[ 'id' ] ) == 20 ) ) {
                $id = weathermap_translate_id( $_REQUEST[ 'id' ] );
            }

            if ( isset( $_REQUEST[ 'id' ] ) && is_numeric( $_REQUEST[ 'id' ] ) ) {
                $id = intval( $_REQUEST[ 'id' ] );
            }

            if ( $id >= 0 ) {
                $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
                $map    = db_fetch_assoc( "select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $id );

                if ( sizeof( $map ) ) {
                    $maptitle = $map[ 0 ][ 'titlecache' ];

                    print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";
                    ?>
                    <tr bgcolor="<?php print $colors[ "panel" ]; ?>">
                        <td>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="textHeader" nowrap><?php print $maptitle; ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php
                    print "<tr><td>";

                    # print "Generating map $id here now from ".$map[0]['configfile'];

                    $confdir = dirname( __FILE__ ) . '/configs/';
                    // everything else in this file is inside this else
                    $mapname = $map[ 0 ][ 'configfile' ];
                    $mapfile = $confdir . '/' . $mapname;

                    $orig_cwd = getcwd();
                    chdir( dirname( __FILE__ ) );

                    $map = new WeatherMap;
                    // $map->context = "cacti";
                    $map->rrdtool = read_config_option( "path_rrdtool" );
                    print "<pre>";
                    $map->ReadConfig( $mapfile );
                    $map->ReadData();
                    $map->DrawMap( 'null' );
                    $map->PreloadMapHTML();
                    print "</pre>";
                    print "";
                    print "<img src='?action=liveviewimage&id=$id' />\n";
                    print $map->imap->subHTML( "LEGEND:" );
                    print $map->imap->subHTML( "TIMESTAMP" );
                    print $map->imap->subHTML( "NODE:" );
                    print $map->imap->subHTML( "LINK:" );
                    chdir( $orig_cwd );

                    print "</td></tr>";
                    print "</table>";
                } else {
                    print "Map unavailable.";
                }
            } else {
                print "No ID, or unknown map name.";
            }


            weathermap_versionbox();
            bottom_footer();
            break;

        case 'mrss':
            header( 'Content-type: application/rss+xml' );
            print '<?xml version="1.0" encoding="utf-8" standalone="yes"?>' . "\n";
            print '<rss xmlns:media="http://search.yahoo.com/mrss" version="2.0"><channel><title>My Network Weathermaps</title>';
            $userid  = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
            $maplist = db_fetch_assoc( "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by sortorder, id" );
            foreach ( $maplist as $map ) {
                $thumburl = $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php?action=viewthumb&id=" . $map[ 'filehash' ] . "&time=" . time();
                $bigurl   = $config[ 'url_path' ] . "weathermap-cacti-plugin.php?action=viewimage&id=" . $map[ 'filehash' ] . "&time=" . time();
                $linkurl  = $config[ 'url_path' ] . 'weathermap-cacti-plugin.php?action=viewmap&id=' . $map[ 'filehash' ];
                $maptitle = $map[ 'titlecache' ];
                $guid     = $map[ 'filehash' ];
                if ( $maptitle == '' ) $maptitle = "Map for config file: " . $map[ 'configfile' ];

                printf( '<item><title>%s</title><description>Network Weathermap named "%s"</description><link>%s</link><media:thumbnail url="%s"/><media:content url="%s"/><guid isPermaLink="false">%s%s</guid></item>',
                    $maptitle, $maptitle, $linkurl, $thumburl, $bigurl, $config[ 'url_path' ], $guid );
                print "\n";
            }

            print '</channel></rss>';
            break;

        case 'viewmapcycle':

            $fullscreen = 0;
            if ( ( isset( $_REQUEST[ 'fullscreen' ] ) && is_numeric( $_REQUEST[ 'fullscreen' ] ) ) ) {
                $fullscreen = intval( $_REQUEST[ 'fullscreen' ] );
            }

            if ( $fullscreen == 1 ) {
                print "<!DOCTYPE html>\n";
                print "<html><head>";
                print '<LINK rel="stylesheet" type="text/css" media="screen" href="cacti-resources/weathermap.css">';
                print "</head><body id='wm_fullscreen'>";
            } else {
                top_graph_header();
            }

            print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
            print "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";


            $groupid = -1;
            if ( ( isset( $_REQUEST[ 'group' ] ) && is_numeric( $_REQUEST[ 'group' ] ) ) ) {
                $groupid = intval( $_REQUEST[ 'group' ] );
            }

            weathermap_fullview( TRUE, FALSE, $groupid, $fullscreen );
            if ( $fullscreen == 0 ) {
                weathermap_versionbox();
            }

            bottom_footer();
            break;

        case 'viewmap':
            top_graph_header();
            print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
            print "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

            $id = -1;

            if ( isset( $_REQUEST[ 'id' ] ) && ( !is_numeric( $_REQUEST[ 'id' ] ) || strlen( $_REQUEST[ 'id' ] ) == 20 ) ) {
                $id = weathermap_translate_id( $_REQUEST[ 'id' ] );
            }

            if ( isset( $_REQUEST[ 'id' ] ) && is_numeric( $_REQUEST[ 'id' ] ) ) {
                $id = intval( $_REQUEST[ 'id' ] );
            }

            if ( $id >= 0 ) {
                weathermap_singleview( $id );
            }

            weathermap_versionbox();

            bottom_footer();
            break;
        default:
            top_graph_header();
            print "<div id=\"overDiv\" style=\"position:absolute; visibility:hidden; z-index:1000;\"></div>\n";
            print "<script type=\"text/javascript\" src=\"overlib.js\"><!-- overLIB (c) Erik Bosrup --></script> \n";

            $group_id = -1;
            if ( isset( $_REQUEST[ 'group_id' ] ) && ( is_numeric( $_REQUEST[ 'group_id' ] ) ) ) {
                $group_id                    = intval( $_REQUEST[ 'group_id' ] );
                $_SESSION[ 'wm_last_group' ] = $group_id;
            } else {
                if ( isset( $_SESSION[ 'wm_last_group' ] ) ) {
                    $group_id = intval( $_SESSION[ 'wm_last_group' ] );
                }
            }

            $tabs    = weathermap_get_valid_tabs();
            $tab_ids = array_keys( $tabs );
            if ( ( $group_id == -1 ) && ( sizeof( $tab_ids ) > 0 ) ) {
                $group_id = $tab_ids[ 0 ];
            }

            if ( read_config_option( "weathermap_pagestyle" ) == 0 ) {
                weathermap_thumbview( $group_id );
            }
            if ( read_config_option( "weathermap_pagestyle" ) == 1 ) {
                weathermap_fullview( FALSE, FALSE, $group_id );
            }
            if ( read_config_option( "weathermap_pagestyle" ) == 2 ) {
                weathermap_fullview( FALSE, TRUE, $group_id );
            }

            weathermap_versionbox();
            bottom_footer();
            break;
    }


    function weathermap_cycleview()
    {

    }

    function weathermap_singleview( $mapid )
    {
        global $colors;

        $is_wm_admin = FALSE;

        $outdir  = dirname( __FILE__ ) . '/output/';
        $confdir = dirname( __FILE__ ) . '/configs/';

        $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
        $map    = db_fetch_assoc( "select weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) and weathermap_maps.id=" . $mapid );


        if ( sizeof( $map ) ) {
            # print do_hook_function ('weathermap_page_top', array($map[0]['id'], $map[0]['titlecache']) );
            print do_hook_function( 'weathermap_page_top', '' );

            $htmlfile = $outdir . $map[ 0 ][ 'filehash' ] . ".html";
            $maptitle = $map[ 0 ][ 'titlecache' ];
            if ( $maptitle == '' ) $maptitle = "Map for config file: " . $map[ 0 ][ 'configfile' ];

            weathermap_mapselector( $mapid );

            print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";
            ?>
            <tr bgcolor="<?php print $colors[ "panel" ]; ?>">
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="textHeader" nowrap><?php print $maptitle;

                                    if ( $is_wm_admin ) {

                                        print "<span style='font-size: 80%'>";
                                        print "[ <a href='weathermap-cacti-plugin-mgmt.php?action=map_settings&id=" . $mapid . "'>Map Settings</a> |";
                                        print "<a href='weathermap-cacti-plugin-mgmt.php?action=perms_edit&id=" . $mapid . "'>Map Permissions</a> |";
                                        print "<a href=''>Edit Map</a> ]";
                                        print "</span>";
                                    }


                                ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php
            print "<tr><td>";

            if ( file_exists( $htmlfile ) ) {
		echo "<div class='fixscroll' style='overflow:auto'>";
                include( $htmlfile );
		echo "</div>";
            } else {
                print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.";

                global $config, $user_auth_realms, $user_auth_realm_filenames;
                $realm_id2 = 0;

                if ( isset( $user_auth_realm_filenames[ basename( 'weathermap-cacti-plugin.php' ) ] ) ) {
                    $realm_id2 = $user_auth_realm_filenames[ basename( 'weathermap-cacti-plugin.php' ) ];
                }

                $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
                if ( ( db_fetch_assoc( "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.us
				er_id='" . $userid . "' and user_auth_realm.realm_id='$realm_id2'" ) ) || ( empty( $realm_id2 ) ) ) {

                    print " (If this message stays here for more than one poller cycle, then check your cacti.log file for errors!)";

                }
                print "</em></div>";
            }
            print "</td></tr>";
            print "</table>";

        }
    }

    function weathermap_show_manage_tab()
    {
        global $config, $user_auth_realms, $user_auth_realm_filenames;
        $realm_id2 = 0;

        if ( isset( $user_auth_realm_filenames[ 'weathermap-cacti-plugin-mgmt.php' ] ) ) {
            $realm_id2 = $user_auth_realm_filenames[ 'weathermap-cacti-plugin-mgmt.php' ];
        }
        $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
        if ( ( db_fetch_assoc( "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $userid . "' and user_auth_realm.realm_id='$realm_id2'" ) ) || ( empty( $realm_id2 ) ) ) {

            print '<a href="' . $config[ 'url_path' ] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php">Manage Maps</a>';
        }
    }

    function weathermap_thumbview( $limit_to_group = -1 )
    {
        global $colors, $config;

        $total_map_count_SQL = "select count(*) as total from weathermap_maps";
        $total_map_count     = db_fetch_cell( $total_map_count_SQL );

        $userid      = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
        $maplist_SQL = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";
        if ( $limit_to_group > 0 ) $maplist_SQL .= " weathermap_maps.group_id=" . $limit_to_group . " and ";
        $maplist_SQL .= " (userid=" . $userid . " or userid=0) order by sortorder, id";

        $maplist = db_fetch_assoc( $maplist_SQL );

        // if there's only one map, ignore the thumbnail setting and show it fullsize
        if ( sizeof( $maplist ) == 1 ) {
            $pagetitle = "Network Weathermap";
            weathermap_fullview( FALSE, FALSE, $limit_to_group );
        } else {
            $pagetitle = "Network Weathermaps";

            print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='2'>\n";
            ?>
            <tr bgcolor="<?php print $colors[ "panel" ]; ?>">
                <td>
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="textHeader" nowrap> <?php print $pagetitle; ?></td>
                            <td align="right"><a
                                        href="<?php echo $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php"; ?>?action=viewmapcycle">automatically
                                    cycle</a> between full-size maps)
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td><i>Click on thumbnails for a full view (or you can <a
                                href="<?php echo $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php"; ?>?action=viewmapcycle">automatically
                            cycle</a> between full-size maps)</i></td>
            </tr>
            <?php
            print "</table>";
            $showlivelinks = intval( read_config_option( "weathermap_live_view" ) );

            weathermap_tabs( $limit_to_group );
            $i = 0;
            if ( sizeof( $maplist ) > 0 ) {

                $outdir  = dirname( __FILE__ ) . '/output/';
                $confdir = dirname( __FILE__ ) . '/configs/';

                $imageformat = strtolower( read_config_option( "weathermap_output_format" ) );

                print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";
                print "<tr><td class='wm_gallery'>";
                foreach ( $maplist as $map ) {
                    $i++;

                    $imgsize = "";
                    # $thumbfile = $outdir."weathermap_thumb_".$map['id'].".".$imageformat;
                    # $thumburl = "output/weathermap_thumb_".$map['id'].".".$imageformat."?time=".time();
                    $thumbfile = $outdir . $map[ 'filehash' ] . ".thumb." . $imageformat;
                    $thumburl  = $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php?action=viewthumb&id=" . $map[ 'filehash' ] . "&time=" . time();
                    if ( $map[ 'thumb_width' ] > 0 ) {
                        $imgsize = ' WIDTH="' . $map[ 'thumb_width' ] . '" HEIGHT="' . $map[ 'thumb_height' ] . '" ';
                    }
                    $maptitle = $map[ 'titlecache' ];
                    if ( $maptitle == '' ) $maptitle = "Map for config file: " . $map[ 'configfile' ];

                    print '<div class="wm_thumbcontainer" style="margin: 2px; border: 1px solid #bbbbbb; padding: 2px; float:left;">';
                    if ( file_exists( $thumbfile ) ) {
                        print '<div class="wm_thumbtitle" style="font-size: 1.2em; font-weight: bold; text-align: center">' . $maptitle . '</div><a href=' . $config[ 'url_path' ] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&id=' . $map[ 'filehash' ] . '><img class="wm_thumb" ' . $imgsize . 'src="' . $thumburl . '" alt="' . $maptitle . '" border="0" hspace="5" vspace="5" title="' . $maptitle . '"/></a>';
                    } else {
                        print "(thumbnail for map not created yet)";
                    }
                    if ( $showlivelinks == 1 ) {
                        print "<a href='" . $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php?action=liveview&id=" . $map[ 'filehash' ] . "'>(live)</a>";
                    }
                    print '</div> ';
                }
                print "</td></tr>";
                print "</table>";

            } else {
                print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em>\n";

                if ( $total_map_count == 0 ) {
                    print '<p>To add a map to the schedule, go to the <a href="' . $config[ 'url_path' ] . 'plugins/weathermap/weathermap-cacti-plugin-mgmt.php">Manage...Weathermaps page</a> and add one.</p>';
                }

                print "</div>";

            }
        }
    }

    function weathermap_fullview( $cycle = FALSE, $firstonly = FALSE, $limit_to_group = -1, $fullscreen = 0 )
    {
    global $colors, $config;
    $_SESSION[ 'custom' ] = FALSE;
    $userid               = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );

    $maplist_SQL = "select distinct weathermap_maps.* from weathermap_auth,weathermap_maps where weathermap_maps.id=weathermap_auth.mapid and active='on' and ";

    if ( $limit_to_group > 0 ) $maplist_SQL .= " weathermap_maps.group_id=" . $limit_to_group . " and ";

    $maplist_SQL .= " (userid=" . $userid . " or userid=0) order by sortorder, id";
    if ( $firstonly ) {
        $maplist_SQL .= " LIMIT 1";
    }
    $maplist = db_fetch_assoc( $maplist_SQL );

    if ( sizeof( $maplist ) == 1 ) {
        $pagetitle = "Network Weathermap";
    } else {
        $pagetitle = "Network Weathermaps";
    }
    $class = "";
    if ( $cycle ) $class = "inplace";
    if ( $fullscreen ) $class = "fullscreen";

    if ( $cycle ) {
        if ( $fullscreen ) {
            print "<script src='" . $config[ 'url_path' ] . "plugins/weathermap/vendor/jquery/dist/jquery.min.js'></script>";
        }
        print "<script src='" . $config[ 'url_path' ] . "plugins/weathermap/vendor/jquery-idletimer/dist/idle-timer.min.js'></script>";
        $extra = "";
        if ( $limit_to_group > 0 ) $extra = " in this group";
        ?>
        <div id="wmcyclecontrolbox" class="<?php print $class ?>">
            <div id="wm_progress"></div>
            <div id="wm_cyclecontrols">
                <a id="cycle_stop" href="?action="><img
                            src="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/cacti-resources/img/control_stop_blue.png"
                            width="16" height="16"/></a>
                <a id="cycle_prev" href="#"><img
                            src="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/cacti-resources/img/control_rewind_blue.png"
                            width="16" height="16"/></a>
                <a id="cycle_pause" href="#"><img
                            src="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/cacti-resources/img/control_pause_blue.png"
                            width="16" height="16"/></a>
                <a id="cycle_next" href="#"><img
                            src="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/cacti-resources/img/control_fastforward_blue.png"
                            width="16" height="16"/></a>
                <a id="cycle_fullscreen"
                   href="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&fullscreen=1&group=<?php echo $limit_to_group; ?>"><img
                            src="cacti-resources/img/arrow_out.png" width="16" height="16"/></a>
                Showing <span id="wm_current_map">1</span> of <span id="wm_total_map">1</span>.
                Cycling all available maps<?php echo $extra; ?>.
            </div>
        </div>
        <?php
    }

    // only draw the whole screen if we're not cycling, or we're cycling without fullscreen mode
    if ( $cycle == FALSE || $fullscreen == 0 ) {
        print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='2'>\n";
        ?>
        <tr bgcolor="<?php print $colors[ "panel" ]; ?>">
            <td>
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="textHeader" nowrap> <?php print $pagetitle; ?> </td>
                        <td align="right">
                            <?php if ( !$cycle ) { ?>
                                (automatically cycle between full-size maps (<?php

                                if ( $limit_to_group > 0 ) {

                                    print '<a href = "' . $config[ 'url_path' ] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle&group=' . intval( $limit_to_group ) . '">within this group</a>, or ';
                                }
                                print ' <a href = "' . $config[ 'url_path' ] . 'plugins/weathermap/weathermap-cacti-plugin.php?action=viewmapcycle">all maps</a>';
                                ?>)

                                <?php
                            }

                            ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php
        print "</table>";

        weathermap_tabs( $limit_to_group );
    }

    $i = 0;
    if ( sizeof( $maplist ) > 0 ) {
    print "<div class='all_map_holder $class'>";

    $outdir  = dirname( __FILE__ ) . '/output/';
    $confdir = dirname( __FILE__ ) . '/configs/';
    foreach ( $maplist

              as $map )
    {
    $i++;
    $htmlfile = $outdir . $map[ 'filehash' ] . ".html";
    $maptitle = $map[ 'titlecache' ];
    if ( $maptitle == '' ) $maptitle = "Map for config file: " . $map[ 'configfile' ];

    print '<div class="weathermapholder" id="mapholder_' . $map[ 'filehash' ] . '">';
    if ( $cycle == FALSE || $fullscreen == 0 ) {
    print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";


?>
    <tr bgcolor="#<?php echo $colors[ "header_panel" ] ?>">
        <td colspan="3">
            <table width="100%" cellspacing="0" cellpadding="3" border="0">
                <tr>
                    <td align="left" class="textHeaderDark">
                        <a name="map_<?php echo $map[ 'filehash' ]; ?>">
                        </a><?php print htmlspecialchars( $maptitle ); ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
<tr>
    <td>
        <?php
            }

            if ( file_exists( $htmlfile ) ) {
		echo "<div class='fixscroll' style='overflow:auto'>";
                include( $htmlfile );
		echo "</div>";
            } else {
                print "<div align=\"center\" style=\"padding:20px\"><em>This map hasn't been created yet.</em></div>";
            }

            if ( $cycle == FALSE || $fullscreen == 0 ) {
                print '</td></tr>';
                print "</table>";
            }
            print '</div>';
            }
            print "</div>";

            if ( $cycle ) {
                $refreshtime  = read_config_option( "weathermap_cycle_refresh" );
                $poller_cycle = read_config_option( "poller_interval" );
                ?>
                <script type="text/javascript"
                        src="<?php echo $config[ 'url_path' ]; ?>plugins/weathermap/cacti-resources/map-cycle.js"></script>
                <script type="text/javascript">
                    $(document).ready(function () {
                        WMcycler.start({
                            fullscreen: <?php echo( $fullscreen ? "1" : "0" ); ?>,
                            poller_cycle: <?php echo $poller_cycle * 1000; ?>,
                            period: <?php echo $refreshtime * 1000; ?>});
                    });
                </script>
                <?php
            }
            }
            else {
                print "<div align=\"center\" style=\"padding:20px\"><em>You Have No Maps</em></div>\n";
            }
            }


            function weathermap_translate_id( $idname )
            {
                $pdo = weathermap_get_pdo();

                $stmt = $pdo->prepare( "select id from weathermap_maps where configfile=? or filehash=?" );
                $stmt->execute( array( $idname, $idname ) );
                $map = $stmt->fetchAll( PDO::FETCH_ASSOC );

//	$SQL = "select id from weathermap_maps where configfile='".mysql_real_escape_string($idname)."' or filehash='".mysql_real_escape_string($idname)."'";
//	$map = db_fetch_assoc($SQL);

                return ( $map[ 0 ][ 'id' ] );
            }

            function weathermap_versionbox()
            {
            global $WEATHERMAP_VERSION, $colors;
            global $config, $user_auth_realms, $user_auth_realm_filenames;

            $pagefoot = "Powered by <a href=\"http://www.network-weathermap.com/?v=$WEATHERMAP_VERSION\">PHP Weathermap version $WEATHERMAP_VERSION</a>";

            $realm_id2 = 0;

            if ( isset( $user_auth_realm_filenames[ 'weathermap-cacti-plugin-mgmt.php' ] ) ) {
                $realm_id2 = $user_auth_realm_filenames[ 'weathermap-cacti-plugin-mgmt.php' ];
            }
            $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
            if ( ( db_fetch_assoc( "select user_auth_realm.realm_id from user_auth_realm where user_auth_realm.user_id='" . $userid . "' and user_auth_realm.realm_id='$realm_id2'" ) ) || ( empty( $realm_id2 ) ) ) {
                $pagefoot .= " --- <a href='" . $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin-mgmt.php' title='Go to the map management page'>Weathermap Management</a>";
                $pagefoot .= " | <a target=\"_blank\" href=\"docs/\">Local Documentation</a>";
                $pagefoot .= " | <a target=\"_blank\" href=\"" . $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin-editor.php\">Editor</a>";
            }


            print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='1'>\n";

        ?>
    <tr bgcolor="<?php print $colors[ "panel" ]; ?>">
        <td>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="textHeader" nowrap> <?php print $pagefoot; ?> </td>
                </tr>
            </table>
        </td>
    </tr>
<?php
    print "</table>";
    }


    function readfile_chunked( $filename )
    {
        $chunksize = 1 * ( 1024 * 1024 ); // how many bytes per chunk
        $buffer    = '';
        $cnt       = 0;

        $handle = fopen( $filename, 'rb' );
        if ( $handle === FALSE ) return FALSE;

        while ( !feof( $handle ) ) {
            $buffer = fread( $handle, $chunksize );
            echo $buffer;
        }
        $status = fclose( $handle );
        return $status;
    }

    function weathermap_footer_links()
    {
        global $colors;
        global $WEATHERMAP_VERSION;
        print '<br />';
        html_start_box( "<center><a target=\"_blank\" class=\"linkOverDark\" href=\"docs/\">Local Documentation</a> -- <a target=\"_blank\" class=\"linkOverDark\" href=\"http://www.network-weathermap.com/\">Weathermap Website</a> -- <a target=\"_target\" class=\"linkOverDark\" href=\"weathermap-cacti-plugin-editor.php?plug=1\">Weathermap Editor</a> -- This is version $WEATHERMAP_VERSION</center>", "78%", $colors[ "header" ], "2", "center", "" );
        html_end_box();
    }

    function weathermap_mapselector( $current_id = 0 )
    {
        global $colors;

        $show_selector = intval( read_config_option( "weathermap_map_selector" ) );

        if ( $show_selector == 0 ) return FALSE;

        $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
        $maps   = db_fetch_assoc( "select distinct weathermap_maps.*,weathermap_groups.name, weathermap_groups.sortorder as gsort from weathermap_groups,weathermap_auth,weathermap_maps where weathermap_maps.group_id=weathermap_groups.id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by gsort, sortorder" );

        if ( sizeof( $maps ) > 1 ) {

            /* include graph view filter selector */
            print "<br/><table width='100%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='3'>\n";
            ?>
            <tr bgcolor="<?php print $colors[ "panel" ]; ?>" class="noprint">
                <form name="weathermap_select" method="post" action="">
                    <input name="action" value="viewmap" type="hidden">
                    <td class="noprint">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr class="noprint">
                                <td nowrap style='white-space: nowrap;' width="40">
                                    &nbsp;<strong>Jump To Map:</strong>&nbsp;
                                </td>
                                <td>
                                    <select name="id">
                                        <?php

                                            $ngroups   = 0;
                                            $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                            foreach ( $maps as $map ) {
                                                if ( $current_id == $map[ 'id' ] ) $nullhash = $map[ 'filehash' ];
                                                if ( $map[ 'name' ] != $lastgroup ) {
                                                    $ngroups++;
                                                    $lastgroup = $map[ 'name' ];
                                                }
                                            }


                                            $lastgroup = "------lasdjflkjsdlfkjlksdjflksjdflkjsldjlkjsd";
                                            foreach ( $maps as $map ) {
                                                if ( $ngroups > 1 && $map[ 'name' ] != $lastgroup ) {
                                                    print "<option style='font-weight: bold; font-style: italic' value='$nullhash'>" . htmlspecialchars( $map[ 'name' ] ) . "</option>";
                                                    $lastgroup = $map[ 'name' ];
                                                }
                                                print '<option ';
                                                if ( $current_id == $map[ 'id' ] ) print " SELECTED ";
                                                print 'value="' . $map[ 'filehash' ] . '">';
                                                // if we're showing group headings, then indent the map names
                                                if ( $ngroups > 1 ) {
                                                    print " - ";
                                                }
                                                print htmlspecialchars( $map[ 'titlecache' ] ) . '</option>';
                                            }
                                        ?>
                                    </select>
                                    &nbsp;<input type="image" src="../../images/enable_icon.png" alt="Go" border="0"
                                                 align="absmiddle">
                                </td>
                            </tr>
                        </table>
                    </td>
                </form>
            </tr>
            <?php

            print "</table>";
        }
    }

    function weathermap_get_valid_tabs()
    {
        $tabs   = array();
        $userid = ( isset( $_SESSION[ "sess_user_id" ] ) ? intval( $_SESSION[ "sess_user_id" ] ) : 1 );
        $maps   = db_fetch_assoc( "select weathermap_maps.*, weathermap_groups.name as group_name from weathermap_auth,weathermap_maps, weathermap_groups where weathermap_groups.id=weathermap_maps.group_id and weathermap_maps.id=weathermap_auth.mapid and active='on' and (userid=" . $userid . " or userid=0) order by weathermap_groups.sortorder" );


        foreach ( $maps as $map ) {
            $tabs[ $map[ 'group_id' ] ] = $map[ 'group_name' ];
        }

        return ( $tabs );
    }

    function weathermap_tabs( $current_tab )
    {
        global $colors, $config;

        // $current_tab=2;

        $tabs = weathermap_get_valid_tabs();

        # print "Limiting to $current_tab\n";

        if ( sizeof( $tabs ) > 1 ) {
            /* draw the categories tabs on the top of the page */
            print "<p></p><table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

            if ( sizeof( $tabs ) > 0 ) {
                $show_all = intval( read_config_option( "weathermap_all_tab" ) );
                if ( $show_all == 1 ) {
                    $tabs[ '-2' ] = "All Maps";
                }

                foreach ( array_keys( $tabs ) as $tab_short_name ) {
                    print "<td " . ( ( $tab_short_name == $current_tab ) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'" ) . " nowrap='nowrap' width='" . ( strlen( $tabs[ $tab_short_name ] ) * 9 ) . "' align='center' class='tab'>
	                                <span class='textHeader'><a href='" . $config[ 'url_path' ] . "plugins/weathermap/weathermap-cacti-plugin.php?group_id=$tab_short_name'>$tabs[$tab_short_name]</a></span>
	                                </td>\n
	                                <td width='1'></td>\n";
                }

            }

            print "<td></td>\n</tr></table>\n";

            return ( TRUE );
        } else {
            return ( FALSE );
        }

    }

    // vim:ts=4:sw=4:
?>
